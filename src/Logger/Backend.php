<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Logger;

use Drupal\arangodb\ConnectionFactory;
use Drupal\arangodb\ConnectionTrait;
use Drupal\Core\Logger\RfcLoggerTrait;
use Psr\Log\LoggerInterface;
use Sweetchuck\CacheBackend\ArangoDb\SchemaManagerInterface;

/**
 * Logs events in the watchdog database table.
 */
class Backend implements LoggerInterface {
  use ConnectionTrait;
  use RfcLoggerTrait;

  protected DocumentConverterInterface $documentConverter;

  protected SchemaManagerInterface $schemaManager;

  protected array $options;

  public function __construct(
    ConnectionFactory $connectionFactory,
    string $connectionName,
    DocumentConverterInterface $documentConverter,
    SchemaManagerInterface $schemaManager,
    array $options,
  ) {
    $this->connectionFactory = $connectionFactory;
    $this->connectionName = $connectionName;
    $this->documentConverter = $documentConverter;
    $this->schemaManager = $schemaManager;
    $this->options = $options;

    $this->collectionNamePattern = $options['collectionNamePattern'] ?? 'log';
  }

  public function getCollectionNamePlaceholderValues(string $channel = 'unknown'): array {
    return [
      '{{ channel }}' => $channel,
    ];
  }

  public function getCollectionName(string $channel = 'unknown'): string {
    return strtr(
      $this->getCollectionNamePattern(),
      $this->getCollectionNamePlaceholderValues($channel),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $collectionName = $this->getCollectionName($context['channel']);
    $this
      ->initConnection()
      ->initCollection($collectionName);

    $document = $this->documentConverter->logEntryToDocument($level, $message, $context);
    $this->documentHandler->insert($collectionName, $document);
  }

}
