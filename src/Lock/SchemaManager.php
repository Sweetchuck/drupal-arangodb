<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Lock;

use ArangoDBClient\CollectionHandler;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManager as SchemaManagerBase;

class SchemaManager extends SchemaManagerBase {

  /**
   * {@inheritdoc}
   */
  protected array $indexDefinitions = [
    'idx_lockId' => [
      'name' => 'idx_lockId',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['lockId'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_name' => [
      'name' => 'idx_name',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['name'],
      CollectionHandler::OPTION_UNIQUE => TRUE,
    ],
    'idx_expire' => [
      'name' => 'idx_expire',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['expire'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
  ];

}
