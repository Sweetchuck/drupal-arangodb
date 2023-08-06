<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Unit;

use ArangoDBClient\Connection as ArangoDbConnection;
use Drupal\arangodb\Cache\Backend as ArangoDbCacheBackend;
use Drupal\arangodb\Utils;
use Drupal\Tests\UnitTestCase;
use Sweetchuck\CacheBackend\ArangoDb\CacheItemPool;

/**
 * @group arangodb
 *
 * @covers \Drupal\arangodb\Utils
 */
class UtilsTest extends UnitTestCase {

  /**
   * @throws \ArangoDBClient\Exception
   */
  public function testGroupCacheBinsByConnections(): void {
    $options1 = [
      'endpoint' => 'tcp://127.0.0.1:8529',
      'database' => 'db1',
    ];
    $connection1 = new ArangoDbConnection($options1);
    $pool1 = new CacheItemPool();
    $pool1->setConnection($connection1);
    $pool1->setCollectionName('cache_1');
    $pool2 = new CacheItemPool();
    $pool2->setConnection($connection1);
    $pool2->setCollectionName('cache_2');

    $options2 = [
      'endpoint' => 'tcp://127.0.0.1:8530',
      'database' => 'db2',
    ];
    $connection2 = new ArangoDbConnection($options2);
    $pool3 = new CacheItemPool();
    $pool3->setConnection($connection2);
    $pool3->setCollectionName('cache_3');
    $pool4 = new CacheItemPool();
    $pool4->setConnection($connection2);
    $pool4->setCollectionName('cache_4');

    $bins = [
      new ArangoDbCacheBackend($pool1),
      new ArangoDbCacheBackend($pool2),
      new ArangoDbCacheBackend($pool3),
      new ArangoDbCacheBackend($pool4),
    ];

    $expected = [
      [
        'connection' => $connection1,
        'binNames' => [
          0 => 'cache_1',
          1 => 'cache_2',
        ],
      ],
      [
        'connection' => $connection2,
        'binNames' => [
          2 => 'cache_3',
          3 => 'cache_4',
        ],
      ],
    ];
    $actual = array_values(Utils::groupCacheBinsByConnections($bins));
    static::assertSame($expected, $actual);
  }

  public function casesConnectionUri(): array {
    $expected = 'tcp://me@1.2.3.4:5678/my_database_01';
    $values = [
      'endpoint' => 'tcp://1.2.3.4:5678',
      'AuthUser' => 'me',
      'database' => 'my_database_01',
    ];

    $valuesEndpointAsArray = [
      'endpoint' => [
        'tcp://1.2.3.4:5678',
        'tcp://1.2.3.4:9012',
      ],
      'AuthUser' => 'me',
      'database' => 'my_database_01',
    ];

    return [
      'plain array' => [
        $expected,
        $values,
      ],
      'plain ArrayAccess' => [
        $expected,
        new \ArrayObject($values),
      ],
      'array array' => [
        $expected,
        $valuesEndpointAsArray,
      ],
      'array ArrayAccess' => [
        $expected,
        new \ArrayObject($valuesEndpointAsArray),
      ],
    ];
  }

  /**
   * @dataProvider casesConnectionUri
   */
  public function testConnectionUri(string $expected, $options) {
    static::assertEquals($expected, Utils::connectionUri($options));
  }

}
