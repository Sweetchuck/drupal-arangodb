<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use Drupal\arangodb\ConnectionFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

class StoreExpirableFactory extends StoreSimpleFactory implements KeyValueExpirableFactoryInterface {

  protected string $type = 'expirable';

  protected ?TimeInterface $time = NULL;

  public function getTime(): ?TimeInterface {
    return $this->time;
  }

  public function setTime(?TimeInterface $time): static {
    $this->time = $time;

    return $this;
  }

  public function __construct(
    ConnectionFactoryInterface $connectionFactory,
    string $connectionName,
    SchemaManagerInterface $schemaManager,
    DocumentConverterInterface $documentConverter,
    array $options,
    ?TimeInterface $time = NULL,
  ) {
    $this->setTime($time);

    parent::__construct(
      $connectionFactory,
      $connectionName,
      $schemaManager,
      $documentConverter,
      $options,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function get($collection): KeyValueStoreInterface {
    $store = parent::get($collection);
    $store->setTime($this->getTime());

    return $store;
  }

}
