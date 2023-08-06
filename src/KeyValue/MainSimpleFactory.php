<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main KeyValue factory implementation.
 *
 * This class is not used by default.
 * The "keyvalue" and "keyvalue.expirable" service definitions have to be
 * replaced in order to use this KeyValueStoreFactory.
 *
 * This one uses a more flexible "collection <=> service_id" mapping.
 *
 * @code
 * parameters:
 *   arangodb.keyvalue.main_factory.simple:
 *     default: 'keyvalue.database'
 *     mapping_static:
 *       my_module_01.a: 'arangodb.keyvalue.factory.simple_shared_php'
 *       my_module_01.b: 'arangodb.keyvalue.factory.simple_shared_php'
 *     mapping_pattern:
 *       '/^my_module_01\.c\.\d+/': 'arangodb.keyvalue.factory.simple_shared_loafer'
 *   arangodb.keyvalue.main_factory.expirable:
 *     default: 'keyvalue.expirable.database'
 *     mapping_static:
 *       my_module_01.d: 'arangodb.keyvalue.factory.expirable_shared_php'
 *       my_module_01.e: 'arangodb.keyvalue.factory.expirable_shared_php'
 *     mapping_pattern:
 *       '/^my_module_01\.f\.\d+/': 'arangodb.keyvalue.factory.expirable_shared_loafer'
 *
 * services:
 *   keyvalue:
 *     class: 'Drupal\arangodb\KeyValue\MainSimpleFactory'
 *     arguments:
 *       - '@service_container'
 *       - '%arangodb.keyvalue.main_factory.simple%'
 *   keyvalue.expirable:
 *     class: 'Drupal\arangodb\KeyValue\MainExpirableFactory'
 *     arguments:
 *       - '@service_container'
 *       - '%arangodb.keyvalue.main_factory.expirable%'
 * @endcode
 */
class MainSimpleFactory implements KeyValueFactoryInterface {

  protected string $defaultFactoryServiceId = 'keyvalue.database';

  protected ContainerInterface $container;

  /**
   * @phpstan-var drupal-arangodb-main-factory-parameters-final
   *
   * @code
   * # Value: A service id which implements the \Drupal\Core\KeyValueStore\KeyValueFactoryInterface interface.
   * default: 'keyvalue.database',
   *
   * # Key: Collection name.
   * # Value: A service id which implements the \Drupal\Core\KeyValueStore\KeyValueFactoryInterface interface.
   * mapping_static:
   *   my_module_01.foo.a: 'implementer_a.keyvalue.factory'
   *   my_module_01.foo.b: 'implementer_a.keyvalue.factory'
   *   my_module_01.bar.a: 'implementer_b.keyvalue.factory'
   *   my_module_01.bar.b: 'implementer_b.keyvalue.factory'
   *
   * # Key: A regexp pattern which will be tested against the collection name.
   * # Value: A service id which implements the \Drupal\Core\KeyValueStore\KeyValueFactoryInterface interface.
   * mapping_pattern:
   *   '/^my_module_01\.baz\.\d+/': 'implementer_c.keyvalue.factory'
   * @endcode
   */
  protected array $parameters = [];

  /**
   * @phpstan-var array<string, \Drupal\Core\KeyValueStore\KeyValueStoreInterface>
   */
  protected array $stores = [];

  /**
   * @phpstan-param drupal-arangodb-main-factory-parameters-lazy $parameters
   */
  public function __construct(ContainerInterface $container, array $parameters) {
    $this->container = $container;
    $this->setParameters($parameters);
  }

  /**
   * @phpstan-param drupal-arangodb-main-factory-parameters-lazy $parameters
   */
  protected function setParameters(array $parameters): static {
    $this->parameters = $parameters
      + [
        'default' => $this->defaultFactoryServiceId,
        'mapping_static' => [],
        'mapping_pattern' => [],
      ];

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function get($collection) {
    if (!isset($this->stores[$collection])) {
      $this->stores[$collection] = $this
        ->container
        ->get($this->getFactoryServiceId($collection))
        ->get($collection);
    }

    return $this->stores[$collection];
  }

  protected function getFactoryServiceId(string $collection): string {
    if (isset($this->parameters['mapping_static'][$collection])) {
      return $this->parameters['mapping_static'][$collection];
    }

    foreach ($this->parameters['mapping_pattern'] as $pattern => $serviceId) {
      if (preg_match($pattern, $collection) === 1) {
        return $serviceId;
      }
    }

    return $this->parameters['default'];
  }

}
