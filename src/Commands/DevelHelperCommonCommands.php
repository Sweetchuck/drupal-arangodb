<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Commands;

use ArangoDBClient\CollectionHandler;
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
   *   Example values: "test_cache_", "test_queue_", "cache_", "queue_".
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

    return CommandResult::dataWithExitCode(
      array_keys($collections),
      $exitCode,
    );
  }

  /**
   * Delete all collection with the given name $prefix.
   *
   * @param string $prefix
   *   Example values: "test_cache_", "test_queue_", "cache_", "queue_".
   *
   * @command arangodb:collection:delete
   *
   * @option string $connection-name
   *    Default: default
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
      return str_starts_with($key, $keyPrefix);
    };
  }

}
