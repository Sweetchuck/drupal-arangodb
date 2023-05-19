<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use ArangoDBClient\CollectionHandler;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManager as SchemaManagerBase;

class CoreSchemaManager extends SchemaManagerBase {

  /**
   * {@inheritdoc}
   */
  protected array $indexDefinitions = [
    'idx_queue' => [
      'name' => 'idx_queue',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_HASH_INDEX,
      CollectionHandler::OPTION_FIELDS => ['queue'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_created' => [
      'name' => 'idx_created',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['created'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
  ];

}
