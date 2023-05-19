<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Traits;

use Drupal\arangodb\ConnectionFactory;

/**
 * @property \Drupal\Core\DependencyInjection\ContainerBuilder $container
 */
trait ConnectionTrait {

  protected function createConnectionFactory(): ConnectionFactory {
    return new ConnectionFactory(
      $this->container->get('settings'),
      $this->container->get('arangodb.connection_pool.default'),
      'default',
      $this->getConnectionOptions(),
    );
  }

  protected function getConnectionOptions(): array {
    return [
      'endpoint' => getenv('ARANGODB_CONNECTION_OPTION_ENDPOINT'),
      'AuthUser' => getenv('ARANGODB_CONNECTION_OPTION_AUTHUSER'),
      'AuthPasswd' => getenv('ARANGODB_CONNECTION_OPTION_AUTHPASSWD'),
      'database' => getenv('ARANGODB_CONNECTION_OPTION_DATABASE'),
      'Connection' => 'Close',
      'timeout' => 3,
      'Reconnect' => TRUE,
      'policy' => 'last',
      'createCollection' => FALSE,
    ];
  }

}
