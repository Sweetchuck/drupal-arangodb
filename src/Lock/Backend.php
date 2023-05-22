<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Lock;

use Drupal\arangodb\ConnectionTrait;
use Drupal\Core\Database\IntegrityConstraintViolationException;
use Drupal\Core\Lock\LockBackendAbstract;

class Backend extends LockBackendAbstract {

  use ConnectionTrait;

  protected function getCollectionName(): string {
    return 'lock';
  }

  /**
   * {@inheritdoc}
   */
  public function acquire($name, $timeout = 30.0) {
    $name = $this->normalizeName($name);

    // Insure that the timeout is at least 1 ms.
    $timeout = max($timeout, 0.001);
    $expire = microtime(TRUE) + $timeout;
    if (isset($this->locks[$name])) {
      // Try to extend the expiration of a lock we already acquired.
      $success = (bool) $this->database->update('semaphore')
        ->fields(['expire' => $expire])
        ->condition('name', $name)
        ->condition('value', $this->getLockId())
        ->execute();
      if (!$success) {
        // The lock was broken.
        unset($this->locks[$name]);
      }
      return $success;
    }
    else {
      // Optimistically try to acquire the lock, then retry once if it fails.
      // The first time through the loop cannot be a retry.
      $retry = FALSE;
      // We always want to do this code at least once.
      do {
        try {
          $this->database->insert('semaphore')
            ->fields([
              'name' => $name,
              'value' => $this->getLockId(),
              'expire' => $expire,
            ])
            ->execute();
          // We track all acquired locks in the global variable.
          $this->locks[$name] = TRUE;
          // We never need to try again.
          $retry = FALSE;
        }
        catch (IntegrityConstraintViolationException $e) {
          // Suppress the error. If this is our first pass through the loop,
          // then $retry is FALSE. In this case, the insert failed because some
          // other request acquired the lock but did not release it. We decide
          // whether to retry by checking lockMayBeAvailable(). This will clear
          // the offending row from the database table in case it has expired.
          $retry = $retry ? FALSE : $this->lockMayBeAvailable($name);
        }
        catch (\Exception $e) {
          // Create the semaphore table if it does not exist and retry.
          if ($this->ensureTableExists()) {
            // Retry only once.
            $retry = !$retry;
          }
          else {
            throw $e;
          }
        }
        // We only retry in case the first attempt failed, but we then broke
        // an expired lock.
      } while ($retry);
    }
    return isset($this->locks[$name]);
  }

  /**
   * {@inheritdoc}
   */
  public function lockMayBeAvailable($name) {
    // @todo Implement lockMayBeAvailable() method.
  }

  /**
   * {@inheritdoc}
   */
  public function release($name) {
    // @todo Implement release() method.
  }

  /**
   * {@inheritdoc}
   */
  public function releaseAll($lockId = NULL) {
    // @todo Implement releaseAll() method.
  }

  protected function normalizeName(string $name): string {
    return $name;
  }

}
