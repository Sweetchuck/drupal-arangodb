<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use Drupal\arangodb\ConnectionFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Queue\QueueInterface;
use Psr\Log\LoggerInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

/**
 * Queue factory implementation.
 *
 * There is no such thing as QueueFactoryInterface, but the ::get() method is mandatory.
 *
 * @see \Drupal\Core\Queue\QueueFactory::get
 */
class CoreStorageFactory {

  protected ConnectionFactoryInterface $connectionFactory;

  protected string $connectionName = 'default';

  public function getConnectionFactory(): ConnectionFactoryInterface {
    return $this->connectionFactory;
  }

  public function setConnectionFactory(ConnectionFactoryInterface $connectionFactory): static {
    $this->connectionFactory = $connectionFactory;

    return $this;
  }

  protected CoreDocumentConverterInterface $documentConverter;

  public function getDocumentConverter(): CoreDocumentConverterInterface {
    return $this->documentConverter;
  }

  public function setDocumentConverter(CoreDocumentConverterInterface $documentConverter): static {
    $this->documentConverter = $documentConverter;

    return $this;
  }

  protected SchemaManagerInterface $schemaManager;

  public function getSchemaManager(): SchemaManagerInterface {
    return $this->schemaManager;
  }

  public function setSchemaManager(SchemaManagerInterface $schemaManager): static {
    $this->schemaManager = $schemaManager;

    return $this;
  }

  protected TimeInterface $time;

  public function getTime(): TimeInterface {
    return $this->time;
  }

  public function setTime(TimeInterface $time): static {
    $this->time = $time;

    return $this;
  }

  protected LoggerInterface $logger;

  public function getLogger(): LoggerInterface {
    return $this->logger;
  }

  public function setLogger(LoggerInterface $logger): static {
    $this->logger = $logger;

    return $this;
  }

  /**
   * @phpstan-var drupal-arangodb-queue-options-lazy
   */
  protected array $options = [];

  /**
   * @phpstan-return drupal-arangodb-queue-options-lazy
   */
  public function getOptions(): array {
    return $this->options;
  }

  /**
   * @phpstan-param drupal-arangodb-queue-options-lazy $options
   */
  public function setOptions(array $options): static {
    $this->options = $options;

    return $this;
  }

  /**
   * @phpstan-return drupal-arangodb-queue-options-lazy
   */
  public function getDefaultOptions(): array {
    return [
      'collection_name_pattern' => 'queue_core_shared',
    ];
  }

  /**
   * @phpstan-return drupal-arangodb-queue-options-final
   */
  public function getFinalOptions(): array {
    return array_replace_recursive(
      $this->getDefaultOptions(),
      $this->getOptions(),
    );
  }

  /**
   * @phpstan-param drupal-arangodb-queue-options-lazy $options
   */
  public function __construct(
    ConnectionFactoryInterface $connectionFactory,
    string $connectionName,
    CoreDocumentConverterInterface $documentConverter,
    SchemaManagerInterface $schemaManager,
    TimeInterface $time,
    LoggerInterface $logger,
    array $options,
  ) {
    $this->connectionName = $connectionName;

    $this
      ->setConnectionFactory($connectionFactory)
      ->setDocumentConverter($documentConverter)
      ->setSchemaManager($schemaManager)
      ->setTime($time)
      ->setLogger($logger)
      ->setOptions($options);
  }

  public function get(string $name): QueueInterface {
    $options = $this->getFinalOptions();

    $queue = new CoreStorage($name);
    $queue
      ->setDbCollectionNamePattern($options['collection_name_pattern'])
      ->setDbConnection($this->getConnectionFactory()->get($this->connectionName))
      ->setSchemaManager($this->getSchemaManager())
      ->setDocumentConverter($this->getDocumentConverter())
      ->setTime($this->getTime())
      ->setLogger($this->getLogger());

    return $queue;
  }

}
