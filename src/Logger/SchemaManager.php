<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Logger;

use ArangoDBClient\CollectionHandler;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManager as SchemaManagerBase;

class SchemaManager extends SchemaManagerBase {

  /**
   * {@inheritdoc}
   */
  protected array $indexDefinitions = [
    'idx_channel' => [
      'name' => 'idx_channel',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['channel'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
    'idx_timestamp' => [
      'name' => 'idx_timestamp',
      CollectionHandler::OPTION_TYPE => CollectionHandler::OPTION_PERSISTENT_INDEX,
      CollectionHandler::OPTION_FIELDS => ['timestamp'],
      CollectionHandler::OPTION_UNIQUE => FALSE,
    ],
  ];

}
