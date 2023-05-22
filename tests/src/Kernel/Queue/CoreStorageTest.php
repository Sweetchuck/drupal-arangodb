<?php

declare(strict_types=1);


namespace Drupal\Tests\arangodb\Kernel\Queue;

use Drupal\arangodb\Queue\CoreStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\arangodb\Traits\ConnectionTrait;
use Drupal\Tests\arangodb\Traits\TestTime;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @covers \Drupal\arangodb\Queue\CoreStorage
 */
class CoreStorageTest extends KernelTestBase {

  use ConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'arangodb',
    'user',
    'system',
  ];

  protected TestTime $time;

  protected LoggerInterface $logger;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->time = new TestTime();
    $this->logger = new NullLogger();

    parent::setUp();
    $this->setUpArangodbConnection();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ClientException
   */
  protected function tearDown(): void {
    $this->tearDownArangodbDropCollections($this->collectionNamePrefix);
    parent::tearDown();
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  public function testQueue(): void {
    $connectionFactory = $this->createConnectionFactory();

    $collectionNamePattern = "{$this->collectionNamePrefix}_queue_core_common";

    $queue1 = new CoreStorage('q1');
    $queue1
      ->setCollectionNamePattern($collectionNamePattern)
      ->setConnection($connectionFactory->get('default'))
      ->setSchemaManager($this->container->get('arangodb.queue_core.schema_manager.default'))
      ->setDocumentConverter($this->container->get('arangodb.queue_core.document_converter.default'))
      ->setTime($this->time)
      ->setLogger($this->logger);
    $queue1->createQueue();

    $queue2 = new CoreStorage('q2');
    $queue2
      ->setCollectionNamePattern($collectionNamePattern)
      ->setConnection($connectionFactory->get('default'))
      ->setSchemaManager($this->container->get('arangodb.queue_core.schema_manager.default'))
      ->setDocumentConverter($this->container->get('arangodb.queue_core.document_converter.default'))
      ->setTime($this->time)
      ->setLogger($this->logger);
    $queue2->createQueue();

    $this->runQueueTest($queue1, $queue2);
  }

  /**
   * @throws \ArangoDBClient\Exception
   * @throws \Exception
   */
  protected function runQueueTest(CoreStorage $queue1, CoreStorage $queue2): void {
    $data = [];
    for ($i = 0; $i < 4; $i++) {
      $data[] = [
        $this->randomMachineName() => $this->randomMachineName(),
      ];
    }

    // Queue items 1 and 2 in the queue1.
    $queue1->createItem($data[0]);
    $queue1->createItem($data[1]);

    // Retrieve two items from queue1.
    $items = [];
    $new_items = [];

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    // First two dequeued items should match the first two items we queued.
    static::assertEquals(2, $this->queueScore($data, $new_items), 'Two items matched');

    // Add two more items.
    $queue1->createItem($data[2]);
    $queue1->createItem($data[3]);

    static::assertSame(4, $queue1->numberOfItems(), 'Queue 1 is not empty after adding items.');
    static::assertSame(0, $queue2->numberOfItems(), 'Queue 2 is empty while Queue 1 has items');

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    $items[] = $item = $queue1->claimItem();
    $new_items[] = $item->data;

    // All dequeued items should match the items we queued exactly once,
    // therefore the score must be exactly 4.
    static::assertEquals(4, $this->queueScore($data, $new_items), 'Four items matched');

    // There should be no duplicate items.
    static::assertEquals(4, $this->queueScore($new_items, $new_items), 'Four items matched');

    // Delete all items from queue1.
    foreach ($items as $item) {
      $queue1->deleteItem($item);
    }

    // Check that both queues are empty.
    static::assertSame(0, $queue1->numberOfItems(), 'Queue 1 is empty');
    static::assertSame(0, $queue2->numberOfItems(), 'Queue 2 is empty');
  }

  /**
   * Returns the number of equal items in two arrays.
   */
  protected function queueScore($items, $new_items): int {
    $score = 0;
    foreach ($items as $item) {
      foreach ($new_items as $new_item) {
        if ($item === $new_item) {
          $score++;
        }
      }
    }

    return $score;
  }

}
