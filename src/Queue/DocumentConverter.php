<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use ArangoDBClient\Document;
use Drupal\Component\Datetime\TimeInterface;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;

class DocumentConverter implements DocumentConverterInterface
{

  //region Property - documentClass
  protected string $documentClass = Document::class;

  public function getDocumentClass(): string
  {
    return $this->documentClass ?: Document::class;
  }

  public function setDocumentClass(string $documentClass): static
  {
    $this->documentClass = $documentClass;

    return $this;
  }
  //endregion

  // region Property - serializer
  protected SerializerInterface $serializer;

  public function getSerializer(): SerializerInterface {
    return $this->serializer;
  }

  public function setSerializer(SerializerInterface $serializer): static {
    $this->serializer = $serializer;

    return $this;
  }
  // endregion

  // region Property - time
  protected TimeInterface $time;

  public function getTime(): TimeInterface {
    return $this->time;
  }

  public function setTime(TimeInterface $time): static {
    $this->time = $time;

    return $this;
  }
  // endregion

  public function __construct(
    SerializerInterface $serializer,
    TimeInterface $time,
  ) {
    $this
      ->setSerializer($serializer)
      ->setTime($time);
  }

  public function documentToItem(Document $document): object {
    $item = $document->jsonSerialize();
    $item['item_id'] = $document->getKey();
    $item['data'] = $this->getSerializer()->unserialize($item['data']);
    settype($item['created'], 'int');

    return (object) $item;
  }

  /**
   * @throws \ArangoDBClient\ClientException
   */
  public function itemToDocument(string $queueName, object $item): Document {
    $class = $this->getDocumentClass();
    /** @var \ArangoDBClient\Document $document */
    $document = new $class();
    $document->set('queue', $queueName);
    $document->set('created', $this->getTime()->getCurrentMicroTime());
    $document->set('expire', 0);
    $document->set('data', $this->getSerializer()->serialize($item->data));

    return $document;
  }

  /**
   * @throws \ArangoDBClient\ClientException
   */
  public function dataToDocument(string $queueName, mixed $data): Document {
    return $this->itemToDocument($queueName, (object) ['data' => $data]);
  }

}
