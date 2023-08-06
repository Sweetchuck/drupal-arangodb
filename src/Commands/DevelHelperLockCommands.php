<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Commands;

use ArangoDBClient\Statement;
use Consolidation\AnnotatedCommand\CommandResult;
use Drupal\arangodb\ConnectionFactory;
use Drupal\Core\Lock\LockBackendInterface;
use Drush\Commands\DrushCommands;
use Robo\Common\BuilderAwareTrait;
use Robo\Contract\BuilderAwareInterface;

class DevelHelperLockCommands extends DrushCommands implements BuilderAwareInterface {

  use BuilderAwareTrait;

  protected ConnectionFactory $connectionFactory;

  protected string $connectionName;

  protected LockBackendInterface $lockRequest;

  protected LockBackendInterface $lockPersistent;

  public function __construct(
    ConnectionFactory $connectionFactory,
    string $connectionName,
    LockBackendInterface $lockRequest,
    LockBackendInterface $lockPersistent,
  ) {
    $this->connectionFactory = $connectionFactory;
    $this->connectionName = $connectionName;
    $this->lockRequest = $lockRequest;
    $this->lockPersistent = $lockPersistent;
    parent::__construct();
  }

  /**
   * Call ::acquire on a request lock backend.
   *
   * @param string $name
   *   Name of the lock.
   * @param string $timeout
   *   Float in seconds.
   * @param string $sleep
   *   Integer in seconds.
   *
   * @command arangodb:lock-request:acquire
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdLockRequestAcquireExecute(
    string $name,
    string $timeout = '2',
    string $sleep = '4',
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    $timeoutFloat = (float) $timeout;
    $sleepInt = (int) $sleep;
    $data = [];
    $exitCode = 0;

    $data['first'] = $this->lockRequest->acquire($name, $timeoutFloat);
    sleep($sleepInt);
    $data['second'] = $this->lockRequest->acquire($name, $timeoutFloat);

    return CommandResult::dataWithExitCode($data, $exitCode);
  }

  /**
   * Call ::acquire on a persistent lock backend.
   *
   * @param string $name
   *   Name of the lock.
   * @param string $timeout
   *   Float in seconds.
   * @param string $sleep
   *   Integer in seconds.
   *
   * @command arangodb:lock-persistent:acquire
   *
   * @bootstrap full
   *
   * @hidden
   */
  public function cmdLockPersistentAcquireExecute(
    string $name,
    string $timeout = '2',
    string $sleep = '4',
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    $timeoutFloat = (float) $timeout;
    $sleepInt = (int) $sleep;
    $data = [];
    $exitCode = 0;

    // Both should be TRUE, because a persistent lock will be created,
    // or an existing one extended,
    // doesn't matter what the $timeout and $sleep values are.
    $data['first'] = $this->lockPersistent->acquire($name, $timeoutFloat);
    sleep($sleepInt);
    $data['second'] = $this->lockPersistent->acquire($name, $timeoutFloat);

    return CommandResult::dataWithExitCode($data, $exitCode);
  }

  /**
   * List all the locks.
   *
   * @command arangodb:lock:list
   *
   * @bootstrap full
   *
   * @hidden
   *
   * @throws \ArangoDBClient\Exception
   */
  public function cmdLockListExecute(
    array $options = [
      'format' => 'yaml',
    ],
  ): CommandResult {
    $data = [];
    $exitCode = 0;

    $query = <<< AQL
    FOR doc IN @@collection
      SORT
        doc.name
      RETURN doc
    AQL;

    $statement = new Statement(
      $this->connectionFactory->get('default'),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => 'lock',
        ],
      ],
    );
    $result = $statement->execute();
    /** @var \ArangoDBClient\Document $document */
    foreach ($result as $document) {
      $data[$document->get('name')] = $document->getAll(['_includeInternals' => TRUE]);
    }

    return CommandResult::dataWithExitCode($data, $exitCode);
  }

}
