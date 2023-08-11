<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use Drupal\arangodb\ConnectionFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

class StoreSimpleFactory implements KeyValueFactoryInterface {

  protected string $type = 'simple';

  protected ConnectionFactoryInterface $connectionFactory;

  protected string $connectionName = 'default';

  public function getConnectionFactory(): ConnectionFactoryInterface {
    return $this->connectionFactory;
  }

  public function setConnectionFactory(ConnectionFactoryInterface $connectionFactory): static {
    $this->connectionFactory = $connectionFactory;

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

  protected DocumentConverterInterface $documentConverter;

  public function getDocumentConverter(): DocumentConverterInterface {
    return $this->documentConverter;
  }

  public function setDocumentConverter(DocumentConverterInterface $documentConverter): static {
    $this->documentConverter = $documentConverter;

    return $this;
  }

  /**
   * @phpstan-var drupal-arangodb-keyvalue-store-options
   */
  protected array $options = [];

  /**
   * @phpstan-return drupal-arangodb-keyvalue-store-options
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * @phpstan-param drupal-arangodb-keyvalue-store-options $options
   */
  public function setOptions(array $options): static {
    $this->options = $options;

    return $this;
  }

  /**
   * @phpstan-param drupal-arangodb-keyvalue-store-options $options
   */
  public function __construct(
    ConnectionFactoryInterface $connectionFactory,
    string $connectionName,
    SchemaManagerInterface $schemaManager,
    DocumentConverterInterface $documentConverter,
    array $options,
  ) {
    $this->connectionName = $connectionName;

    $this
      ->setConnectionFactory($connectionFactory)
      ->setSchemaManager($schemaManager)
      ->setDocumentConverter($documentConverter)
      ->setOptions($options);
  }

  /**
   * @phpstan-return drupal-arangodb-keyvalue-store-options
   */
  public function getDefaultOptions(): array {
    return [];
  }

  /**
   * @phpstan-return drupal-arangodb-keyvalue-store-options
   */
  public function getFinalOptions(): array {
    return array_replace_recursive(
      $this->getDefaultOptions(),
      $this->getOptions(),
    );
  }

  /**
   * @return \Drupal\arangodb\KeyValue\Store
   */
  public function get($collection): KeyValueStoreInterface {
    $options = $this->getFinalOptions();

    $storage = new Store($collection);
    $storage
      ->setDbCollectionNamePattern($options['collection_name_pattern'] ?? "keyvalue_{$this->type}")
      ->setDbConnection($this->getConnectionFactory()->get($this->connectionName))
      ->setSchemaManager($this->getSchemaManager())
      ->setDocumentConverter($this->getDocumentConverter());

    return $storage;
  }

}
