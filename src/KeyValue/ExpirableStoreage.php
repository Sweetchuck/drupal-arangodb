<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

class ExpirableStoreage extends PermanentStorage implements KeyValueStoreExpirableInterface {

  /**
   * {@inheritdoc}
   */
  public function setWithExpire($key, $value, $expire) {
    // @todo Implement setWithExpire() method.
  }

  /**
   * {@inheritdoc}
   */
  public function setWithExpireIfNotExists($key, $value, $expire): bool {
    // @todo Implement setWithExpireIfNotExists() method.
  }

  /**
   * {@inheritdoc}
   */
  public function setMultipleWithExpire(array $data, $expire) {
    // @todo Implement setMultipleWithExpire() method.
  }

}
