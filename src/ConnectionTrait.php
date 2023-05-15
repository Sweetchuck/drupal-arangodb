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

  protected ?Collection $collection;

  protected ?CollectionHandler $collectionHandler;

  protected ?DocumentHandler $documentHandler;

  protected string $uri = '';

  /**
   * Keys are \ArangoDBClient\ConnectionOptions::OPTION_*
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
      ConnectionOptions::OPTION_RECONNECT => true,
      ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
      ConnectionOptions::OPTION_CREATE => false,
      ConnectionOptions::OPTION_DATABASE => $this->getCollectionName(),
    ];
  }

  /**
   * @phpstan-return CacheBackendArangoDbConnectionOptions
   */
  protected function getFinalConnectionOptions(): array {
    return $this->getConnectionOptions() + $this->getDefaultConnectionOptions();
  }

  protected ?Connection $connection = null;

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
    $collectionName = $this->getCollectionName();

    if (!$this->connection) {
      $this->connection = new Connection($this->getFinalConnectionOptions());
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
    $this->collectionHandler = null;
    $this->collection = null;
    $this->documentHandler = null;

    return $this;
  }

  // region Property - collectionName
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
  // endregion

  protected function initUri(): static
  {
    $options = $this->getFinalConnectionOptions();
    $this->uri = sprintf(
      '%s/%s#%s',
      $options[ConnectionOptions::OPTION_ENDPOINT] ?? '',
      $options[ConnectionOptions::OPTION_DATABASE] ?? '',
      $this->getCollectionName(),
    );

    return $this;
  }

  protected function isStorageReadable(): bool
  {
    return $this->collection && $this->collectionHandler;
  }

  protected function isStorageWritable(): bool
  {
    return $this->collection || $this->documentHandler;
  }

}