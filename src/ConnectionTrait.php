<?php

declare(strict_types = 1);

namespace Drupal\arangodb;

use ArangoDBClient\Collection;
use ArangoDBClient\CollectionHandler;
use ArangoDBClient\Connection;
use ArangoDBClient\ConnectionOptions;
use ArangoDBClient\DocumentHandler;
use ArangoDBClient\UpdatePolicy;

/**
 * @todo Move this into sweetchuck/cache-backend-arangodb.
 */
trait ConnectionTrait {

  protected ConnectionFactory $connectionFactory;

  protected string $connectionName = 'default';

  protected ?Collection $collection = NULL;

  protected ?CollectionHandler $collectionHandler = NULL;

  protected ?DocumentHandler $documentHandler = NULL;

  protected string $uri = '';

  /**
   * Keys are \ArangoDBClient\ConnectionOptions::OPTION_*.
   *
   * @phpstan-var CacheBackendArangoDbConnectionOptions
   *
   * @see \ArangoDBClient\ConnectionOptions
   */
  protected array $connectionOptions = [];

  /**
   * @phpstan-return CacheBackendArangoDbConnectionOptions
   */
  public function getConnectionOptions(): array {
    return $this->connectionOptions;
  }

  /**
   * @phpstan-param CacheBackendArangoDbConnectionOptions $connectionOptions
   */
  public function setConnectionOptions(array $connectionOptions): static {
    $this->connectionOptions = $connectionOptions;
    $this->initUri();

    return $this;
  }

  /**
   * @phpstan-return CacheBackendArangoDbConnectionOptions
   */
  protected function getDefaultConnectionOptions(): array {
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
      ConnectionOptions::OPTION_DATABASE => $this->getCollectionName(),
    ];
  }

  /**
   * @phpstan-return CacheBackendArangoDbConnectionOptions
   */
  protected function getFinalConnectionOptions(): array {
    return $this->getConnectionOptions() + $this->getDefaultConnectionOptions();
  }

  protected ?Connection $connection = NULL;

  public function getConnection(): ?Connection {
    return $this->connection;
  }

  public function setConnection(?Connection $connection): static {
    $this->connection = $connection;
    $this->resetConnection();

    return $this;
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function initConnection(): static {
    if (!$this->connection) {
      $this->connection = $this->connectionFactory->get($this->connectionName);
    }

    if (!$this->collectionHandler) {
      $this->collectionHandler = new CollectionHandler($this->connection);
      // @todo Currently $this->documentConverter->setDocumentClass() can
      // be changed from outside and the $this->collectionHandler->setDocumentClass()
      // won't be updated.
      $this->collectionHandler->setDocumentClass($this->documentConverter->getDocumentClass());
    }

    if (!$this->documentHandler) {
      $this->documentHandler = new DocumentHandler($this->connection);
    }

    return $this;
  }

  protected function initCollection(string $collectionName): static {
    if (!$this->collection) {
      $this->collection = $this->schemaManager->createCollection($this->collectionHandler, $collectionName);
    }

    return $this;
  }

  protected function resetConnection(): static {
    $this->connection = NULL;
    $this->collectionHandler = NULL;
    $this->collection = NULL;
    $this->documentHandler = NULL;

    return $this;
  }

  protected string $collectionNamePattern = '';

  public function getCollectionNamePattern(): string {
    return $this->collectionNamePattern;
  }

  public function setCollectionNamePattern(string $collectionNamePattern): static {
    if ($collectionNamePattern === '') {
      throw new \InvalidArgumentException('ArangoDB collection name pattern can not be empty');
    }

    $this->collectionNamePattern = $collectionNamePattern;

    return $this;
  }

  /**
   * @return array<string, string>
   */
  public function getCollectionNamePlaceholderValues(): array {
    return [];
  }

  public function getCollectionName(): string {
    return strtr(
      $this->getCollectionNamePattern(),
      $this->getCollectionNamePlaceholderValues(),
    );
  }

  protected function initUri(): static {
    $options = $this->getFinalConnectionOptions();
    $this->uri = sprintf(
      '%s/%s#%s',
      $options[ConnectionOptions::OPTION_ENDPOINT] ?? '',
      $options[ConnectionOptions::OPTION_DATABASE] ?? '',
      $this->getCollectionName(),
    );

    return $this;
  }

  protected function isStorageReadable(): bool {
    return $this->collection && $this->collectionHandler;
  }

  protected function isStorageWritable(): bool {
    return $this->collection || $this->documentHandler;
  }

}
