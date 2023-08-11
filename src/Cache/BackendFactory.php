<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Cache;

use Drupal\arangodb\ConnectionFactoryInterface;
use Drupal\Core\Cache\CacheFactoryInterface;
use Psr\Log\LoggerInterface;
use Sweetchuck\CacheBackend\ArangoDb\CacheDocumentConverterInterface;
use Sweetchuck\CacheBackend\ArangoDb\CacheItemPool;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;
use Sweetchuck\CacheBackend\ArangoDb\ValidatorInterface;

class BackendFactory implements CacheFactoryInterface {

  protected ConnectionFactoryInterface $connectionFactory;

  protected string $connectionName = 'default';

  protected ValidatorInterface $validator;

  protected CacheDocumentConverterInterface $documentConverter;

  protected SerializerInterface $serializer;

  protected SchemaManagerInterface $schemaManager;

  protected LoggerInterface $logger;

  /**
   * @phpstan-var drupal-arangodb-cache-backend-options
   */
  protected array $storageOptions;

  /**
   * @phpstan-param drupal-arangodb-cache-backend-options $storageOptions
   */
  public function __construct(
    ConnectionFactoryInterface $connectionFactory,
    string $connectionName,
    ValidatorInterface $validator,
    CacheDocumentConverterInterface $documentConverter,
    SerializerInterface $serializer,
    SchemaManagerInterface $schemaManager,
    LoggerInterface $logger,
    array $storageOptions,
  ) {
    $this->connectionFactory = $connectionFactory;
    $this->connectionName = $connectionName;
    $this->validator = $validator;
    $this->documentConverter = $documentConverter;
    $this->serializer = $serializer;
    $this->schemaManager = $schemaManager;
    $this->logger = $logger;
    $this->storageOptions = $storageOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $storageOptions = $this->getFinalStorageOptions();
    $collectionName = strtr(
      $storageOptions['collection_name_pattern'],
      [
        '{{ bin }}' => $bin,
      ],
    );
    $pool = $this->createPool();
    $pool->setCollectionName($collectionName);

    return new Backend($pool);
  }

  /**
   * @phpstan-return drupal-arangodb-cache-backend-options
   */
  protected function getFinalStorageOptions(): array {
    return array_replace_recursive(
      $this->getDefaultStorageOptions(),
      $this->storageOptions,
    );
  }

  protected function createPool(): CacheItemPool {
    $pool = new CacheItemPool(
      $this->validator,
      $this->documentConverter,
      $this->serializer,
      $this->schemaManager,
      $this->logger,
    );

    $pool->setConnection($this->connectionFactory->get($this->connectionName));

    return $pool;
  }

  /**
   * @phpstan-return drupal-arangodb-cache-backend-options
   */
  protected function getDefaultStorageOptions(): array {
    return [
      'collection_name_pattern' => 'cache_{{ bin }}',
    ];
  }

}
