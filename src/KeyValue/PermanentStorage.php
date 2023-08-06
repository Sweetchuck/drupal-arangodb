<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use Drupal\arangodb\ConnectionTrait;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

class PermanentStorage implements KeyValueStoreInterface {

  /**
   * The name of the collection holding key and value pairs.
   *
   * Not the ArangoDB collection.
   */
  protected string $kvCollection;

  protected SchemaManagerInterface $schemaManager;

  public function getSchemaManager(): SchemaManagerInterface {
    return $this->schemaManager;
  }

  public function setSchemaManager(SchemaManagerInterface $schemaManager): static {
    $this->schemaManager = $schemaManager;

    return $this;
  }

  public function __construct(string $collection) {
    $this->kvCollection = $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->kvCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function has($key) {
    // @todo Implement has() method.
  }

  /**
   * {@inheritdoc}
   */
  public function get($key, $default = NULL) {
    $values = $this->getMultiple([$key]);

    return $values[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array $keys) {
    // @todo Implement getMultiple() method.
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $data) {
    foreach ($data as $key => $value) {
      $this->set($key, $value);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($key) {
    $this->deleteMultiple([$key]);
  }

  /**
   * {@inheritdoc}
   */
  public function getAll() {
    // @todo Implement getAll() method.
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    // @todo Implement set() method.
  }

  /**
   * {@inheritdoc}
   */
  public function setIfNotExists($key, $value) {
    // @todo Implement setIfNotExists() method.
  }

  /**
   * {@inheritdoc}
   */
  public function rename($key, $new_key) {
    // @todo Implement rename() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $keys) {
    // @todo Implement deleteMultiple() method.
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    // @todo Implement deleteAll() method.
  }

}
