<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Kernel\Lock;

use Drupal\arangodb\Lock\Backend;
use Drupal\arangodb\Lock\DocumentConverter;
use Drupal\arangodb\Lock\SchemaManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\arangodb\Traits\ConnectionTrait;

/**
 * Tests the Database lock backend.
 *
 * @group Lock
 */
class BackendTest extends KernelTestBase {

  use ConnectionTrait;

  protected Backend $lock;

  protected static $modules = [
    'arangodb',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpArangodbConnection();

    $this->lock = new Backend(
      $this->createConnectionFactory(),
      'default',
      new SchemaManager(),
      new DocumentConverter(),
      [
        'collectionNamePattern' => "{$this->collectionNamePrefix}_lock",
      ],
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ClientException
   */
  protected function tearDown(): void {
    $this->tearDownArangodbDropCollections($this->collectionNamePrefix);
    parent::tearDown();
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  public function testBackendLockRelease(): void {
    static::assertTrue(
      $this->lock->acquire('lock_a'),
      'Could acquire first lock.',
    );

    static::assertFalse(
      $this->lock->lockMayBeAvailable('lock_a'),
      'First lock is unavailable.',
    );

    $this->lock->release('lock_a');
    static::assertTrue(
      $this->lock->lockMayBeAvailable('lock_a'),
      'First lock has been released.',
    );

    static::assertTrue(
      $this->lock->acquire('lock_b'),
      'Could acquire second lock.',
    );

    static::assertTrue(
      $this->lock->acquire('lock_b'),
      'Could acquire second lock a second time within the same request.',
    );
    $this->lock->release('lock_b');

    // Test acquiring and releasing a lock with a long key (over 255 chars).
    $long_key = 'long_key:BZoMiSf9IIPULsJ98po18TxJ6T4usd3MZrLE0d3qMgG6iAgDlOi1G3oMap7zI5df84l7LtJBg4bOj6XvpO6vDRmP5h5QbA0Bj9rVFiPIPAIQZ9qFvJqTALiK1OR3GpOkWQ4vgEA4LkY0UfznrWBeuK7IWZfv1um6DLosnVXd1z1cJjvbEUqYGJj92rwHfhYihLm8IO9t3P2gAvEkH5Mhc8GBoiTsIDnP01Te1kxGFHO3RuvJIxPnHmZtSdBggmuVN7x9';
    static::assertTrue(
      $this->lock->acquire($long_key),
      'Could acquire long key lock.',
    );

    static::assertFalse(
      $this->lock->lockMayBeAvailable($long_key),
      'Long key lock is unavailable.',
    );

    $this->lock->release($long_key);
    static::assertTrue(
      $this->lock->lockMayBeAvailable($long_key),
      'Long key lock has been released.',
    );
  }

  /**
   * Tests backend release functionality.
   *
   * @throws \ArangoDBClient\Exception
   */
  public function testBackendLockReleaseAll() {
    static::assertTrue(
      $this->lock->acquire('lock_a'),
      'Could acquire first lock.',
    );

    static::assertTrue(
      $this->lock->acquire('lock_b'),
      'Could acquire second lock.',
    );

    $this->lock->releaseAll();

    static::assertTrue(
      $this->lock->lockMayBeAvailable('lock_a'),
      'First lock has been released.',
    );

    static::assertTrue(
      $this->lock->lockMayBeAvailable('lock_b'),
      'Second lock has been released.',
    );
  }

}
