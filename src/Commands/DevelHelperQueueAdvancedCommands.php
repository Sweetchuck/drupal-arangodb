<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\advancedqueue\Job;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Robo\Common\BuilderAwareTrait;
use Robo\Contract\BuilderAwareInterface;

class DevelHelperQueueAdvancedCommands extends DrushCommands implements BuilderAwareInterface {

  use BuilderAwareTrait;

  protected EntityTypeManagerInterface $etm;

  protected ConfigEntityStorageInterface $queueStorage;

  public function __construct(EntityTypeManagerInterface $etm) {
    parent::__construct();

    $this->etm = $etm;
    $this->queueStorage = $etm->getStorage('advancedqueue_queue');
  }

  /**
   * @command arangodb:queue-advanced:create-queue
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdCreateQueueExecute(string $queueId): void {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->queueStorage->load($queueId);
    $queue_backend = $queue->getBackend();
    $queue_backend->createQueue();
  }

  /**
   * @command arangodb:queue-advanced:delete-queue
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdDeleteQueueExecute(string $queueId): void {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->queueStorage->load($queueId);
    $queue_backend = $queue->getBackend();
    $queue_backend->deleteQueue();
  }

  /**
   * @command arangodb:queue-advanced:enqueue-jobs
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdEnqueueJobsExecute(string $queueId): void {
    $jobs = [
      Job::create(
        'flexible',
        [
          'expected_state' => Job::STATE_SUCCESS,
          'expected_message' => 'Everything OK',
        ],
      ),
      Job::create(
        'flexible',
        [
          'expected_state' => Job::STATE_SUCCESS,
          'expected_message' => 'Everything OK',
        ],
      ),
    ];

    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->queueStorage->load($queueId);
    $queue_backend = $queue->getBackend();
    $queue_backend->enqueueJobs($jobs);
  }

  /**
   * Count jobs.
   *
   * @param string $queueId
   *   Queue ID.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:queue-advanced:count-jobs
   *
   * @option string $format
   *   Default: yaml
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdCountJobsExecute(
    string $queueId,
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->queueStorage->load($queueId);
    $queue_backend = $queue->getBackend();

    return CommandResult::data($queue_backend->countJobs());
  }

  /**
   * Claim job.
   *
   * @param string $queueId
   *   Queue ID.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:queue-advanced:claim-job
   *
   * @option string $format
   *   Default: yaml
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdClaimJobExecute(
    string $queueId,
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->queueStorage->load($queueId);
    $queue_backend = $queue->getBackend();

    return CommandResult::data($queue_backend->claimJob()?->toArray());
  }

  /**
   * @command arangodb:queue-advanced:clear
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdClearExecute(string $queueId): void {
    /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue */
    $queue = $this->queueStorage->load($queueId);
    $queue_backend = $queue->getBackend();
    $queue_backend->cleanupQueue();
  }

}
