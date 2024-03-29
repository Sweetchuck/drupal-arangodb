# ArangoDB integrator

This module uses ArangoDB as data storage for different services.


## Features

| Feature                  | status             |
|--------------------------|--------------------|
| [Cache backend]          | &#x2705; POC ready |
| [Queue core]             | &#x2705; POC ready |
| [Queue advanced]         | &#x2705; POC ready |
| [Logger]                 | &#x2705; POC ready |
| [Lock]                   | &#x2705; POC ready |
| [Flood]                  | &#x2705; POC ready |
| [KeyValue]               | &#x2705; POC ready |
| [State]                  | See KeyValue       |
| [Session]                | not yet            |
| [SearchAPI server]       | not yet            |
| [Config entity storage]  | not yet            |
| [Content entity storage] | not yet            |
| [Full database driver]   | not yet            |


## How to setup ArangoDB connections

One or more connection can be defined. Usually the `endpoint` and/or the
`database` is the difference between these connections.

This parameter is used by the `arangodb.connection_factory` service.

**DRUPAL_ROOT/sites/*/services.arangodb.yml**

```yaml
parameters:
  arangodb.connection_options:
    default:
      endpoint: 'tcp://127.0.0.1:8529'
      Connection: 'Close'
      timeout: 3
      Reconnect: true
      policy: 'last'
      createCollection: false
      AuthType: 'Basic'
      AuthUser: 'me'
      AuthPasswd: 'mySecret01'
      database: 'cache'
```

Connection credentials (username/password) also can be defined as service parameters,
but easier to manage them in the `settings.local.php`. See below.
Connection options defined in `settings.local.php` have a higher priority over the ones
defined in `services.*.yml#/parameters/arangodb.connection_options`.

**DRUPAL_ROOT/sites/*/settings.local.php**

```php
use ArangoDBClient\ConnectionOptions as ArangoDbConnectionOptions;
use ArangoDBClient\DefaultValues as ArangoDbDefaultValues;

$settings['arangodb.connection_options'] = [
  'default' => [
    ArangoDbConnectionOptions::OPTION_ENDPOINT => getenv('APP_ARANGODB_CONNECTION_DEFAULT_ENDPOINT') ?: 'tcp://127.0.0.1:8529',
    ArangoDbConnectionOptions::OPTION_AUTH_TYPE => ArangoDbDefaultValues::DEFAULT_AUTH_TYPE,
    ArangoDbConnectionOptions::OPTION_AUTH_USER => getenv('APP_ARANGODB_CONNECTION_DEFAULT_USER') ?: getenv('USER'),
    ArangoDbConnectionOptions::OPTION_AUTH_PASSWD => getenv('APP_ARANGODB_CONNECTION_DEFAULT_PASSWD') ?: 'admin',
    ArangoDbConnectionOptions::OPTION_DATABASE => getenv('APP_ARANGODB_CONNECTION_DEFAULT_DATABASE') ?: $databases['default']['default']['database'],
  ],
];
```


## Feature - Cache backend

**Remaining tasks:**

1. tests
2. documentation

**settings.local.php**

```php
$settings['cache']['default'] = 'cache.backend.arangodb_default';
$settings['cache']['bins']['config'] = 'cache.backend.arangodb_no_serializer';
```


## Feature - Queue core

**Remaining tasks:**

1. tests
2. documentation

**settings.local.php**

```php
$settings['queue_default'] = 'arangodb.queue_core.storage.shared';
$settings['queue_reliable_service_foo'] = 'arangodb.queue_core.storage.dedicated';
//$settings['queue_service_bar'] = 'arangodb.queue_core.storage.dedicated';
```


## Feature - Queue advanced

The integration is done by implementing an `AdvancedQueue/Backend` plugin.


## Feature - Logger

Logger in the `arangodb` module isn't activated by default.
In order to activate the logger, the following line has to be added to the `settings.local.php`.

**settings.local.php**

```php
$settings['container_yamls']['arangodb.logger'] = 'modules/contrib/arangodb/arangodb.services.logger.yml';
```


## Feature - Lock

To store locks with `arangodb` module isn't activated by default.
In order to use ArangoDB as lock storage, the following line has to be added to the `settings.local.php`.

**settings.local.php**

```php
$settings['container_yamls']['arangodb.lock'] = 'modules/contrib/arangodb/arangodb.services.lock.yml';
```


## Feature - Flood

To store flood events with `arangodb` module isn't activated by default.
In order to use ArangoDB as flood event storage, the following line has to be added to the `settings.local.php`.

**settings.local.php**

```php
$settings['container_yamls']['arangodb.flood'] = 'modules/contrib/arangodb/arangodb.services.flood.yml';
```


## Feature - KeyValue

Tasks:
* [x] Basic implementation
* [ ] write more tests

### Feature - KeyValue - configuration

ArangoDB module defines a few KeyValue factory services in the `arangodb.services.yml`. \
These service names can be listed by the following command:
```
yq eval \
  '.services | keys | .[] | select(test("^arangodb\.keyvalue\.factory\."))' \
  'arangodb.services.yml'
```

> - `arangodb.keyvalue.factory.simple_shared_php`
> - `arangodb.keyvalue.factory.simple_shared_loafer`
> - `arangodb.keyvalue.factory.simple_dedicated_php`
> - `arangodb.keyvalue.factory.simple_dedicated_loafer`
> - `arangodb.keyvalue.factory.expirable_shared_php`
> - `arangodb.keyvalue.factory.expirable_shared_loafer`
> - `arangodb.keyvalue.factory.expirable_dedicated_php`
> - `arangodb.keyvalue.factory.expirable_dedicated_loafer`

A „KeyValue collection” can be paired with a „KeyValue storage factory” in an additional `services.*.yml` file.
```yaml
parameters:
  factory.keyvalue:
    my_module_01_collection_01: 'arangodb.keyvalue.factory.simple_shared_php'
  factory.keyvalue.expirable:
    my_module_01_collection_02: 'arangodb.keyvalue.factory.expirable_shared_php'
```

Usage:
```php
/** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $simple_storage */
$simple_storage = \Drupal::getContainer()
  ->get('keyvalue')
  ->get('my_module_01_collection_01');
$simple_storage->set('my_key_01', 'my_data_01');

/** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $expirable_storage */
$expirable_storage = \Drupal::getContainer()
  ->get('keyvalue.expirable')
  ->get('my_module_01_collection_02');
$expirable_storage->setWithExpire('my_key_02', 'my_data_02', 3600);
```

The „KeyValue collection” identifiers are defined by modules on demand. \
There is no hook or any kind of API to get a list of the possible „KeyValue collection” identifiers.

A „KeyValue collection” which has no dedicated „KeyValue storage factory” will uses the default factory. \
By default the default factory is `keyvalue.database`. \
See `\Drupal\Core\KeyValueStore\KeyValueFactory::DEFAULT_SERVICE`. \
The default factory can be changed in a `services.*.yml` file.
```yaml
parameters:
  factory.keyvalue:
    default: '<name of a keyvalue factory service>'
  factory.keyvalue.expirable:
    # https://www.drupal.org/project/drupal/issues/2769955
    keyvalue_expirable_default: '<name of a keyvalue factory service>'
```

I am not sure if a KeyValue factory service from custom module works during the installation.


## Feature - State

In the background State uses the KeyValue as storage backend. \
The KeyValue collection name is `state`.


## Feature - Session

@todo list

1. Create an individual package `sweetchuck/php-session-arangodb`
   1. [PHP Session](https://www.php.net/manual/en/book.session.php)
   2. Symfony compatible session storage.
   3. Example implementation: [symfony/http-foundation#/Session/Storage/Handler](https://github.com/symfony/http-foundation/tree/6.2/Session/Storage/Handler)
   4. Framework (Drupal) independent.
2. Drupal integration.


## Feature - Config entity storage


@todo


## Feature - Content entity storage

@todo


## Feature - Full database driver

@todo


---

[Cache backend]: #feature---cache-backend

[Queue core]: #feature---queue-core

[Queue advanced]: #feature---queue-advanced

[Logger]: #feature---logger

[Lock]: #feature---lock

[Flood]: #feature---flood

[State]: #feature---state

[KeyValue]: #feature---keyvalue

[Session]: #feature---session

[Config entity storage]: #feature---config-entity-storage

[Content entity storage]: #feature---content-entity-storage

[Full database driver]: #feature---full-database-driver
