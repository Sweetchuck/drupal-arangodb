# ArangoDB integrator

This module uses ArangoDB as data storage for different services.


## Features

| Feature                      | status             |
|------------------------------|--------------------|
| [Cache backend]              | &#x2705; POC ready |
| [Queue core]                 | &#x2705; POC ready |
| [Queue advanced]             | &#x2705; POC ready |
| [Logger]                     | &#x2705; POC ready |
| [Lock]                       | &#x2705; POC ready |
| [Flood]                      | &#x2705; POC ready |
| [Session]                    | not yet            |
| [Webform submission storage] | not yet            |
| [SearchAPI server]           | not yet            |
| [Config entity storage]      | not yet            |
| [Content entity storage]     | not yet            |
| [Full database driver]       | not yet            |


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

1. advancedqueue integration
2. tests
3. documentation

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


## Feature - Session

@todo list

1. Create an individual package `sweetchuck/php-session-arangodb`
   1. [PHP Session](https://www.php.net/manual/en/book.session.php)
   2. Symfony compatible session storage.
   3. Example implementation: [symfony/http-foundation#/Session/Storage/Handler](https://github.com/symfony/http-foundation/tree/6.2/Session/Storage/Handler)
   4. Framework (Drupal) independent.
2. Drupal integration.


## Feature - Webform submission storage

@todo


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

[Session]: #feature---session

[Webform submission storage]: #feature---webform-submission-storage

[Config entity storage]: #feature---config-entity-storage

[Content entity storage]: #feature---content-entity-storage

[Full database driver]: #feature---full-database-driver
