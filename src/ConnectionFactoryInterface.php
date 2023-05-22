<?php

declare(strict_types = 1);

namespace Drupal\arangodb;

use ArangoDBClient\Connection as ArangoDbConnection;

interface ConnectionFactoryInterface {

  public function get(string $name): ArangoDbConnection;

}
