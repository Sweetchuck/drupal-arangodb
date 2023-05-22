<?php

declare(strict_types = 1);

namespace Drupal\arangodb;

use ArangoDBClient\Connection as ArangoDbConnection;
use Drupal\Core\Site\Settings;

class ConnectionFactory implements ConnectionFactoryInterface {

  /**
   * @var \ArrayAccess<string, \ArangoDBClient\Connection>
   */
  protected \ArrayAccess $connections;

  protected array $connectionOptionsFromParameters = [];

  protected Settings $settings;

  public function __construct(
    \ArrayAccess $connections,
    array $connectionOptions,
    Settings $settings,
  ) {
    $this->connections = $connections;
    $this->connectionOptionsFromParameters = $connectionOptions;
    $this->settings = $settings;
  }

  public function getFinalConnectionOptions(string $name): array {
    // @todo Validate. One of the $connectionOptionsFromSettings or
    // $this->connectionOptionsFromParameters should contain the key.
    //
    // @todo Some keys are depend on the value of the "AuthType".
    // "AuthUser" and "AuthPasswd" only make sens if the "AuthType" is "Basic".
    $connectionOptionsFromSettings = $this->settings->get('arangodb.connection_options', []);

    $final = array_replace(
      $this->connectionOptionsFromParameters['default'] ?? [],
      $connectionOptionsFromSettings['default'] ?? [],
    );

    if ($name !== 'default') {
      $final = array_replace(
        $final,
        $this->connectionOptionsFromParameters[$name] ?? [],
        $connectionOptionsFromSettings[$name] ?? [],
      );
    }

    return array_filter(
      $final,
      function ($value): bool {
        return $value !== NULL;
      },
    );
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  public function get(string $name): ArangoDbConnection {
    if (!isset($this->connections[$name])) {
      $this->connections[$name] = new ArangoDbConnection($this->getFinalConnectionOptions($name));
    }

    return $this->connections[$name];
  }

}
