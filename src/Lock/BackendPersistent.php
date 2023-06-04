<?php

declare(strict_types=1);


namespace Drupal\arangodb\Lock;

use Drupal\arangodb\ConnectionFactory;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

class BackendPersistent extends Backend {

  public function __construct(
    ConnectionFactory $connectionFactory,
    string $connectionName,
    SchemaManagerInterface $schemaManager,
    DocumentConverterInterface $documentConverter,
    array $options,
  ) {
    $this->lockId = 'persistent';
    parent::__construct(
      $connectionFactory,
      $connectionName,
      $schemaManager,
      $documentConverter,
      $options,
    );
  }

}
