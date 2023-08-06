<?php

declare(strict_types=1);


namespace Drupal\Tests\arangodb\Kernel\Logger;

use ArangoDBClient\Document;
use ArangoDBClient\Statement;
use Drupal\arangodb\Logger\Backend;
use Drupal\arangodb\Logger\DocumentConverter;
use Drupal\arangodb\Logger\SchemaManager;
use Drupal\Core\Logger\LogMessageParser;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\arangodb\Traits\ConnectionTrait;
use Exception;
use Sweetchuck\CacheBackend\ArangoDb\Serializer\JsonSerializer;

/**
 * @covers \Drupal\arangodb\Logger\Backend
 */
class BackendTest extends KernelTestBase {
  use ConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'arangodb',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpArangodbConnection();
  }

  /**
   * {@inheritdoc}
   *
   * @throws \ArangoDBClient\Exception
   * @throws \ArangoDBClient\ClientException
   */
  protected function tearDown(): void {
    $this->tearDownArangodbDropCollections($this->collectionNamePrefix);
    parent::tearDown();
  }

  /**
   * @throws \Exception
   */
  protected function createLoggerBackend(): Backend {
    return new Backend(
      $this->createConnectionFactory(),
      'default',
      new DocumentConverter(
        new LogMessageParser(),
        new JsonSerializer(),
      ),
      new SchemaManager(),
      [
        'collectionNamePattern' => "{$this->collectionNamePrefix}_log",
      ],
    );
  }

  public function casesLog(): array {
    return [
      'basic' => [
        [
          'channel' => 'test',
          'timestamp' => 234,
          'level' => 1,
          'uid' => 5,
          'link' => 'my_link_1',
          'request_uri' => 'my_request_uri_1',
          'referer' => 'my_referer_1',
          'ip' => 'my_hostname_1',
          'message' => 'my message @p1 and %p2 and @p3',
          'variables' => '{"@p1":"v1","%p2":"v3","@p3":"v3"}',
        ],
        1,
        'my message @p1 and %p2 and {p3}',
        [
          'backtrace' => 'my_backtrace_1',
          'exception' => 'my_exception_1',
          'channel' => 'test',
          'timestamp' => 234,
          'uid' => 5,
          'link' => 'my_link_1',
          'request_uri' => 'my_request_uri_1',
          'referer' => 'my_referer_1',
          'ip' => 'my_hostname_1',
          '@p1' => 'v1',
          '%p2' => 'v3',
          '@p3' => 'v3',
        ],
      ],
    ];
  }

  /**
   * @dataProvider casesLog
   *
   * @throws Exception
   */
  public function testLog(array $expected, mixed $level, string|\Stringable $message, array $context = []): void {
    $logger = $this->createLoggerBackend();
    $logger->log($level, $message, $context);

    $document = $this->getLastDocument($logger);
    $actual = $document->getAll();
    unset(
      $actual['_id'],
      $actual['_key'],
    );
    static::assertSame($expected, $actual);
  }

  /**
   * @throws \ArangoDBClient\Exception
   */
  protected function getLastDocument(Backend $logger): ?Document {
    $query = <<< AQL
      FOR doc in @@collection
        SORT doc._key DESC
        LIMIT 1
        RETURN doc
    AQL;

    $statement = new Statement(
      $logger->getDbConnection(),
      [
        'query' => $query,
        'bindVars' => [
          '@collection' => $logger->getDbCollectionName('test'),
        ],
      ],
    );

    $result = $statement->execute();

    return $result->current();
  }

}
