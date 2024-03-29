<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Commands;

use ArangoDBClient\CollectionHandler;
use ArangoDBClient\Document;
use ArangoDBClient\DocumentHandler;
use ArangoDBClient\ServerException;
use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\arangodb\ConnectionFactoryInterface;
use Drush\Commands\DrushCommands;
use Robo\Collection\CollectionBuilder;
use Robo\Common\BuilderAwareTrait;
use Robo\Contract\BuilderAwareInterface;

class DevelHelperCommonCommands extends DrushCommands implements BuilderAwareInterface {

  use BuilderAwareTrait;

  protected ConnectionFactoryInterface $connectionFactory;

  public function __construct(ConnectionFactoryInterface $connectionFactory) {
    $this->connectionFactory = $connectionFactory;
    parent::__construct();
  }

  /**
   * List all collection with the given name $prefix.
   *
   * @param string $prefix
   *   Example values: "cache_", "queue_core_", "queue_advanced_", "test_".
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:collection:list
   *
   * @option string $format
   *   Default: yaml
   * @option string $connection-name
   *   Default: default
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdCollectionListExecute(
    string $prefix,
    array $options = [
      'connection-name' => 'default',
    ],
  ): CommandResult {
    // @todo Validate connection-name.
    $connection = $this->connectionFactory->get($options['connection-name']);
    $collectionHandler = new CollectionHandler($connection);

    $exitCode = 0;
    try {
      $collections = $this->getCollectionsByNamePrefix($collectionHandler, $prefix);
    }
    catch (\Exception $e) {
      $this->logger()->error($e->getMessage());
      $exitCode = 1;
      $collections = [];
    }

    $collectionNames = array_keys($collections);
    sort($collectionNames, \SORT_NATURAL);

    return CommandResult::dataWithExitCode(
      $collectionNames,
      $exitCode,
    );
  }

  /**
   * Delete all collection with the given name $prefix.
   *
   * @param string $prefix
   *   Example values: "cache_", "queue_core_", "queue_advanced_", "test_".
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:collection:delete
   *
   * @option string $connection-name
   *   Default: default
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdCollectionDeleteExecute(
    string $prefix,
    array $options = [
      'connection-name' => 'default',
    ],
  ): CollectionBuilder {
    return $this
      ->collectionBuilder()
      ->addCode(function () use ($prefix, $options): int {
        $logger = $this->logger();
        $connection = $this->connectionFactory->get($options['connection-name']);
        $collectionHandler = new CollectionHandler($connection);
        try {
          foreach ($this->getCollectionsByNamePrefix($collectionHandler, $prefix) as $collectionName => $collection) {
            $logger->info('Drop collection: {collection.name}', ['collection.name' => $collectionName]);
            $collectionHandler->drop($collectionName);
          }
        }
        catch (\Exception $e) {
          $logger->error($e->getMessage());

          return 1;
        }

        return 0;
      });
  }

  /**
   * Create a new collection by insert.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:collection:create-by-insert
   *
   * @option string $connection-name
   *   Name of the ArangoDB connection.
   *
   * @throws \Exception
   */
  public function cmdArangodbCollectionCreateByInsertExecute(
    array $options = [
      'connection-name' => 'default',
    ]
  ): CommandResult {
    $connection = $this->connectionFactory->get($options['connection-name']);
    $documentHandler = new DocumentHandler($connection);

    $document = new Document();
    $document->set('key01', 'value');

    try {
      $documentHandler->insert(
        'test_foo',
        $document,
        [
          'createCollection' => TRUE,
        ],
      );
    }
    catch (ServerException $e) {
      var_dump($e->getCode());
      var_dump($e->getServerCode());

      return CommandResult::exitCode(1);
    }

    var_dump($document);

    return CommandResult::exitCode(0);
  }

  /**
   * @phpstan-return array<string>
   *
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ClientException
   */
  protected function getCollectionsByNamePrefix(CollectionHandler $collectionHandler, string $prefix): array {
    return array_filter(
      $collectionHandler->getAllCollections(),
      $this->getArrayFilterByKeyPrefix($prefix),
      \ARRAY_FILTER_USE_KEY,
    );
  }

  protected function getArrayFilterByKeyPrefix(string $keyPrefix): callable {
    return function (string $key) use ($keyPrefix): bool {
      return !$keyPrefix || str_starts_with($key, $keyPrefix);
    };
  }

}
