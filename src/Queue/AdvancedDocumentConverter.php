<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use ArangoDBClient\Document;
use Drupal\advancedqueue\Job;
use Drupal\Component\Datetime\TimeInterface;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;

class AdvancedDocumentConverter implements AdvancedDocumentConverterInterface {

  /**
   * @phpstan-var class-string
   */
  protected string $documentClass = Document::class;

  /**
   * @phpstan-return class-string
   */
  public function getDocumentClass(): string {
    return $this->documentClass ?: Document::class;
  }

  /**
   * @phpstan-param class-string $documentClass
   */
  public function setDocumentClass(string $documentClass): static {
    // @todo It has to be instance of \ArangoDBClient\Document.
    $this->documentClass = $documentClass;

    return $this;
  }

  protected SerializerInterface $serializer;

  public function getSerializer(): SerializerInterface {
    return $this->serializer;
  }

  public function setSerializer(SerializerInterface $serializer): static {
    $this->serializer = $serializer;

    return $this;
  }

  protected TimeInterface $time;

  public function getTime(): TimeInterface {
    return $this->time;
  }

  public function setTime(TimeInterface $time): static {
    $this->time = $time;

    return $this;
  }

  public function __construct(
    SerializerInterface $serializer,
    TimeInterface $time,
  ) {
    $this
      ->setSerializer($serializer)
      ->setTime($time);
  }

  public function documentToJob(Document $document): Job {
    $values = $document->jsonSerialize();
    $values['id'] = $values['_key'];
    $values['queue_id'] = $values['queueId'];
    $values['num_retries'] = $values['numRetries'];
    $values['payload'] = $this->getSerializer()->unserialize($values['payload']);
    unset(
      $values['_id'],
      $values['_key'],
      $values['queueId'],
      $values['numRetries'],
    );

    return new Job($values);
  }

  /**
   * @throws \ArangoDBClient\ClientException
   */
  public function jobToDocument(string $collectionName, Job $job): Document {
    $values = [];
    $jobId = $job->getId();
    if (!empty($jobId)) {
      $values['_id'] = "$collectionName/$jobId";
      $values['_key'] = $jobId;
    }
    $values += [
      'queueId' => $job->getQueueId(),
      'type' => $job->getType(),
      'state' => $job->getState(),
      'numRetries' => $job->getNumRetries(),
      'available' => $job->getAvailableTime(),
      'processed' => $job->getProcessedTime(),
      'expires' => $job->getExpiresTime(),
      'message' => $job->getMessage(),
      'payload' => $this->getSerializer()->serialize($job->getPayload()),
    ];

    $class = $this->getDocumentClass();
    /** @var \ArangoDBClient\Document $document */
    $document = $class::createFromArray($values);

    return $document;
  }

}
