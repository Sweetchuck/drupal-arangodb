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

  protected string $connectionName = '';

  public function getConnectionName(): string {
    return $this->connectionName;
  }

  protected array $connectionOptions = [];

  public function getConnectionOptions(): array {
    return $this->connectionOptions;
  }

  public function setConnectionOptions(array $connectionOptions): static {
    $this->connectionOptions = $connectionOptions;

    return $this;
  }

  public function getFinalConnectionOptions(): array {
    // @todo Some keys are depend on the value of the "AuthType".
    // "AuthUser" and "AuthPasswd" only make sens if the "AuthType" is "Basic".
    return array_replace(
      $this->getDefaultAuthConnectionOptions(),
      $this->getConnectionOptions(),
      $this->settings->get("arangodb.connection.options.{$this->connectionName}", []),
    );
  }

  public function __construct(
    Settings $settings,
    \ArrayAccess $connections,
    string $connectionName,
    array $connectionOptions,
  ) {
    $this->settings = $settings;
    $this->connections = $connections;
    $this->connectionName = $connectionName;
    $this->connectionOptions = $connectionOptions;
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
      $options = $this->getFinalConnectionOptions();
      $this->connections[$this->connectionName] = new ArangoDbConnection($options);
    }

    return $this->connections[$this->connectionName];
  }

}
