<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

class MainExpirableFactory extends MainSimpleFactory implements KeyValueExpirableFactoryInterface {

  protected string $defaultFactoryServiceId = 'keyvalue.expirable.database';

}
