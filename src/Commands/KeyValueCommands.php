<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\arangodb\KeyValue\Store as ArangodbKeyValueStore;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection as DatabaseConnection;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drush\Commands\DrushCommands;
use Robo\Collection\CallableTask;
use Robo\Collection\CollectionBuilder;
use Robo\Collection\Tasks as CollectionTasks;
use Robo\Common\BuilderAwareTrait;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\TaskInterface;
use Robo\State\Data as RoboState;
use Robo\TaskAccessor;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class KeyValueCommands extends DrushCommands implements BuilderAwareInterface {

  use TaskAccessor;
  use BuilderAwareTrait;
  use CollectionTasks;

  protected DatabaseConnection $defaultDb;

  protected KeyValueFactoryInterface $keyValueFactorySimple;

  protected KeyValueExpirableFactoryInterface $keyValueFactoryExpirable;

  protected TimeInterface $time;

  public function __construct(
    DatabaseConnection $defaultDb,
    KeyValueFactoryInterface $keyValueFactorySimple,
    KeyValueExpirableFactoryInterface $keyValueFactoryExpirable,
    TimeInterface $time,
  ) {
    $this->defaultDb = $defaultDb;
    $this->keyValueFactorySimple = $keyValueFactorySimple;
    $this->keyValueFactoryExpirable = $keyValueFactoryExpirable;
    $this->time = $time;
    parent::__construct();

  }

  /**
   * @hook interact arangodb:keyvalue:copy-from-default
   */
  public function cmdKeyValueCopyFromDefaultInteract(
    InputInterface $input,
    OutputInterface $output,
  ): void {
    $io = new SymfonyStyle($input, $output);

    $storeType = $input->getArgument('storeType');
    if (empty($storeType)) {
      $storeType = $io->choice(
        'Which store type the key-value pairs should be copied from?',
        $this->getStoreTypes(),
      );
      $input->setArgument('storeType', $storeType);
    }
  }

  /**
   * @hook validate arangodb:keyvalue:copy-from-default
   */
  public function cmdKeyValueCopyFromDefaultValidate(CommandData $commandData): void {
    $input = $commandData->input();
    $storeType = $input->getArgument('storeType');
    $storeTypes = $this->getStoreTypes();
    $storeTypesList = implode(', ', $storeTypes);
    if (!in_array($storeType, $storeTypes)) {
      throw new \InvalidArgumentException("Value '$storeType' provided for storeType argument is invalid. Allowed values: $storeTypesList");
    }
  }

  /**
   * Copies key-value pairs from the default database into ArangoDB.
   *
   * @param string $storeType
   *   Allowed values: simple, expirable.
   *
   * @command arangodb:keyvalue:copy-from-default
   */
  public function cmdKeyValueCopyFromDbExecute(string $storeType): CollectionBuilder {
    $taskForEach = $this->taskForEach();
    $taskForEach
      ->iterationMessage('Copy values of {key} KeyValue collection from default DB into ArangoDB')
      ->deferTaskConfiguration('setIterable', 'stores')
      ->withBuilder(function (CollectionBuilder $builder, string $collectionName, $store) use ($taskForEach): void {
        $state = $taskForEach->getState();

        $builder
          ->addCode(function () use ($state, $collectionName, $store): int {
            $result = $this
              ->defaultDb
              ->select($state['srcTableName'], 'kv')
              ->fields('kv')
              ->condition('kv.collection', $collectionName)
              ->orderBy('kv.name')
              ->execute();
            $numOfRows = 0;
            /* @noinspection PhpComposerExtensionStubsInspection */
            foreach ($result->fetchAll(\PDO::FETCH_ASSOC) as $row) {
              // phpcs:disable DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize
              $row['value'] = unserialize($row['value']);
              // phpcs:enable DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize
              if (!empty($row['expire'])) {
                $row['expire'] = max(0, $row['expire'] - $this->time->getCurrentTime());
              }

              /** @var \Drupal\arangodb\KeyValue\Store $store */
              if (isset($row['expire'])) {
                $store->setWithExpire($row['name'], $row['value'], $row['expire']);
              }
              else {
                $store->set($row['name'], $row['value']);
              }

              $numOfRows++;
            }

            $this->logger()->notice(
              '{numOfRows} key-value pairs from collection {collectionName} have been copied into {store.dbCollectionName}',
              [
                'numOfRows' => $numOfRows,
                'collectionName' => $collectionName,
                'store.dbCollectionName' => $store->getDbCollectionName(),
              ],
            );

            return 0;
          });
      });

    $cb = $this->collectionBuilder();
    $cb
      ->addCode(function (RoboState $state) use ($storeType): int {
        $state['storeType'] = $storeType;
        $state['srcTableName'] = $state['storeType'] === 'simple' ? 'key_value' : 'key_value_expire';

        return 0;
      })
      ->addTask($this->getTaskCollectCollectionNames($cb, 'storeType', 'stores'))
      ->addCode(function (RoboState $state): int {
        if ($state['stores']) {
          return 0;
        }

        $this->logger()->notice(
          'There are no {storeType} KeyValue collections which should be stored in ArangoDB',
          [
            'storeType' => $state['storeType'],
          ],
        );

        return 0;
      })
      ->addTask($taskForEach);

    return $cb;
  }

  protected function getTaskCollectCollectionNames(
    TaskInterface $reference,
    string $storeTypeStateKey,
    string $dstStateKey,
  ): TaskInterface {
    return new CallableTask(
      function (RoboState $state) use ($storeTypeStateKey, $dstStateKey): int {
        $storeType = $state[$storeTypeStateKey];

        $select = $this
          ->defaultDb
          ->select($state['srcTableName'], 'kv')
          ->distinct()
          ->fields('kv', ['collection'])
          ->orderBy('collection');

        try {
          $result = $select->execute();
        }
        catch (\Exception $error) {
          $this->logger()->error($error->getMessage());

          return 1;
        }

        $state[$dstStateKey] = [];
        foreach ($result->fetchAllKeyed(0, 0) as $collectionName) {
          $store = $storeType === 'simple' ?
            $this->keyValueFactorySimple->get($collectionName)
            : $this->keyValueFactoryExpirable->get($collectionName);
          if ($store instanceof ArangodbKeyValueStore) {
            $state[$dstStateKey][$collectionName] = $store;
          }
        }

        return 0;
      },
      $reference,
    );
  }

  /**
   * @phpstan-return array<string, string>
   */
  protected function getStoreTypes(): array {
    return [
      'simple' => 'simple',
      'expirable' => 'expirable',
    ];
  }

}
