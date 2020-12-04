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

  protected ValidatorInterface $validator;

  protected CacheDocumentConverterInterface $documentConverter;

  protected SerializerInterface $serializer;

  protected SchemaManagerInterface $schemaManager;

  protected LoggerInterface $logger;

  protected array $parameters = [];

  protected string $bin = '';

  public function __construct(
    ConnectionFactoryInterface $connectionFactory,
    ValidatorInterface $validator,
    CacheDocumentConverterInterface $documentConverter,
    SerializerInterface $serializer,
    SchemaManagerInterface $schemaManager,
    LoggerInterface $logger,
    array $parameters
  ) {
    $this->connectionFactory = $connectionFactory;
    $this->validator = $validator;
    $this->documentConverter = $documentConverter;
    $this->serializer = $serializer;
    $this->schemaManager = $schemaManager;
    $this->logger = $logger;
    $this->parameters = $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    $this->bin = $bin;
    $this->initParameters();

    return new Backend($this->createPool());
  }

  protected function initParameters(): static {
    $this->parameters = array_replace_recursive(
      $this->getDefaultParameters(),
      $this->parameters,
    );

    return $this;
  }

  protected function createPool(): CacheItemPool {
    $pool = new CacheItemPool(
      $this->validator,
      $this->documentConverter,
      $this->serializer,
      $this->schemaManager,
      $this->logger,
    );

    return $pool
      ->setConnection($this->connectionFactory->get())
      ->setCollectionName($this->getCollectionName());
  }

  protected function getCollectionName(): string {
    return $this->parameters['collection_name_prefix'] . $this->bin;
  }

  protected function getDefaultParameters(): array {
    return [
      'collection_name_prefix' => 'cache_',
    ];
  }

}
