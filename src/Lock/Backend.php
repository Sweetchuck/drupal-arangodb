<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Lock;

use ArangoDBClient\Document;
use ArangoDBClient\Statement;
use Drupal\arangodb\ConnectionFactory;
use Drupal\arangodb\ConnectionTrait;
use Drupal\Core\Lock\LockBackendAbstract;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

class Backend extends LockBackendAbstract {

  use ConnectionTrait;

  /**
   * Existing locks for this page.
   *
   * @var array<string, Document>
   */
  protected $locks = [];

  protected int|float $minTimeout = 0.001;

  /**
   * @phpstan-var drupal-arangodb-lock-options
   */
  protected array $options;

  protected DocumentConverterInterface $documentConverter;

  /**
   * @phpstan-param drupal-arangodb-lock-options $options
   */
  public function __construct(
    ConnectionFactory $connectionFactory,
    string $connectionName,
    SchemaManagerInterface $schemaManager,
    DocumentConverterInterface $documentConverter,
    array $options,
  ) {
    $this->dbConnectionFactory = $connectionFactory;
    $this->dbConnectionName = $connectionName;
    $this->schemaManager = $schemaManager;
    $this->documentConverter = $documentConverter;
    $this->options = $options;

    $this->dbCollectionNamePattern = $options['collectionNamePattern'] ?? 'lock';
    $this->minTimeout = max($options['minTimeout'] ?? 0.001, 0.001);
  }

  protected function getDbCollectionName(): string {
    return $this->dbCollectionNamePattern;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-return bool
   *
   * @throws \ArangoDBClient\Exception
   */
  public function acquire($name, $timeout = 30.0) {
    $name = $this->normalizeName($name);

    assert(
      $timeout >= $this->minTimeout,
      "\$timeout($timeout) has to be >= than {$this->minTimeout}",
    );

    $expire = $this->getNow() + max($timeout, $this->minTimeout);

    $this
      ->initDbConnection()
      ->initDbCollection($this->getDbCollectionName());

    if (isset($this->locks[$name])) {
      $success = $this->acquireUpdate($name, $expire);
      if (!$success) {
        unset($this->locks[$name]);
      }

      return $success;
    }

    try {
      $this->locks[$name] = $this->acquireInsert($name, $expire);
    }
    catch (\Exception $e) {
      if ($this->lockMayBeAvailable($name)) {
        $this->locks[$name] = $this->acquireInsert($name, $expire);
      }
    }

    return isset($this->locks[$name]);
  }

  /**
   * @throws \ArangoDBClient\ClientException
   * @throws \ArangoDBClient\Exception
   */
  protected function acquireInsert(string $name, int|float $expire): Document {
    $class = $this->documentConverter->getDocumentClass();
    /** @var \ArangoDBClient\Document $document */
    $document = new $class();
    $document->set('lockId', $this->getLockId());
    $document->set('name', $name);
    $document->set('expire', $expire);

    $this
      ->dbDocumentHandler
      ->insert($this->getDbCollectionName(), $document);

    return $document;
  }

  /**
   * @throws \ArangoDBClient\ClientException
   */
  protected function acquireUpdate(string $name, int|float $expire): bool {
    // Try to extend the expiration of a lock we already acquired.
    $this->locks[$name]->set('expire', $expire);
    try {
      return $this->dbDocumentHandler->update($this->locks[$name]);
    }
    catch (\Exception) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function lockMayBeAvailable($name) {
    $this
      ->initDbConnection()
      ->initDbCollection($this->getDbCollectionName());

    $name = $this->normalizeName($name);
    $document = $this->load($name);
    if (!$document) {
      return TRUE;
    }

    return $document->get('expire') < $this->getNow()
      && $this->cleanup($document);
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function cleanup(Document $document): bool {
    // We check two conditions to prevent a race condition where another
    // request acquired the lock and set a new expire time. We add a small
    // number to $expire to avoid errors with float to string conversion.
    $query = <<< AQL
    FOR doc IN @@collection
      FILTER
        doc.name == @name
        AND
        doc.expire <= @expire
      REMOVE doc IN @@collection
    AQL;

    $statement = new Statement(
      $this->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => $this->getDbCollectionName(),
          'name' => $document->get('name'),
          'expire' => $document->get('expire') + 0.0001,
        ],
      ],
    );
    $result = $statement->execute();

    return $result->getCount() > 0;
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function release($name) {
    $collectionName = $this->getDbCollectionName();
    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    $name = $this->normalizeName($name);
    isset($this->locks[$name]) ?
      $this->releaseByDocument($name)
      : $this->releaseByName($name);
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *
   * @throws \ArangoDBClient\Exception
   */
  public function releaseAll($lockId = NULL) {
    $collectionName = $this->getDbCollectionName();
    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    $currentLockId = $this->getLockId();
    if (!$lockId) {
      $lockId = $currentLockId;
    }

    if ($lockId === $currentLockId) {
      $this->locks = [];
    }

    $this->releaseByLockId($lockId);
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function load(string $name): ?Document {
    $query = <<< AQL
      FOR doc IN @@collection
        FILTER
          doc.name == @name
        RETURN doc
    AQL;

    $statement = new Statement(
      $this->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => $this->getDbCollectionName(),
          'name' => $name,
        ],
      ],
    );
    $statement->setDocumentClass($this->documentConverter->getDocumentClass());
    $result = $statement->execute();

    $result->rewind();
    if (!$result->valid()) {
      return NULL;
    }

    /** @var \ArangoDBClient\Document $document */
    $document = $result->current();

    return $document;
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function releaseByDocument(string $name): void {
    $this
      ->dbDocumentHandler
      ->remove($this->locks[$name]);

    unset($this->locks[$name]);
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function releaseByName(string $name): void {
    $query = <<< AQL
    FOR doc in @@collection
      FILTER
        doc.lockId == @lockId
        AND
        doc.name == @name
      REMOVE doc IN @@collection
    AQL;

    $statement = new Statement(
      $this->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => $this->getDbCollectionName(),
          'lockId' => $this->getLockId(),
          'name' => $name,
        ],
      ],
    );

    $statement->execute();
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function releaseByLockId(string $lockId): void {
    $query = <<< AQL
    FOR doc in @@collection
      FILTER
        doc.lockId == @lockId
      REMOVE doc IN @@collection
    AQL;

    $statement = new Statement(
      $this->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => $this->getDbCollectionName(),
          'lockId' => $lockId,
        ],
      ],
    );

    $statement->execute();
  }

  protected function normalizeName(string $name): string {
    return $name;
  }

  protected function getNow(): float {
    // @todo Why not @datetime.time::getCurrentMicroTime()?
    return microtime(TRUE);
  }

}
