<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Kernel\Cache;

use Drupal\arangodb\Cache\Backend;
use Drupal\arangodb\Cache\BackendFactory;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\arangodb\Traits\ConnectionTrait;

class BackendTest extends KernelTestBase {

  use ConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'arangodb',
    'user',
    'system',
  ];

  public function testDummy() {
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

}
