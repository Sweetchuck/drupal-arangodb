<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Kernel\KeyValue;

use ArangoDBClient\Statement;
use Drupal\arangodb\KeyValue\DocumentConverter;
use Drupal\arangodb\KeyValue\SchemaManager;
use Drupal\arangodb\KeyValue\Store;
use Drupal\arangodb\KeyValue\StoreExpirableFactory;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\arangodb\Traits\ConnectionTrait;
use Drupal\Tests\arangodb\Traits\TestTime;
use Sweetchuck\CacheBackend\ArangoDb\Serializer\NativeSerializer;

/**
 * Functional tests for the key-value storage.
 *
 * @group KeyValue
 *
 * @covers \Drupal\arangodb\KeyValue\Store
 */
class StoreTest extends KernelTestBase {

  use ConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'arangodb',
  ];

  protected TimeInterface $time;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->time = new TestTime();
    $this->time->requestTime = 100.0;
    $this->time->currentTime = $this->time->requestTime;
    $this->container->set('datetime.time', $this->time);
    $this->setUpArangodbConnection();
  }

  /**
   * @throws \Exception
   */
  protected function tearDown(): void {
    $this->tearDownArangodbDropCollections($this->collectionNamePrefix);
    parent::tearDown();
  }

  /**
   * @throws \Exception
   */
  protected function createKeyValueStore(string $collection, array $options = []): Store {
    $options += [
      'collection_name_pattern' => 'keyvalue_shared',
    ];

    $options['collection_name_pattern'] = "{$this->collectionNamePrefix}_{$options['collection_name_pattern']}";

    $factory = new StoreExpirableFactory(
      $this->createConnectionFactory(),
      'default',
      new SchemaManager(),
      new DocumentConverter(new NativeSerializer()),
      $options,
    );

    return $factory->get($collection);
  }

  /**
   * Tests KeyValue workflow.
   *
   * @throws \Exception
   */
  public function testWorkflow(): void {
    $collFoo = $this->createKeyValueStore('foo');
    $collBar = $this->createKeyValueStore('bar');
    $collBar->setTime($this->time);

    static::assertFalse($collFoo->has('k1'));
    static::assertFalse($collFoo->has('k2'));
    static::assertSame([], $collFoo->getAll());

    $collFoo->set('k1', 'k1-v1');
    static::assertTrue($collFoo->has('k1'));
    static::assertFalse($collBar->has('k1'));
    static::assertSame('k1-v1', $collFoo->get('k1'));

    static::assertSame(
      [
        'k1' => 'k1-v1',
        'k2' => NULL,
      ],
      $collFoo->getMultiple(['k1', 'k2']),
    );

    $collFoo->setMultiple([
      'k1' => 'k1-v2',
      'k2' => 'k2-v1',
      'k3' => 'k3-v1',
    ]);

    static::assertSame(
      [
        'k1' => 'k1-v2',
        'k2' => 'k2-v1',
        'k3' => 'k3-v1',
      ],
      $collFoo->getAll(),
    );

    static::assertFalse($collFoo->setIfNotExists('k1', 'k1-v3'));
    static::assertSame('k1-v2', $collFoo->get('k1'));
    $collFoo->delete('k1');
    static::assertFalse($collFoo->has('k1'));
    static::assertTrue($collFoo->has('k2'));
    static::assertTrue($collFoo->setIfNotExists('k1', 'k1-v3'));
    static::assertSame('k1-v3', $collFoo->get('k1'));

    $collFoo->deleteMultiple(['k1', 'k3']);
    static::assertSame(
      [
        'k2' => 'k2-v1',
      ],
      $collFoo->getAll(),
    );

    $collFoo->rename('k2', 'k2-renamed');
    static::assertSame(
      [
        'k2-renamed' => 'k2-v1',
      ],
      $collFoo->getAll(),
    );

    $collFoo->deleteAll();
    static::assertSame([], $collFoo->getAll());

    $this->time->currentTime = \time();
    $collBar->set('k1', 'k1-v1');
    $collBar->setWithExpire('k2', 'k2-v1', 10);
    $rows = $this->getRows($collBar);
    static::assertSame(
      [
        'k1' => [
          'collection' => 'bar',
          'key' => 'k1',
          'value' => 'k1-v1',
        ],
        'k2' => [
          'collection' => 'bar',
          'key' => 'k2',
          'value' => 'k2-v1',
          'expire' => intval($this->time->currentTime + 10),
        ],
      ],
      $rows,
    );

    static::assertFalse(
      $collBar->setWithExpireIfNotExists('k2', 'k2-v2', 15),
    );
    static::assertTrue(
      $collBar->setWithExpireIfNotExists('k3', 'k3-v1', 15),
    );
    static::assertSame(
      [
        'k1' => [
          'collection' => 'bar',
          'key' => 'k1',
          'value' => 'k1-v1',
        ],
        'k2' => [
          'collection' => 'bar',
          'key' => 'k2',
          'value' => 'k2-v1',
          'expire' => intval($this->time->currentTime + 10),
        ],
        'k3' => [
          'collection' => 'bar',
          'key' => 'k3',
          'value' => 'k3-v1',
          'expire' => intval($this->time->currentTime + 15),
        ],
      ],
      $this->getRows($collBar),
    );

    $collBar->setMultipleWithExpire(
      [
        'k3' => 'k3-v2',
        'k4' => 'k4-v1',
      ],
      20,
    );
    static::assertSame(
      [
        'k1' => [
          'collection' => 'bar',
          'key' => 'k1',
          'value' => 'k1-v1',
        ],
        'k2' => [
          'collection' => 'bar',
          'key' => 'k2',
          'value' => 'k2-v1',
          'expire' => intval($this->time->currentTime + 10),
        ],
        'k3' => [
          'collection' => 'bar',
          'key' => 'k3',
          'value' => 'k3-v2',
          'expire' => intval($this->time->currentTime + 20),
        ],
        'k4' => [
          'collection' => 'bar',
          'key' => 'k4',
          'value' => 'k4-v1',
          'expire' => intval($this->time->currentTime + 20),
        ],
      ],
      $this->getRows($collBar),
    );
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function getRows(Store $storage): array {
    $query = <<< AQL
      FOR doc in @@collection
        SORT doc.key
        RETURN doc
    AQL;

    $statement = new Statement(
      $storage->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => $storage->getDbCollectionName(),
        ],
      ],
    );

    $rows = [];
    $result = $statement->execute();
    /** @var \ArangoDBClient\Document $document */
    foreach ($result as $document) {
      $row = $document->getAll([
        '_includeInternals' => FALSE,
        '_ignoreHiddenAttributes' => TRUE,
      ]);
      $row['value'] = unserialize($row['value'], ['allowed_classes' => []]);
      unset($row['_key']);
      $rows[$row['key']] = $row;
    }

    return $rows;
  }

}
