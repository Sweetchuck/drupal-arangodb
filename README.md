# ArangoDB integrator

@todo


## Feature

| Feature                                   | status             |
|-------------------------------------------|--------------------|
| [Cache backend](#feature---cache-backend) | &#x2705; POC ready |
| [Session](#feature---session)             | not yet            |
| Lock                                      | not yet            |
| Flood                                     | not yet            |
| Queue storage                             | not yet            |
| Log                                       | not yet            |
| Webform submission storage                | not yet            |
| Config entity storage                     | not yet            |
| Content entity storage                    | not yet            |
| Full database driver                      | not yet            |


### Feature - Cache backend

@todo list

1. tests
2. documentation
   1. setup - connection
   2. setup - serializers


### Feature - Session

@todo list

1. Create an individual package `sweetchuck/session-arangodb` \
   1. [PHP Session](https://www.php.net/manual/en/book.session.php)
   2. Symfony compatible session storage.
   3. Example implementation: [symfony/http-foundation#/Session/Storage/Handler](https://github.com/symfony/http-foundation/tree/6.2/Session/Storage/Handler)
   4. Framework (Drupal) independent.
2. Drupal integration.
