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

  /**
   * @throws \ArangoDBClient\Exception
   */
  public function get(): ArangoDbConnection {
    if (!isset($this->connections[$this->connectionName])) {
      // @todo Some keys are depend on the value of the "AuthType".
      // "AuthUser" and "AuthPasswd" only make sens if the "AuthType" is "Basic".
      $options = array_replace(
        $this->getDefaultAuthConnectionOptions(),
        $this->parameters,
        $this->settings->get("arangodb.connection.options.{$this->connectionName}", []),
      );

      $this->connections[$this->connectionName] = new ArangoDbConnection($options);
    }

    return $this->connections[$this->connectionName];
  }

}
