<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Unit\KeyValue;

use Drupal\arangodb\KeyValue\MainSimpleFactory;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @covers \Drupal\arangodb\KeyValue\MainSimpleFactory
 */
class MainSimpleFactoryTest extends TestCase {

  protected string $defaultServiceId = 'keyvalue.database';

  protected function createInstance(ContainerInterface $container, array $parameters): KeyValueFactoryInterface {
    return new MainSimpleFactory($container, $parameters);
  }

  public function casesGet(): array {
    return [
      'empty parameters' => [
        $this->defaultServiceId,
        [],
        'a',
      ],
      'only default is provided' => [
        's0',
        [
          'default' => 's0',
        ],
        'a',
      ],
      'no match; no default' => [
        $this->defaultServiceId,
        [
          'mapping_static' => [
            'a' => 's1',
            'b' => 's2',
            'c' => 's3',
          ],
          'mapping_pattern' => [
            '/a/' => 's4',
            '/b/' => 's5',
            '/c/' => 's6',
          ],
        ],
        'd',
      ],
      'no match; with default' => [
        's0',
        [
          'default' => 's0',
          'mapping_static' => [
            'a' => 's1',
            'b' => 's2',
            'c' => 's3',
          ],
          'mapping_pattern' => [
            '/a/' => 's4',
            '/b/' => 's5',
            '/c/' => 's6',
          ],
        ],
        'd',
      ],
      'static match' => [
        's2',
        [
          'default' => 's0',
          'mapping_static' => [
            'a' => 's1',
            'b' => 's2',
            'c' => 's3',
          ],
          'mapping_pattern' => [
            '/a/' => 's4',
            '/b/' => 's5',
            '/c/' => 's6',
          ],
        ],
        'b',
      ],
      'pattern match' => [
        's5',
        [
          'default' => 's0',
          'mapping_static' => [
            'a' => 's1',
            'c' => 's3',
          ],
          'mapping_pattern' => [
            '/a/' => 's4',
            '/b/' => 's5',
            '/c/' => 's6',
          ],
        ],
        'b',
      ],
    ];
  }

  /**
   * @dataProvider casesGet
   */
  public function testGet(string $expectedServiceId, array $parameters, string $collection): void {
    $store = $this->createMock(KeyValueStoreInterface::class);
    $storeFactory = $this->createMock(KeyValueFactoryInterface::class);
    $storeFactory
      ->expects(static::once())
      ->method('get')
      ->with($collection)
      ->willReturn($store);
    $container = $this->createMock(ContainerInterface::class);
    $container
      ->expects(static::once())
      ->method('get')
      ->with($expectedServiceId)
      ->willReturn($storeFactory);
    $mainFactory = $this->createInstance($container, $parameters);
    static::assertSame(
      $store,
      $mainFactory->get($collection),
      'the right serviceId is selected',
    );
    static::assertSame(
      $store,
      $mainFactory->get($collection),
      '$mainFactory->stores is used',
    );
  }

}
