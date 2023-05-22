<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Unit\Queue;

use ArangoDBClient\Document;
use Drupal\advancedqueue\Job;
use Drupal\arangodb\Queue\AdvancedDocumentConverter;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Tests\arangodb\Traits\TestTime;
use PHPUnit\Framework\TestCase;
use Sweetchuck\CacheBackend\ArangoDb\Serializer\JsonSerializer;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;

/**
 * @covers \Drupal\arangodb\Queue\AdvancedDocumentConverter
 */
class AdvancedDocumentConverterTest extends TestCase {

  public function testGetSetDocumentClass(): void {
    $dc = $this->createDocumentConverter();
    static::assertSame(
      $dc->getDocumentClass(),
      Document::class,
      'default value is valid',
    );

    $dc->setDocumentClass('foo');
    static::assertSame('foo', $dc->getDocumentClass());
  }

  public function testGetSetTime(): void {
    $time = $this->createTime();
    $dc = $this->createDocumentConverter();
    $dc->setTime($time);
    static::assertSame($time, $dc->getTime());
  }

  public function casesJobToDocument(): array {
    return [
      'basic' => [
        [
          'queue_id' => 'q1',
          'id' => 'j1',
          'state' => Job::STATE_SUCCESS,
          'type' => 't1',
          'payload' => ['myKey1' => 'myValue1'],
          'num_retries' => 3,
          'message' => 'm1',
          'available' => 4,
          'processed' => 5,
          'expires' => 6,
        ],
      ],
    ];
  }

  /**
   * @dataProvider casesJobToDocument
   */
  public function testJobToDocument(array $jobDefinition): void {
    $collectionName = 'myCollection01';

    $documentConverter = $this->createDocumentConverter();
    $job = new Job($jobDefinition);
    $document = $documentConverter->jobToDocument($collectionName, $job);

    $expected = [
      'queueId' => $jobDefinition['queue_id'],
      'type' => $jobDefinition['type'],
      'state' => $jobDefinition['state'],
      'numRetries' => $jobDefinition['num_retries'],
      'available' => $jobDefinition['available'],
      'processed' => $jobDefinition['processed'],
      'expires' => $jobDefinition['expires'],
      'message' => $jobDefinition['message'],
      'payload' => json_encode($jobDefinition['payload']),
      '_id' => "$collectionName/{$jobDefinition['id']}",
      '_key' => $jobDefinition['id'],
    ];

    $actual = $document->getAll(['_includeInternals' => TRUE]);
    unset(
      $actual['_rev'],
      $actual['_isNew'],
      $actual['_doValidate'],
      $actual['_ignoreHiddenAttributes'],
    );

    static::assertSame($expected, $actual);
  }

  public function casesDocumentToJob(): array {
    return [
      'basic' => [
        [
          '_key' => '0000000012345678',
          'queueId' => 'q1',
          'type' => 'myType01',
          'state' => Job::STATE_QUEUED,
          'payload' => '{"myKey01":"myValue01"}',
          'numRetries' => 2,
        ],
      ],
    ];
  }

  /**
   * @throws \ArangoDBClient\ClientException
   *
   * @dataProvider casesDocumentToJob
   */
  public function testDocumentToJob(array $docValues): void {
    $documentConverter = $this->createDocumentConverter();
    $document = Document::createFromArray($docValues);
    $job = $documentConverter->documentToJob($document);

    static::assertSame($docValues['_key'], $job->getId());
    static::assertSame($docValues['type'], $job->getType());
    static::assertSame($docValues['state'], $job->getState());
    static::assertSame($docValues['payload'], json_encode($job->getPayload()));

    if (array_key_exists('queueId', $docValues)) {
      static::assertSame($docValues['queueId'], $job->getQueueId());
    }
  }

  protected function createDocumentConverter(): AdvancedDocumentConverter {
    return new AdvancedDocumentConverter(
      $this->createSerializer(),
      $this->createTime(),
    );
  }

  protected function createSerializer(): SerializerInterface {
    return new JsonSerializer();
  }

  protected function createTime(): TimeInterface {
    return new TestTime();
  }

}
