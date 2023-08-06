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

  protected array $options;

  public function __construct(
    ConnectionFactory $connectionFactory,
    string $connectionName,
    DocumentConverterInterface $documentConverter,
    SchemaManagerInterface $schemaManager,
    array $options,
  ) {
    $this->dbConnectionFactory = $connectionFactory;
    $this->dbConnectionName = $connectionName;
    $this->documentConverter = $documentConverter;
    $this->schemaManager = $schemaManager;
    $this->options = $options;

    $this->dbCollectionNamePattern = $options['collectionNamePattern'] ?? 'log';
  }

  public function getDbCollectionNamePlaceholderValues(string $channel = 'unknown'): array {
    return [
      '{{ channel }}' => $channel,
    ];
  }

  public function getDbCollectionName(string $channel = 'unknown'): string {
    return strtr(
      $this->getDbCollectionNamePattern(),
      $this->getDbCollectionNamePlaceholderValues($channel),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function log($level, string|\Stringable $message, array $context = []): void {
    $collectionName = $this->getDbCollectionName($context['channel']);
    $this
      ->initDbConnection()
      ->initDbCollection($collectionName);

    $document = $this->documentConverter->logEntryToDocument($level, $message, $context);
    $this->dbDocumentHandler->insert($collectionName, $document);
  }

}
