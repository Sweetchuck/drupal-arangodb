<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drush\Commands\DrushCommands;
use Robo\Common\BuilderAwareTrait;
use Robo\Contract\BuilderAwareInterface;

class DevelHelperQueueCoreCommands extends DrushCommands implements BuilderAwareInterface {

  use BuilderAwareTrait;

  protected QueueFactory $queryFactory;

  public function __construct(QueueFactory $queryFactory) {
    $this->queryFactory = $queryFactory;
    parent::__construct();
  }

  /**
   * Creates a queue without adding any item into the queue.
   *
   * @param string $queueName
   *   The name of the queue to create.
   *
   * @command arangodb:queue-core:create-queue
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdCreateQueueExecute(string $queueName): void {
    $this
      ->getQueueHandler($queueName)
      ->createQueue();
  }

  /**
   * Get number of items in the given queue.
   *
   * @param string $queueName
   *   The name of the queue.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:queue-core:number-of-items
   *
   * @option string $format
   *   Default: yaml
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdNumberOfItemsExecute(
    string $queueName,
    array $options = [
      'format' => 'yaml',
    ]
  ): CommandResult {
    $exitCode = 0;
    $data = [
      'number_of_items' => $this->getQueueHandler($queueName)->numberOfItems(),
    ];

    return CommandResult::dataWithExitCode($data, $exitCode);
  }

  /**
   * Claim an item from a queue.
   *
   * @param string $queueName
   *   The name of the queue.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:queue-core:claim-item
   *
   * @option string $format
   *   Default: yaml
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdClaimItemExecute(
    string $queueName,
    array $options = [
      'format' => 'yaml',
    ]
  ): CommandResult {
    $exitCode = 0;
    $data = [
      'item' => (array) $this->getQueueHandler($queueName)->claimItem(),
    ];

    return CommandResult::dataWithExitCode($data, $exitCode);
  }

  /**
   * Release a queue item.
   *
   * @param string $queueName
   *   The name of the queue which contains the item.
   * @param string $itemId
   *   Queue item ID.
   *
   * @command arangodb:queue-core:release-item
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdReleaseItemExecute(
    string $queueName,
    string $itemId,
  ): void {
    $item = (object) [
      'item_id' => $itemId,
    ];
    $this
      ->getQueueHandler($queueName)
      ->releaseItem($item);
  }

  /**
   * Deletes the queue with the given name.
   *
   * @param string $queueName
   *   The name of the queue to delete.
   *
   * @command arangodb:queue-core:delete-queue
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdDeleteQueueExecute(string $queueName): void {
    $this
      ->getQueueHandler($queueName)
      ->deleteQueue();
  }

  /**
   * Add item to a queue.
   *
   * @param string $queueName
   *   The name of the queue to add teh item to.
   * @param string $data
   *   Item data.
   *
   * @command arangodb:queue-core:create-item
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdCreateItemExecute(
    string $queueName,
    string $data = '',
  ): void {
    if ($data === '') {
      $data = date('Y-m-d H:i:s');
    }

    $itemId = $this
      ->getQueueHandler($queueName)
      ->createItem($data);
    var_dump($itemId);
  }

  /**
   * Delete as item from the queue.
   *
   * @param string $queueName
   *   The name of the queue.
   * @param string $itemId
   *   Item ID.
   *
   * @command arangodb:queue-core:delete-item
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdDeleteItemExecute(
    string $queueName,
    string $itemId = '',
  ): void {
    $item = (object) ['item_id' => $itemId];
    $this
      ->getQueueHandler($queueName)
      ->deleteItem($item);
  }

  protected function getQueueHandler(string $queueName): QueueInterface {
    return $this->queryFactory->get($queueName, TRUE);
  }

}
