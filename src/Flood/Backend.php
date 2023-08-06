<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Flood;

use ArangoDBClient\ServerException;
use ArangoDBClient\Statement;
use Drupal\arangodb\ConnectionFactory;
use Drupal\arangodb\ConnectionTrait;
use Drupal\arangodb\Utils;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the database flood backend. This is the default Drupal backend.
 */
class Backend implements FloodInterface {

  use ConnectionTrait;

  protected RequestStack $requestStack;
  protected TimeInterface $time;

  protected SchemaManager $schemaManager;

  protected DocumentConverterInterface $documentConverter;

  protected array $options;

  public function __construct(
    ConnectionFactory $connectionFactory,
    string $connectionName,
    SchemaManager $schemaManager,
    DocumentConverterInterface $documentConverter,
    RequestStack $requestStack,
    TimeInterface $time,
    array $options,
  ) {
    $this->dbConnectionFactory = $connectionFactory;
    $this->dbConnectionName = $connectionName;
    $this->options = $options;
    $this->setDbCollectionNamePattern($options['collectionNamePattern'] ?? 'flood');
    $this->schemaManager = $schemaManager;
    $this->documentConverter = $documentConverter;
    $this->requestStack = $requestStack;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function register($name, $window = 3600, $identifier = NULL) {
    $this->initDbConnection();

    if (!isset($identifier)) {
      $identifier = $this->requestStack->getCurrentRequest()->getClientIp();
    }

    try {
      $this->doInsert($name, $window, $identifier);
    }
    catch (ServerException $e) {
      if ($e->getServerCode() === Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        $this->initDbCollection($this->getDbCollectionName());
        $this->doInsert($name, $window, $identifier);

        return;
      }

      throw $e;
    }
  }

  /**
   * Inserts an event into the flood table.
   *
   * @param string $name
   *   The name of an event.
   * @param int $window
   *   Number of seconds before this event expires.
   * @param string $identifier
   *   Unique identifier of the current user.
   *
   * @throws \ArangoDBClient\Exception
   * @throw \ArangoDBClient\ServerException
   */
  protected function doInsert(string $name, int $window, string $identifier) {
    $document = $this->documentConverter->eventToDocument($name, $window, $identifier);
    $this->dbDocumentHandler->insert(
      $this->getDbCollectionName(),
      $document,
      [
        'createCollection' => FALSE,
      ],
    );
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function clear($name, $identifier = NULL) {
    $this->initDbConnection();

    if ($identifier === NULL) {
      $identifier = $this->requestStack->getCurrentRequest()->getClientIp();
    }

    $query = <<< AQL
    FOR doc IN @@collection
      FILTER
        doc.name == @name
        AND
        doc.identifier == @identifier
      REMOVE doc IN @@collection
    AQL;

    $statement = new Statement(
      $this->getDbConnection(),
      [
        Statement::ENTRY_QUERY => $query,
        Statement::ENTRY_BINDVARS => [
          '@collection' => $this->getDbCollectionName(),
          'name' => $name,
          'identifier' => $identifier,
        ],
      ],
    );

    try {
      $statement->execute();
    } catch (ServerException $e) {
      if ($e->getServerCode() !== Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        throw $e;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    $this->initDbConnection();

    if ($identifier === NULL) {
      $identifier = $this->requestStack->getCurrentRequest()->getClientIp();
    }

    return $threshold > $this->numOfNonExpiredEvents($name, $window, $identifier);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function garbageCollection() {
    $this->initDbConnection();

    $query = <<< AQL
    FOR doc IN @@collection
      FILTER
        doc.expiration < @expiration
      REMOVE doc IN @@collection
    AQL;

    $statement = new Statement(
      $this->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => $this->getDbCollectionName(),
          'expiration' => $this->time->getCurrentTime(),
        ],
      ],
    );
    try {
      $statement->execute();
    }
    catch (ServerException $e) {
      if ($e->getServerCode() !== Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        throw $e;
      }
    }
  }

  /**
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ServerException
   */
  protected function numOfNonExpiredEvents(string $name, int $window, string $identifier): int {
    $query = <<< AQL
    FOR doc IN @@collection
      FILTER
        doc.name == @name
        AND
        doc.identifier == @identifier
        AND
        doc.created > @createdSince
      COLLECT WITH COUNT INTO length
      RETURN length
    AQL;

    $bindVars = [
      '@collection' => $this->getDbCollectionName(),
      'name' => $name,
      'identifier' => $identifier,
      'createdSince' => $this->time->getCurrentTime() - $window,
    ];

    $statement = new Statement(
      $this->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => $bindVars,
      ],
    );

    try {
      $result = $statement->execute();

      return (int) $result->current();
    }
    catch (ServerException $e) {
      if ($e->getServerCode() === Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        return 0;
      }

      throw $e;
    }
  }
}
