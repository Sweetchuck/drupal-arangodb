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

  /**
   * @phpstan-var array<string, array<string, mixed>>
   */
  protected array $connectionOptionsFromParameters = [];

  protected Settings $settings;

  /**
   * @phpstan-param \ArrayAccess<string, \ArangoDBClient\Connection> $connections
   * @phpstan-param array<string, array<string, mixed>> $connectionOptions
   */
  public function __construct(
    \ArrayAccess $connections,
    array $connectionOptions,
    Settings $settings,
  ) {
    $this->connections = $connections;
    $this->connectionOptionsFromParameters = $connectionOptions;
    $this->settings = $settings;
  }

  /**
   * @phpstan-return array<string, mixed>
   */
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

  public function getConnectionNames(): array {
    $set1 = array_keys($this->settings->get('arangodb.connection_options', []));
    $set2 = array_keys($this->connectionOptionsFromParameters);
    /** @var string[] $names */
    $names = array_unique(array_merge($set1, $set2));
    sort($names);

    return array_combine($names, $names);
  }

}
