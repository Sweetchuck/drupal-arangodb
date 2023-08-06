<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Unit\KeyValue;

use Drupal\arangodb\KeyValue\MainExpirableFactory;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @covers \Drupal\arangodb\KeyValue\MainExpirableFactory
 */
class MainExpirableFactoryTest extends MainSimpleFactoryTest {

  protected string $defaultServiceId = 'keyvalue.expirable.database';

  protected function createInstance(ContainerInterface $container, array $parameters): KeyValueFactoryInterface {
    return new MainExpirableFactory($container, $parameters);
  }

}
