<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Kernel\Cache;

use Drupal\arangodb\Cache\Backend;
use Drupal\arangodb\Cache\BackendFactory;
use Drupal\arangodb\ConnectionFactory;
use Drupal\KernelTests\KernelTestBase;

class BackendTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'arangodb',
    'user',
    'system',
  ];

  public function testDummy() {
    $backend = $this->createBackend();

    static::assertTrue(TRUE);
  }

  protected function createBackend(): Backend {
    return $this->createBackendFactory()->get('dummy');
  }

  protected function createBackendFactory(): BackendFactory {
    return new BackendFactory(
      $this->createConnectionFactory(),
      $this->container->get('arangodb.cache.validator.basic'),
      $this->container->get('arangodb.cache.document_converter.default'),
      $this->container->get('arangodb.serializer.native'),
      $this->container->get('arangodb.cache.schema_manager.default'),
      $this->container->get('logger.channel.arangodb_cache_default'),
      $this->container->getParameter('arangodb.cache.backend.options.default'),
    );
  }

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
    ];
  }

}
