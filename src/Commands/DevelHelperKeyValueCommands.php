<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drush\Commands\DrushCommands;
use Robo\Common\BuilderAwareTrait;
use Robo\Contract\BuilderAwareInterface;

class DevelHelperKeyValueCommands extends DrushCommands implements BuilderAwareInterface {

  use BuilderAwareTrait;

  protected KeyValueFactoryInterface $keyValueFactorySimple;

  protected KeyValueExpirableFactoryInterface $keyValueFactoryExpirable;

  public function __construct(
    KeyValueFactoryInterface $keyValueFactorySimple,
    KeyValueExpirableFactoryInterface $keyValueFactoryExpirable,
  ) {
    $this->keyValueFactorySimple = $keyValueFactorySimple;
    $this->keyValueFactoryExpirable = $keyValueFactoryExpirable;
    parent::__construct();
  }

  /**
   * Calls ::has().
   *
   * @param string $collection
   *   Collection.
   * @param string $key
   *   Key.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:keyvalue:simple:has
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleHasExecute(
    string $collection,
    string $key,
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    $keyValueStore = $this->keyValueFactorySimple->get($collection);

    $data = [
      'return' => $keyValueStore->has($key),
    ];
    $exitCode = 0;

    return CommandResult::dataWithExitCode($data, $exitCode);
  }

  /**
   * Calls ::get().
   *
   * @param string $collection
   *   Collection.
   * @param string $key
   *   Key.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:keyvalue:simple:get
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleGetExecute(
    string $collection,
    string $key,
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    return CommandResult::data(
      $this
        ->keyValueFactorySimple
        ->get($collection)
        ->get($key),
    );
  }

  /**
   * Calls ::getMultiple().
   *
   * @param string $collection
   *   Collection.
   * @param string[] $keys
   *   Keys.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:keyvalue:simple:get-multiple
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleGetMultipleExecute(
    string $collection,
    array $keys,
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    return CommandResult::data(
      $this
        ->keyValueFactorySimple
        ->get($collection)
        ->getMultiple($keys),
    );
  }

  /**
   * Calls ::getAll().
   *
   * @param string $collection
   *   Collection.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:keyvalue:simple:get-all
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleGetAllExecute(
    string $collection,
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    return CommandResult::data(
      $this
        ->keyValueFactorySimple
        ->get($collection)
        ->getAll(),
    );
  }

  /**
   * Calls ::set().
   *
   * @param string $collection
   *   Collection.
   * @param string $key
   *   Key.
   * @param string $type
   *   Value data type.
   *
   * @command arangodb:keyvalue:simple:set
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleSetExecute(
    string $collection,
    string $key,
    string $type = 'array',
  ): void {
    $this
      ->keyValueFactorySimple
      ->get($collection)
      ->set($key, $this->getValueByType($type));
  }

  /**
   * Calls ::setWithExpire().
   *
   * @param string $collection
   *   Collection.
   * @param string $key
   *   Key.
   * @param string $type
   *   Value data type.
   * @param string $ttl
   *   Time To Live in seconds.
   *
   * @command arangodb:keyvalue:expirable:set-with-expire
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueExpirableSetWithExpireExecute(
    string $collection,
    string $key,
    string $type = 'array',
    string $ttl = '120',
  ): void {
    $this
      ->keyValueFactoryExpirable
      ->get($collection)
      ->setWithExpire($key, $this->getValueByType($type), (int) $ttl);
  }

  /**
   * Calls ::set().
   *
   * @param string $collection
   *   Collection.
   * @param string $key
   *   Key.
   * @param string $type
   *   Data type.
   *
   * @command arangodb:keyvalue:simple:set-if-not-exists
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleSetIfNotExistsExecute(
    string $collection,
    string $key,
    string $type = 'array',
  ): CommandResult {
    $keyValueStorage = $this
      ->keyValueFactorySimple
      ->get($collection);
    $data = [
      'value_before' => $keyValueStorage->get($key),
      'value_attempt' => $this->getValueByType($type),
    ];

    $data['setIfNotExists.return'] = $keyValueStorage->setIfNotExists($key, $data['value_attempt']);

    $data['value_after'] = $keyValueStorage->get($key);

    return CommandResult::data($data);
  }

  /**
   * Calls ::set().
   *
   * @param string $collection
   *   Collection.
   * @param string $keyPrefix
   *   Key prefix.
   * @param string[] $types
   *   Value types.
   *
   * @command arangodb:keyvalue:simple:set-multiple
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleSetMultipleExecute(
    string $collection,
    string $keyPrefix,
    array $types,
  ): CommandResult {
    $amount = count($types);
    $keySuffixLength = strlen((string) $amount);

    $keyValuePairs = [];
    foreach ($types as $i => $type) {
      $key = sprintf(
        "%s-%0{$keySuffixLength}s",
        $keyPrefix,
        $i + 1,
      );
      $keyValuePairs[$key] = $this->getValueByType($type);
    }

    $keyValueStorage = $this
      ->keyValueFactorySimple
      ->get($collection);

    $keyValueStorage->setMultiple($keyValuePairs);

    return CommandResult::data($keyValueStorage->getMultiple(array_keys($keyValuePairs)));
  }

  /**
   * Calls ::delete().
   *
   * @param string $collection
   *   Collection.
   * @param string $key
   *   Key.
   *
   * @command arangodb:keyvalue:simple:delete
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleDeleteExecute(
    string $collection,
    string $key,
  ): void {
    $keyValueStore = $this->keyValueFactorySimple->get($collection);

    $keyValueStore->delete($key);
  }

  /**
   * Calls ::deleteMultiple().
   *
   * @param string $collection
   *   Collection.
   * @param string[] $keys
   *   Keys.
   *
   * @command arangodb:keyvalue:simple:delete-multiple
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleDeleteMultipleExecute(
    string $collection,
    array $keys,
  ): CommandResult {
    $keyValueStore = $this->keyValueFactorySimple->get($collection);

    $data = [
      'values_before' => $keyValueStore->getMultiple($keys),
    ];
    $keyValueStore->deleteMultiple($keys);
    $data['values_after'] = $keyValueStore->getMultiple($keys);

    return CommandResult::data($data);
  }

  /**
   * Calls ::deleteAll().
   *
   * @param string $collection
   *   Collection.
   *
   * @command arangodb:keyvalue:simple:delete-all
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleDeleteAllExecute(string $collection): void {
    $keyValueStore = $this->keyValueFactorySimple->get($collection);

    $keyValueStore->deleteAll();
  }

  /**
   * Calls ::rename().
   *
   * @param string $collection
   *   Collection.
   * @param string $oldKey
   *   Key.
   * @param string $newKey
   *   Key.
   *
   * @phpstan-param array<string, mixed> $options
   *
   * @command arangodb:keyvalue:simple:rename
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdKeyValueSimpleRenameExecute(
    string $collection,
    string $oldKey,
    string $newKey,
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    $keyValueStore = $this->keyValueFactorySimple->get($collection);

    $data = [
      'old.key' => $oldKey,
      'old.before.has' => $keyValueStore->has($oldKey),
      'old.before.get' => $keyValueStore->get($oldKey),
      'new.key' => $newKey,
      'new.before.has' => $keyValueStore->has($newKey),
      'new.before.get' => $keyValueStore->get($newKey),
    ];

    $keyValueStore->rename($oldKey, $newKey);

    $data += [
      'old.after.has' => $keyValueStore->has($oldKey),
      'old.after.get' => $keyValueStore->get($oldKey),
      'new.after.has' => $keyValueStore->has($newKey),
      'new.after.get' => $keyValueStore->get($newKey),
    ];

    return CommandResult::data($data);
  }

  protected function getValueByType(string $type): mixed {
    return match($type) {
      'bool' => TRUE,
      'int' => (int) date('s'),
      'float' => ((int) date('s') + 1) / 142,
      'string' => date('Y-m-d H:i:s'),
      'object' => (object) [
        'now' => date('Y-m-d H:i:s'),
        'foo' => 'bar',
      ],
      default => [
        'now' => date('Y-m-d H:i:s'),
        'foo' => 'bar',
      ],
    };
  }

}
