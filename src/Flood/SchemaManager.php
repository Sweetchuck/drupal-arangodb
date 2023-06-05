<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Flood;

use ArangoDBClient\CollectionHandler;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManager as SchemaManagerBase;

class SchemaManager extends SchemaManagerBase {

  /**
   * {@inheritdoc}
   */
  protected array $indexDefinitions = [
    'idx_identifier_name' => [
      'name' => 'idx_event',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => [
        'identifier',
        'name',
      ],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_created' => [
      'name' => 'idx_created',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['created'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_expiration' => [
      'name' => 'idx_expiration',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['expiration'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
  ];

}
