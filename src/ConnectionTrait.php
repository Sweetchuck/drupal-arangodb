<?php

declare(strict_types = 1);

namespace Drupal\arangodb;

use ArangoDBClient\Collection;
use ArangoDBClient\CollectionHandler;
use ArangoDBClient\Connection;
use ArangoDBClient\ConnectionOptions;
use ArangoDBClient\DocumentHandler;
use ArangoDBClient\UpdatePolicy;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

/**
 * @todo Move this into sweetchuck/cache-backend-arangodb.
 */
trait ConnectionTrait {

  protected ConnectionFactory $dbConnectionFactory;

  protected string $dbConnectionName = 'default';

  protected ?Collection $dbCollection = NULL;

  protected ?CollectionHandler $dbCollectionHandler = NULL;

  protected ?DocumentHandler $dbDocumentHandler = NULL;

  protected ?string $dbCollectionName = NULL;

  protected string $dbUri = '';

  /**
   * Keys are \ArangoDBClient\ConnectionOptions::OPTION_*.
   *
   * @phpstan-var CacheBackendArangoDbConnectionOptions
   *
   * @see \ArangoDBClient\ConnectionOptions
   */
  protected array $dbConnectionOptions = [];

  /**
   * @phpstan-return CacheBackendArangoDbConnectionOptions
   */
  public function getDbConnectionOptions(): array {
    return $this->dbConnectionOptions;
  }

  /**
   * @phpstan-param CacheBackendArangoDbConnectionOptions $dbConnectionOptions
   */
  public function setDbConnectionOptions(array $dbConnectionOptions): static {
    $this->dbConnectionOptions = $dbConnectionOptions;
    $this->initDbUri();

    return $this;
  }

  /**
   * @phpstan-return CacheBackendArangoDbConnectionOptions
   */
  protected function getDefaultDbConnectionOptions(): array {
    return [
      ConnectionOptions::OPTION_ENDPOINT => 'tcp://127.0.0.1:8529',
      ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
      ConnectionOptions::OPTION_AUTH_USER => 'root',
      ConnectionOptions::OPTION_AUTH_PASSWD => '',
      ConnectionOptions::OPTION_CONNECTION => 'Close',
      ConnectionOptions::OPTION_CONNECT_TIMEOUT => 3,
      ConnectionOptions::OPTION_RECONNECT => TRUE,
      ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
      ConnectionOptions::OPTION_CREATE => FALSE,
      // @todo This is not a good default value.
      ConnectionOptions::OPTION_DATABASE => $this->getDbCollectionName(),
    ];
  }

  /**
   * @phpstan-return CacheBackendArangoDbConnectionOptions
   */
  protected function getFinalDbConnectionOptions(): array {
    return $this->getDbConnectionOptions() + $this->getDefaultDbConnectionOptions();
  }

  protected ?Connection $dbConnection = NULL;

  public function getDbConnection(): ?Connection {
    return $this->dbConnection;
  }

  public function setDbConnection(?Connection $dbConnection): static {
    $this->resetDbConnection();
    $this->dbConnection = $dbConnection;

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

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function initDbConnection(): static {
    if (!$this->dbConnection) {
      $this->dbConnection = $this->dbConnectionFactory->get($this->dbConnectionName);
    }

    if (!$this->dbCollectionHandler) {
      $this->dbCollectionHandler = new CollectionHandler($this->dbConnection);
      // @todo Currently $this->documentConverter->setDocumentClass() can
      // be changed from outside and the $this->collectionHandler->setDocumentClass()
      // won't be updated.
      $this->dbCollectionHandler->setDocumentClass($this->documentConverter->getDocumentClass());
    }

    if (!$this->dbDocumentHandler) {
      $this->dbDocumentHandler = new DocumentHandler($this->dbConnection);
    }

    return $this;
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function initDbCollection(string $collectionName): static {
    if (!$this->dbCollection) {
      $this->dbCollection = $this
        ->getSchemaManager()
        ->createCollection($this->dbCollectionHandler, $collectionName);
    }

    return $this;
  }

  protected function resetDbConnection(): static {
    $this->dbConnection = NULL;
    $this->dbCollectionHandler = NULL;
    $this->dbCollection = NULL;
    $this->dbDocumentHandler = NULL;

    return $this;
  }

  protected string $dbCollectionNamePattern = '';

  public function getDbCollectionNamePattern(): string {
    return $this->dbCollectionNamePattern;
  }

  public function setDbCollectionNamePattern(string $collectionNamePattern): static {
    if ($collectionNamePattern === '') {
      throw new \InvalidArgumentException('ArangoDB collection name pattern can not be empty');
    }

    $this->dbCollectionNamePattern = $collectionNamePattern;

    return $this;
  }

  /**
   * @return array<string, string>
   */
  public function getDbCollectionNamePlaceholderValues(): array {
    return [];
  }

  public function getDbCollectionName(): string {
    if ($this->dbCollectionName === NULL) {
      $this->dbCollectionName = strtr(
        $this->getDbCollectionNamePattern(),
        $this->getDbCollectionNamePlaceholderValues(),
      );
    }

    return $this->dbCollectionName;
  }

  protected function initDbUri(): static {
    $options = $this->getFinalDbConnectionOptions();
    $this->dbUri = sprintf(
      '%s/%s#%s',
      $options[ConnectionOptions::OPTION_ENDPOINT] ?? '',
      $options[ConnectionOptions::OPTION_DATABASE] ?? '',
      $this->getDbCollectionName(),
    );

    return $this;
  }

  protected function isStorageReadable(): bool {
    return $this->dbCollection && $this->dbCollectionHandler;
  }

  protected function isStorageWritable(): bool {
    return $this->dbCollection || $this->dbDocumentHandler;
  }

}
