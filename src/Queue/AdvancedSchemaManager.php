<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use ArangoDBClient\CollectionHandler;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManager as SchemaManagerBase;

class AdvancedSchemaManager extends SchemaManagerBase {

  /**
   * {@inheritdoc}
   */
  protected array $indexDefinitions = [
    'idx_queueId' => [
      'name' => 'idx_queueId',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_HASH_INDEX,
      CollectionHandler::OPTION_FIELDS => ['queueId'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_state' => [
      'name' => 'idx_state',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['state'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_available' => [
      'name' => 'idx_available',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['available'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_expires' => [
      'name' => 'idx_expires',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['expires'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
  ];

}
