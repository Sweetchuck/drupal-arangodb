<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use ArangoDBClient\Cursor;
use ArangoDBClient\Exception as ArangoDBException;
use ArangoDBClient\ServerException as ArangoDBServerException;
use ArangoDBClient\Statement;
use Drupal\arangodb\ConnectionTrait;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\DelayableQueueInterface;
use Drupal\Core\Queue\QueueGarbageCollectionInterface;
use Drupal\Core\Queue\ReliableQueueInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

/**
 * Queue item storage handler.
 *
 * Class name has to contains "Arangodb" in order to make it compatible with
 * the `queue_ui` module. But it is just the first step, unfortunately there
 * are several other blocker issues as well in the QueueUI API.
 * So, it won't be supported.
 *
 * @see \Drupal\queue_ui\QueueUIManager::queueClassName
 * @see \Drupal\queue_ui\Form\ItemDetailForm::buildForm
 * @see \Drupal\queue_ui\QueueUIInterface::loadItem
 * @see \Drupal\queue_ui\QueueUIInterface::releaseItem
 * @see \Drupal\queue_ui\QueueUIInterface::deleteItem
 */
class CoreStorage implements
  ReliableQueueInterface,
  QueueGarbageCollectionInterface,
  DelayableQueueInterface,
  LoggerAwareInterface {

  use LoggerAwareTrait;
  use ConnectionTrait;

  protected string $queueName = '';

  public function getQueueName(): string {
    return $this->queueName;
  }

  public function setQueueName(string $queueName): static {
    // @todo Validate $queueName is for table name.
    $this->queueName = $queueName;

    return $this;
  }

  protected CoreDocumentConverterInterface $documentConverter;

  public function getDocumentConverter(): CoreDocumentConverterInterface {
    return $this->documentConverter;
  }

  public function setDocumentConverter(CoreDocumentConverterInterface $documentConverter): static {
    $this->documentConverter = $documentConverter;

    return $this;
  }

  protected SchemaManagerInterface $schemaManager;

  public function getSchemaManager(): SchemaManagerInterface {
    return $this->schemaManager;
  }

  public function setSchemaManager(SchemaManagerInterface $schemaManager): static {
    $this->schemaManager = $schemaManager;

    return $this;
  }

  protected TimeInterface $time;

  public function getTime(): TimeInterface {
    return $this->time;
  }

  public function setTime(TimeInterface $time): static {
    $this->time = $time;

    return $this;
  }

  public function getLogger(): ?LoggerInterface {
    return $this->logger;
  }

  public function __construct(string $queueName) {
    $this
      ->setCollectionNamePattern('queue')
      ->setQueueName($queueName);
  }

  public function getCollectionNamePlaceholderValues(): array {
    return [
      '{{ queue.name }}' => $this->getQueueName(),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *   When the item can not be created.
   */
  public function createItem($data) {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

    if (!$this->isStorageWritable()) {
      throw new \Exception();
    }

    try {
      $document = $this
        ->getDocumentConverter()
        ->dataToDocument($this->getQueueName(), $data);
      $this
        ->documentHandler
        ->insert($this->collection, $document);

      return $document->getInternalKey();
    }
    catch (\Exception $e) {
      // @todo Error log.
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function numberOfItems() {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

    if (!$this->isStorageReadable()) {
      return 0;
    }

    $query = <<< AQL
      FOR doc IN @@collection
        FILTER STARTS_WITH(doc.queue, @queueName)
        COLLECT WITH COUNT INTO length
        RETURN length
    AQL;

    $result = $this->executeStatement(
      $query,
      [
        '@collection' => $this->getCollectionName(),
        'queueName' => $this->getQueueName(),
      ],
    );

    return $result->current();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function claimItem($lease_time = 3600) {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

    $collectionName = $this->getCollectionName();
    $queueName = $this->getQueueName();

    $query = <<< AQL
      FOR doc IN @@collection
        FILTER
          doc.queue == @queueName
          AND
          doc.expire == 0
        SORT
            doc.created,
            doc._key
        LIMIT 0, 1
        RETURN doc
    AQL;

    // Claim an item by updating its expire fields. If claim is not successful
    // another thread may have claimed the item in the meantime. Therefore loop
    // until an item is successfully claimed or we are reasonably sure there
    // are no unclaimed items left.
    while (TRUE) {
      $result = $this->executeStatement(
        $query,
        [
          '@collection' => $collectionName,
          'queueName' => $queueName,
        ],
      );

      $result->rewind();
      if (!$result->valid()) {
        return FALSE;
      }

      /** @var \ArangoDBClient\Document $document */
      $document = $result->current();
      if (!$document) {
        return FALSE;
      }

      $item = $this
        ->getDocumentConverter()
        ->documentToItem($document);

      // Try to update the item. Only one thread can succeed in UPDATEing the
      // same row. We cannot rely on REQUEST_TIME because items might be
      // claimed by a single consumer which runs longer than 1 second. If we
      // continue to use REQUEST_TIME instead of the current time(), we steal
      // time from the lease, and will tend to reset items before the lease
      // should really expire.
      $document->set('expire', $this->getTime()->getCurrentTime() + $lease_time);
      try {
        $this->documentHandler->update($document);

        return $item;
      }
      catch (ArangoDBException $e) {
        // Continue.
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteItem($item) {
    $collectionName = $this->getCollectionName();
    $this
      ->initConnection()
      ->initCollection($collectionName);

    try {
      $this->documentHandler->removeById($collectionName, $item->item_id);
    }
    catch (ArangoDBServerException $e) {
      $details = $e->getDetails();
      if (isset($details['code']) && $details['code'] === 404) {
        // The "errorNum" is int(1202).
        return;
      }

      throw $e;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function releaseItem($item) {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

    $query = <<< AQL
      UPDATE {
        _key: @key,
        expire: 0
      }
      IN @@collection
    AQL;

    try {
      $this->executeStatement(
        $query,
        [
          '@collection' => $this->getCollectionName(),
          'key' => $item->item_id,
        ],
      );
    }
    catch (ArangoDBException $e) {
      // @todo Error log.
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function createQueue() {
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteQueue() {
    $this->initConnection();

    $collectionName = $this->getCollectionName();
    if (!$this->collectionHandler->has($collectionName)) {
      // Nothing to delete.
      return;
    }

    if (!$this->isShared()) {
      // Collection is dedicated only for this $queueName.
      $this->collectionHandler->drop($collectionName);

      return;
    }

    // Collection can not be deleted, because the storage is shared,
    // and maybe other process has had already called the ::createQueue()
    // with a different $queueName,
    // but has not added any items to queue yet.
    $this->deleteItems();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function garbageCollection() {
    // @todo Do not create the collection if it is not exists,
    // just skip the garbage collection.
    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

    try {
      $this
        ->gcExpiredBatch()
        ->gcExpiredItems();
    }
    catch (ArangoDBException $e) {
      // @todo Error log.
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function delayItem($item, int $delay) {
    if ($delay < 0) {
      throw new \InvalidArgumentException('$delay must be non-negative');
    }

    $this
      ->initConnection()
      ->initCollection($this->getCollectionName());

    $query = <<< AQL
      UPDATE {
        _key: @key,
        expire: @expire
      }
      IN @@collection
    AQL;

    try {
      $this->executeStatement(
        $query,
        [
          '@collection' => $this->getCollectionName(),
          'key' => $item->item_id,
          'expire' => $this->getTime()->getCurrentTime() + $delay,
        ],
      );

      return TRUE;
    }
    catch (ArangoDBException $e) {
      // @todo Error log.
    }

    return FALSE;
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function deleteItems(): static {
    $query = <<< AQL
      FOR doc IN @@collection
        FILTER
          doc.queueName == @queueName
        REMOVE doc._key IN @@collection
    AQL;

    $this->executeStatement(
      $query,
      [
        '@collection' => $this->getCollectionName(),
        'queueName' => $this->getQueueName(),
      ],
    );

    $this
      ->getLogger()
      ?->info(
        'All items have been deleted from queue %queue',
        [
          '%queue' => $this->getQueueName(),
        ],
      );

    return $this;
  }

  /**
   * @param array<string, mixed> $bindVars
   *
   * @throws \ArangoDBClient\Exception
   */
  protected function executeStatement(string $query, array $bindVars): Cursor {
    $statement = new Statement(
      $this->connection,
      [
        'query' => $query,
        'bindVars' => $bindVars,
      ] + $this->getExecuteStatementData(),
    );

    $statement->setDocumentClass($this->documentConverter->getDocumentClass());

    return $statement->execute();
  }

  /**
   * @phpstan-return cache-backend-arangodb-execute-statement-data
   * @psalm-return ExecuteStatementData
   */
  protected function getExecuteStatementData(): array {
    return [
      'batchSize' => 1000,
      'sanitize' => TRUE,
    ];
  }

  /**
   * Is this storage (ArangoDB collection) handles more than one queue?
   */
  protected function isShared(): bool {
    return !str_contains(
      $this->getCollectionNamePattern(),
      '{{ queue.name }}',
    );
  }

  protected function getBatchTimeout(): int {
    // @todo Make this configurable.
    return 86400;
  }

  /**
   * Clean up the queue for failed batches.
   *
   * @throws \ArangoDBClient\Exception
   */
  protected function gcExpiredBatch(): static {
    $query = <<< AQL
      FOR doc IN @@collection
        FILTER
          STARTS_WITH(doc.queue, @queueNamePrefix)
          AND
          doc.created < @created
        REMOVE doc IN @@collection
    AQL;

    $this->executeStatement(
      $query,
      [
        '@collection' => $this->getCollectionName(),
        'queueNamePrefix' => 'drupal_batch:',
        'created' => $this->getTime()->getRequestTime() - $this->getBatchTimeout(),
      ],
    );

    return $this;
  }

  protected function gcExpiredItems(): static {
    $query = <<< AQL
      FOR doc IN @@collection
        FILTER
          doc.queue == @queueName
          AND
          doc.expire != 0
          AND
          doc.expire < @expire
        REMOVE doc IN @@collection
    AQL;

    try {
      $this->executeStatement(
        $query,
        [
          '@collection' => $this->getCollectionName(),
          'queueName' => $this->getQueueName(),
          'expire' => $this->getTime()->getRequestTime(),
        ],
      );
    }
    catch (ArangoDBException $e) {
      // @todo Error log.
    }

    return $this;
  }

}
