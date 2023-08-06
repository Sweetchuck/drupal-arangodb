<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use Drupal\arangodb\ConnectionFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

class PermanentFactory implements KeyValueFactoryInterface {


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

  public function __construct(
    ConnectionFactoryInterface $connectionFactory,
    string $connectionName,
    SchemaManagerInterface $schemaManager,
    array $options,
  ) {
    $this->connectionName = $connectionName;

    $this
      ->setConnectionFactory($connectionFactory)
      ->setSchemaManager($schemaManager)
      ->setOptions($options);
  }

  protected array $options = [];

  public function getOptions(): array {
    return $this->options;
  }

  public function setOptions(array $options): static {
    $this->options = $options;

    return $this;
  }

  public function getDefaultOptions(): array {
    return [];
  }

  public function getFinalOptions(): array {
    return array_replace_recursive(
      $this->getDefaultOptions(),
      $this->getOptions(),
    );
  }

  public function get($collection): KeyValueStoreInterface {
    $options = $this->getFinalOptions();

    $storage = new PermanentStorage($collection);
    $storage
      ->setCollectionNamePattern($options['collection_name_pattern'])
      ->setConnection($this->getConnectionFactory()->get($this->connectionName))
      ->setSchemaManager($this->getSchemaManager());

    return $storage;
  }

}
