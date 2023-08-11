<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Kernel\Plugin\AdvancedQueue\Backend;

use Drupal\advancedqueue\Entity\Queue;
use Drupal\advancedqueue\Entity\QueueInterface;
use Drupal\advancedqueue\Job;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\arangodb\Traits\ConnectionTrait;

/**
 * @covers \Drupal\arangodb\Plugin\AdvancedQueue\Backend\Arangodb
 */
class ArangodbTest extends KernelTestBase {

  use ConnectionTrait;

  /**
   * {@inheritdoc}
   *
   * @phpstan-var array<string>
   */
  protected static $modules = [
    'advancedqueue',
    'arangodb',
  ];

  protected int $currentTime = 0;

  /**
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpArangodbConnection();

    // Override the current time to control job timestamps.
    $this->currentTime = 635814000;
    $currentTime =& $this->currentTime;
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->will(function () use (&$currentTime): int {
      return $currentTime;
    });
    $this->container->set('datetime.time', $time->reveal());

    $all = Settings::getAll();
    $all['arangodb.connection_options'] = $this->getConnectionOptions();
    $this->container->set('settings', new Settings($all));

    $this->installSchema('advancedqueue', ['advancedqueue']);
  }

  /**
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ClientException
   */
  protected function tearDown(): void {
    $this->tearDownArangodbDropCollections($this->collectionNamePrefix);
    parent::tearDown();
  }

  protected function createQueue(string $id, string $collection_name_pattern): QueueInterface {
    $queue = Queue::create([
      'id' => $id,
      'label' => $id,
      'backend' => 'arangodb',
      'backend_configuration' => [
        'lease_time' => 5,
        'connection_name' => 'default',
        'storage_options' => [
          'collection_name_pattern' => "{$this->collectionNamePrefix}_queue_advanced_$collection_name_pattern",
        ],
      ],
    ]);

    try {
      $queue->save();
    }
    catch (\Exception $e) {
      static::fail($e->getMessage());
    }

    return $queue;
  }

  /**
   * @throws \Exception
   */
  public function testQueue(): void {
    $this->currentTime = 635814000;

    $job1 = Job::create('simple', ['test' => '1']);
    $job2 = Job::create('simple', ['test' => '2']);
    $job3 = Job::create('simple', ['test' => '3']);
    $job4 = Job::create('simple', ['test' => '4']);

    $queue1 = $this->createQueue('q1', 'shared');
    /** @var \Drupal\arangodb\Plugin\AdvancedQueue\Backend\Arangodb $queue1Backend */
    $queue1Backend = $queue1->getBackend();

    $queue2 = $this->createQueue('q2', 'shared');
    /** @var \Drupal\arangodb\Plugin\AdvancedQueue\Backend\Arangodb $queue2Backend */
    $queue2Backend = $queue2->getBackend();

    $queue1Backend->enqueueJobs([$job1, $job3]);

    static::assertQueuedJob('q1', 0, $job1);
    static::assertQueuedJob('q1', 0, $job3);
    static::assertSame(
      [
        Job::STATE_QUEUED => 2,
        Job::STATE_PROCESSING => 0,
        Job::STATE_SUCCESS => 0,
        Job::STATE_FAILURE => 0,
      ],
      $queue1Backend->countJobs(),
    );

    // Update the jobs to match how they'll look when claimed.
    $job1->setState(Job::STATE_PROCESSING);
    $job1->setExpiresTime($this->currentTime + 5);
    $job3->setExpiresTime($this->currentTime + 5);
    $job3->setState(Job::STATE_PROCESSING);

    static::assertEquals($job1, $queue1Backend->claimJob());
    static::assertEquals($job3, $queue1Backend->claimJob());
    static::assertNull($queue1Backend->claimJob());

    $queue2Backend->enqueueJob($job2);
    $queue2Backend->enqueueJob($job4);
    static::assertSame(
      [
        Job::STATE_QUEUED => 2,
        Job::STATE_PROCESSING => 0,
        Job::STATE_SUCCESS => 0,
        Job::STATE_FAILURE => 0,
      ],
      $queue2Backend->countJobs(),
    );

    $queue2Backend->deleteJob($job2->getId());
    static::assertSame(Job::STATE_PROCESSING, $queue2Backend->claimJob()->getState());
    static::assertSame(
      [
        Job::STATE_QUEUED => 0,
        Job::STATE_PROCESSING => 1,
        Job::STATE_SUCCESS => 0,
        Job::STATE_FAILURE => 0,
      ],
      $queue2Backend->countJobs(),
    );

    // Confirm fail -> retry -> success.
    $job4->setState(Job::STATE_FAILURE);
    $queue2Backend->onFailure($job4);
    static::assertEquals($this->currentTime, $job4->getProcessedTime());
    static::assertEmpty($job4->getExpiresTime());

    $queue2Backend->retryJob($job4, 9);
    static::assertEquals(Job::STATE_QUEUED, $job4->getState());
    static::assertEquals(1, $job4->getNumRetries());
    static::assertEquals($this->currentTime + 9, $job4->getAvailableTime());
    static::assertEmpty($job4->getExpiresTime());

    $this->currentTime += 10;
    $job4->setState(Job::STATE_PROCESSING);
    $job4->setExpiresTime($this->currentTime + 5);
    static::assertEquals($job4, $queue2Backend->claimJob());

    $job4->setState(Job::STATE_SUCCESS);
    $queue2Backend->onSuccess($job4);
    static::assertEquals($this->currentTime, $job4->getProcessedTime());
    static::assertEmpty($job4->getExpiresTime());

    $queue2Backend->enqueueJob($job1);
    $queue2Backend->enqueueJob($job2);
    static::assertEquals(
      [
        Job::STATE_QUEUED => 2,
        Job::STATE_PROCESSING => 0,
        Job::STATE_SUCCESS => 1,
        Job::STATE_FAILURE => 0,
      ],
      $queue2Backend->countJobs(),
    );

    $queue1Backend->deleteQueue();
    static::assertNull($queue1Backend->claimJob());
  }

  public function testLoadJob(): void {
    $job = Job::create('simple', ['test' => '1']);

    $queue1 = $this->createQueue('arangodb_shared', 'shared');
    $queue1Backend = $queue1->getBackend();
    $queue1Backend->enqueueJob($job);
    $claimedJob = $queue1Backend->claimJob();
    $loadedJob = $queue1Backend->loadJob($claimedJob->getId());
    static::assertEquals($loadedJob, $claimedJob);
  }

  public static function assertQueuedJob(string $expected_queue_id, int $expected_delay, Job $job): void {
    static::assertNotEmpty($job->getId());
    static::assertSame($expected_queue_id, $job->getQueueId());
    static::assertSame(Job::STATE_QUEUED, $job->getState());
    static::assertSame(635814000 + $expected_delay, $job->getAvailableTime());
  }

}
