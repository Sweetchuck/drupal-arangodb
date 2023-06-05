<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Kernel\Flood;

use ArangoDBClient\Statement;
use Drupal\arangodb\Flood\Backend;
use Drupal\arangodb\Flood\DocumentConverter;
use Drupal\arangodb\Flood\SchemaManager;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\arangodb\Traits\ConnectionTrait;
use Drupal\Tests\arangodb\Traits\TestTime;

/**
 * Functional tests for the flood control mechanism.
 *
 * @group Flood
 */
class BackendTest extends KernelTestBase {

  use ConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'arangodb',
  ];

  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->time = new TestTime();
    $this->time->requestTime = 100.0;
    $this->time->currentTime = $this->time->requestTime;
    $this->container->set('datetime.time', $this->time);
    $this->setUpArangodbConnection();
  }

  /**
   * @throws \Exception
   */
  protected function tearDown(): void {
    $this->tearDownArangodbDropCollections($this->collectionNamePrefix);
    parent::tearDown();
  }

  /**
   * @throws \Exception
   */
  protected function createFlood(): Backend {
    $time = $this->container->get('datetime.time');

    return new Backend(
      $this->createConnectionFactory(),
      'default',
      new SchemaManager(),
      new DocumentConverter($time),
      $this->container->get('request_stack'),
      $time,
      [
        'collectionNamePattern' => "{$this->collectionNamePrefix}_flood",
      ],
    );
  }

  /**
   * Tests flood control mechanism clean-up.
   *
   * @throws \Exception
   */
  public function testClear(): void {
    $flood = $this->createFlood();
    $this->container->set('flood', $flood);

    $threshold = 1;
    $window_expired = -1;
    $name = 'flood_test_cleanup';
    $cron = $this->container->get('cron');

    static::assertTrue($flood->isAllowed($name, $threshold));
    $flood->register($name, $window_expired);
    static::assertFalse($flood->isAllowed($name, $threshold));
    $cron->run();
    static::assertTrue($flood->isAllowed($name, $threshold));

    $flood->register($name);
    static::assertFalse($flood->isAllowed($name, $threshold));
    $cron->run();
    static::assertFalse($flood->isAllowed($name, $threshold));
  }

  /**
   * Tests flood workflow.
   *
   * @throws \Exception
   */
  public function testWorkflow(): void {
    $flood1 = $this->createFlood();
    $identifier1 = 'id-1';
    $identifier2 = 'id-2';
    $name1 = 'name-1';

    // Tests threshold limit and expiration.
    for ($threshold = 1; $threshold < 4; $threshold++) {
      for ($amount = 1; $amount <= $threshold; $amount++) {
        static::assertTrue(
          $flood1->isAllowed($name1, $threshold, 1, $identifier1),
          "$identifier1:$name1:$amount is allowed",
        );
        $flood1->register($name1, 1, $identifier1);
      }

      static::assertFalse(
        $flood1->isAllowed($name1, $threshold, 1, $identifier1),
        "$identifier1:$name1:$threshold+1 is not allowed",
      );

      static::assertTrue(
        $flood1->isAllowed($name1, $threshold, 1, $identifier2),
        "$identifier2:$name1:1 is allowed",
      );

      $this->time->currentTime += 2;

      static::assertTrue(
        $flood1->isAllowed($name1, 1, 1, $identifier1),
        "$identifier1:$name1:1 is allowed again, because previous ones are expired",
      );

      $flood1->clear($name1, $identifier1);
    }

    $this->time->currentTime = 200;
    $flood1->garbageCollection();

    $flood1->register('a', 1, 'id-1');
    $flood1->register('b', 1, 'id-1');
    $flood1->register('c', 3, 'id-1');
    $flood1->register('d', 3, 'id-1');

    $this->time->currentTime += 2;
    $flood1->garbageCollection();

    $query = <<< AQL
    FOR doc IN @@collection
      SORT
        doc._key
      RETURN doc
    AQL;

    $bindVars = [
      '@collection' => $flood1->getCollectionName(),
    ];

    $connection = $this->createConnectionFactory()->get('default');
    $statement = new Statement(
      $connection,
      [
        'query' => $query,
        'bindVars' => $bindVars,
      ],
    );
    $result = $statement->execute();

    static::assertSame(
      2,
      $result->getCount(),
      'after garbage collection there are two document in the flood collection',
    );

    /** @var \ArangoDBClient\Document $document */
    $document = $result->current();
    $result->next();
    $documentValues = $document->getAll();
    unset(
      $documentValues['_id'],
      $documentValues['_key'],
      $documentValues['_rev'],
    );
    static::assertSame(
      [
        'identifier' => 'id-1',
        'name' => 'c',
        'created' => 200,
        'expiration' => 203,
      ],
      $documentValues,
    );

    /** @var \ArangoDBClient\Document $document */
    $document = $result->current();
    $result->next();
    $documentValues = $document->getAll();
    unset(
      $documentValues['_id'],
      $documentValues['_key'],
      $documentValues['_rev'],
    );
    static::assertSame(
      [
        'identifier' => 'id-1',
        'name' => 'd',
        'created' => 200,
        'expiration' => 203,
      ],
      $documentValues,
    );
  }

}
