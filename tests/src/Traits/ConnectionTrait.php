<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Traits;

use ArangoDBClient\CollectionHandler;
use Drupal\arangodb\ConnectionFactory;

/**
 * @property \Drupal\Core\DependencyInjection\ContainerBuilder $container
 */
trait ConnectionTrait {

  protected string $collectionNamePrefix = '';

  /**
   * @param int $length
   *
   * @return string
   */
  abstract protected function randomMachineName($length = 8);

  protected function setUpArangodbConnection(): static {
    $this->collectionNamePrefix = 'test_' . $this->randomMachineName();

    return $this;
  }

  /**
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ClientException
   */
  protected function tearDownArangodbDropCollections(string $collectionNamePrefix): static {
    $connectionFactory = $this->createConnectionFactory();
    $connection = $connectionFactory->get('default');
    $collectionHandler = new CollectionHandler($connection);

    foreach ($collectionHandler->getAllCollections() as $collectionName => $collection) {
      if (str_contains($collectionName, $collectionNamePrefix)) {
        $collectionHandler->drop($collectionName);
      }
    }

    return $this;
  }

  protected function createConnectionFactory(): ConnectionFactory {
    return new ConnectionFactory(
      $this->container->get('arangodb.connection_pool'),
      $this->getConnectionOptions(),
      $this->container->get('settings'),
    );
  }

  protected function getConnectionOptions(): array {
    return [
      'default' => [
        'endpoint' => getenv('ARANGODB_CONNECTION_OPTION_ENDPOINT'),
        'AuthType' => getenv('ARANGODB_CONNECTION_OPTION_AUTHTYPE'),
        'AuthUser' => getenv('ARANGODB_CONNECTION_OPTION_AUTHUSER'),
        'AuthPasswd' => getenv('ARANGODB_CONNECTION_OPTION_AUTHPASSWD'),
        'database' => getenv('ARANGODB_CONNECTION_OPTION_DATABASE'),
        'Connection' => 'Close',
        'timeout' => 3,
        'Reconnect' => TRUE,
        'policy' => 'last',
        'createCollection' => FALSE,
      ],
    ];
  }

}
