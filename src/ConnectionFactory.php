<?php

declare(strict_types = 1);

namespace Drupal\arangodb;

use ArangoDBClient\Connection as ArangoDbConnection;
use ArangoDBClient\ConnectionOptions as ArangoDbConnectionOptions;
use ArangoDBClient\DefaultValues as ArangoDbDefaultValues;
use Drupal\Core\Site\Settings;

class ConnectionFactory implements ConnectionFactoryInterface {

  /**
   * @var \ArrayAccess<\ArangoDBClient\Connection>
   */
  protected \ArrayAccess $connections;

  protected Settings $settings;

  protected array $parameters = [];

  protected string $connectionName = '';

  public function __construct(
    Settings $settings,
    \ArrayAccess $connections,
    string $connectionName,
    array $parameters
  ) {
    $this->settings = $settings;
    $this->connections = $connections;
    $this->connectionName = $connectionName;
    $this->parameters = $parameters;
  }

  protected function getDefaultAuthConnectionOptions(): array {
    return [
      ArangoDbConnectionOptions::OPTION_AUTH_TYPE => ArangoDbDefaultValues::DEFAULT_AUTH_TYPE,
      ArangoDbConnectionOptions::OPTION_AUTH_USER => NULL,
      ArangoDbConnectionOptions::OPTION_AUTH_PASSWD => NULL,
    ];
  }

  public function get(): ArangoDbConnection {
    if (!isset($this->connections[$this->connectionName])) {
      $auth_options = $this->settings->get(
        "arangodb.connection.options.{$this->connectionName}",
        $this->getDefaultAuthConnectionOptions(),
      );

      $this->connections[$this->connectionName] = new ArangoDbConnection($auth_options + $this->parameters);
    }

    return $this->connections[$this->connectionName];
  }

}
