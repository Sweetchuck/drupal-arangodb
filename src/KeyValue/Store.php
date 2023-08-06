<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use ArangoDBClient\Cursor;
use ArangoDBClient\ServerException;
use ArangoDBClient\Statement;
use Drupal\arangodb\ConnectionTrait;
use Drupal\arangodb\Utils;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

class Store implements KeyValueStoreExpirableInterface {

  use ConnectionTrait;

  /**
   * The name of the collection holding key and value pairs.
   */
  protected string $collectionName;

  /**
   * {@inheritdoc}
   */
  public function getCollectionName() {
    return $this->collectionName;
  }

  protected DocumentConverterInterface $documentConverter;

  public function getDocumentConverter(): DocumentConverterInterface {
    return $this->documentConverter;
  }

  public function setDocumentConverter(DocumentConverterInterface $documentConverter): static {
    $this->documentConverter = $documentConverter;

    return $this;
  }

  protected ?TimeInterface $time = NULL;

  public function getTime(): ?TimeInterface {
    return $this->time;
  }

  public function setTime(?TimeInterface $time): static {
    $this->time = $time;

    return $this;
  }

  public function __construct($collection) {
    $this->collectionName = $collection;
  }

  public function getDbCollectionNamePlaceholderValues(): array {
    return [
      '{{ collection }}' => $this->getCollectionName(),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function has($key) {
    $this->initDbConnection();
    $documents = $this->getDocuments([$key], FALSE);

    return isset($documents[$key]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function get($key, $default = NULL) {
    $values = $this->getMultiple([$key]);

    return $values[$key] ?? $default;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function getMultiple(array $keys) {
    $this->initDbConnection();

    $result = array_fill_keys($keys, NULL);
    $documents = $this->getDocuments($keys, FALSE);
    foreach ($documents as $key => $document) {
      $entry = $this->documentConverter->documentToEntry($document);
      $result[$key] = $entry['value'];
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function getAll() {
    $this->initDbConnection();

    $result = [];
    $documents = $this->getDocuments(NULL, FALSE);
    foreach ($documents as $key => $document) {
      $entry = $this->documentConverter->documentToEntry($document);
      $result[$key] = $entry['value'];
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function set($key, $value) {
    $this->initDbConnection();

    $this->doSet($key, $value, NULL);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function setWithExpire($key, $value, $expire) {
    $time = $this->getTime();
    if (!$time) {
      throw new \LogicException('::setTime() has to be called first');
    }

    $this->initDbConnection();
    $this->doSet($key, $value, $time->getCurrentTime() + $expire);
  }

  /**
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ServerException
   */
  protected function doSet(string $key, mixed $value, ?int $expire) {
    try {
      $this->doUpsert($key, $value, $expire);
    }
    catch (ServerException $error) {
      if ($error->getServerCode() === Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        $this->initDbCollection($this->getDbCollectionName());
        $this->doUpsert($key, $value, $expire);

        return;
      }

      throw $error;
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function setIfNotExists($key, $value) {
    $this->initDbConnection();

    return $this->doSetIfNotExists($key, $value, NULL);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function setWithExpireIfNotExists($key, $value, $expire) {
    $time = $this->getTime();
    if (!$time) {
      throw new \LogicException('::setTime() has to be called first');
    }

    return $this->doSetIfNotExists($key, $value, $time->getCurrentTime() + $expire);
  }

  /**
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ServerException
   */
  protected function doSetIfNotExists(string $key, mixed $value, ?int $expire): bool {
    $statementData = [
      'bindVars' => [
        '@dbCollection' => $this->getDbCollectionName(),
        'collection' => $this->getCollectionName(),
        'key' => $key,
        'value' => $this->documentConverter->getSerializer()->serialize($value),
      ],
    ];

    $queryInsert = [
      'collection: @collection',
      'key: @key',
      'value: @value',
    ];
    if ($expire !== NULL) {
      $queryInsert[] = 'expire: @expire';
      $statementData['bindVars']['expire'] = $expire;
    }

    $queryInsertAql = implode(",\n    ", $queryInsert);
    $statementData['query'] = <<< AQL
    UPSERT {
        collection: @collection,
        key: @key
    }
    INSERT {
        $queryInsertAql
    }
    UPDATE {}
    IN @@dbCollection
    RETURN {
        old: OLD
    }
    AQL;

    try {
      $result = $this->executeStatement($statementData);
    }
    catch (ServerException $error) {
      if ($error->getServerCode() !== Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        throw $error;
      }

      $this->initDbCollection($this->getDbCollectionName());
      $result = $this->executeStatement($statementData);
    }

    /** @var \ArangoDBClient\Document $record */
    $record = $result->current();

    return $record->get('old') === NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function setMultiple(array $data) {
    $this->initDbConnection();
    $this->doSetMultiple($data, NULL);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function setMultipleWithExpire(array $data, $expire) {
    $time = $this->getTime();
    if (!$time) {
      throw new \LogicException('');
    }

    $this->initDbConnection();
    $this->doSetMultiple($data, $time->getCurrentTime() + $expire);
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function doSetMultiple(array $keyValuePairs, ?int $expire) {
    if (!$keyValuePairs) {
      return;
    }

    $dbCollectionName = $this->getDbCollectionName();
    $serializer = $this->documentConverter->getSerializer();
    $existingDocuments = $this->getDocuments(array_keys($keyValuePairs), TRUE);
    // @todo Find a way to update multiple documents in one step.
    // WARNING: It is strange to update an expired key-value pair without
    // updating the expiration date as well, but this is how the
    // \Drupal\Core\KeyValueStore\DatabaseStorageExpirable::setMultiple() works.
    foreach ($existingDocuments as $key => $document) {
      $document->set('value', $serializer->serialize($keyValuePairs[$key]));
      if ($expire !== NULL) {
        $document->set('expire', $expire);
      }

      $this->dbDocumentHandler->update($document);
    }

    $newEntries = array_diff_key($keyValuePairs, $existingDocuments);
    if ($newEntries) {
      $newDocuments = [];
      $documentConverter = $this->getDocumentConverter();
      $collectionName = $this->getCollectionName();
      foreach ($newEntries as $key => $value) {
        $newDocuments[$key] = $documentConverter->entryToDocument(
          $collectionName,
          $key,
          $value,
          $expire,
        );
      }

      try {
        $this->dbDocumentHandler->insertMany($dbCollectionName, $newDocuments);
      }
      catch (ServerException $error) {
        if ($error->getServerCode() === Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
          $this->initDbCollection($this->getDbCollectionName());
          $this->dbDocumentHandler->insertMany($dbCollectionName, $newDocuments);

          return;
        }

        throw $error;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function delete($key) {
    $this->deleteMultiple([$key]);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteMultiple(array $keys) {
    if (!$keys) {
      return;
    }

    $this->initDbConnection();

    $statementData = [
      'query' => <<< AQL
      FOR doc in @@collection
        FILTER
          doc.collection == @collection
          AND
          doc.key IN @keys
        REMOVE doc IN @@collection
      AQL,
      'bindVars' => [
        '@collection' => $this->getDbCollectionName(),
        'collection' => $this->getCollectionName(),
        'keys' => $keys,
      ],
    ];

    try {
      $this->executeStatement($statementData);
    }
    catch (ServerException $error) {
      if ($error->getServerCode() !== Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        throw $error;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function deleteAll() {
    $this->initDbConnection();

    $statementData = [
      'query' => <<< AQL
      FOR doc in @@collection
        FILTER
          doc.collection == @collection
      REMOVE doc IN @@collection
      AQL,
      'bindVars' => [
        '@collection' => $this->getDbCollectionName(),
        'collection' => $this->getCollectionName(),
      ],
    ];

    try {
      $this->executeStatement($statementData);
    }
    catch (ServerException $error) {
      if ($error->getServerCode() !== Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        throw $error;
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   */
  public function rename($key, $new_key) {
    $this->initDbConnection();

    $statementData = [
      'query' => <<< AQL
      FOR doc in @@dbCollection
        FILTER
          doc.collection == @collection
          AND
          doc.key == @oldKey
        UPDATE doc WITH { key: @newKey } IN @@dbCollection
      AQL,
      'bindVars' => [
        '@dbCollection' => $this->getDbCollectionName(),
        'collection' => $this->getCollectionName(),
        'oldKey' => $key,
        'newKey' => $new_key,
      ],
    ];

    try {
      $this->executeStatement($statementData);
    }
    catch (ServerException $error) {
      if ($error->getServerCode() !== Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        throw $error;
      }
    }
  }

  /**
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ServerException
   */
  protected function doUpsert(string $key, mixed $value, ?int $expire): void {
    $collectionName = $this->getCollectionName();

    $statementData = [
      'query' => <<< AQL
      UPSERT
          @condition
      INSERT
          @insert
      UPDATE
          @update
      IN @@dbCollection
      AQL,
      'bindVars' => [
        '@dbCollection' => $this->getDbCollectionName(),
        'condition' => [
          'collection' => $collectionName,
          'key' => $key,
        ],
        'insert' => $this
          ->documentConverter
          ->entryToDocument($collectionName, $key, $value, $expire)
          ->getAllForInsertUpdate(),
      ],
    ];

    if ($expire !== NULL) {
      $statementData['bindVars']['insert']['expire'] = $expire;
    }

    $statementData['bindVars']['update'] = $statementData['bindVars']['insert'];
    unset(
      $statementData['bindVars']['update']['collection'],
      $statementData['bindVars']['update']['key'],
    );

    $this->executeStatement($statementData);
  }

  /**
   * @phpstan-return array<string, \ArangoDBClient\Document>
   *
   * @throws \ArangoDBClient\Exception
   */
  protected function getDocuments(?array $keys, bool $includeExpired): array {
    $queryFilters = [];
    $statementData = [
      'bindVars' => [
        '@dbCollection' => $this->getDbCollectionName(),
      ],
    ];

    $queryFilters[] = 'doc.collection == @collection';
    $statementData['bindVars']['collection'] = $this->getCollectionName();

    if ($keys !== NULL) {
      $queryFilters[] = 'doc.key IN @keys';
      $statementData['bindVars']['keys'] = $keys;
    }

    $time = $this->getTime();
    if (!$includeExpired && $time) {
      $queryFilters[] = '(doc.expire == null || doc.expire > @now)';
      $statementData['bindVars']['now'] = $time->getCurrentTime();
    }

    $queryFiltersAql = implode("\n    AND\n    ", $queryFilters);
    $statementData['query'] = <<< AQL
    FOR doc IN @@dbCollection
      FILTER
        $queryFiltersAql
      RETURN doc
    AQL;

    $documents = [];
    try {
      $result = $this->executeStatement($statementData);
    }
    catch (ServerException $error) {
      if ($error->getServerCode() === Utils::SERVER_CODE_COLLECTION_NOT_EXISTS) {
        return [];
      }

      throw $error;
    }

    /** @var \ArangoDBClient\Document $document */
    foreach ($result as $document) {
      $documents[$document->get('key')] = $document;
    }

    return $documents;
  }

  /**
   * @param array<string, mixed> $statementData
   *
   * @throws \ArangoDBClient\Exception
   */
  protected function executeStatement(array $statementData): Cursor {
    $statement = new Statement(
      $this->dbConnection,
      $statementData + $this->getExecuteStatementData(),
    );

    $statement->setDocumentClass($this->documentConverter->getDocumentClass());

    return $statement->execute();
  }

  /**
   * @phpstan-return cache-backend-arangodb-execute-statement-data
   * @psalm-return ExecuteStatementData
   */
  protected function getExecuteStatementData(): array {
    return [
      'batchSize' => 1000,
      'sanitize' => TRUE,
    ];
  }

}
