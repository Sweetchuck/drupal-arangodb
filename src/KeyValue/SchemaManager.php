<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use ArangoDBClient\CollectionHandler;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManager as SchemaManagerBase;

class SchemaManager extends SchemaManagerBase {

  /**
   * {@inheritdoc}
   */
  protected array $indexDefinitions = [
    'idx_collection_key' => [
      'name' => 'idx_collection_key',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_HASH_INDEX,
      CollectionHandler::OPTION_FIELDS => ['collection', 'key'],
      CollectionHandler::OPTION_UNIQUE => TRUE,
    ],
    'idx_expire' => [
      'name' => 'idx_expire',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_TTL_INDEX,
      CollectionHandler::OPTION_FIELDS => ['expire'],
      CollectionHandler::OPTION_EXPIRE_AFTER => 0,
    ],
  ];

}
