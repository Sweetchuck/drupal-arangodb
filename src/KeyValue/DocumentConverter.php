<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use ArangoDBClient\Document;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;

class DocumentConverter implements DocumentConverterInterface {

  protected string $documentClass = Document::class;

  public function getDocumentClass(): string {
    return $this->documentClass ?: Document::class;
  }

  public function setDocumentClass(string $documentClass): static {
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

  public function __construct(SerializerInterface $serializer,) {
    $this->setSerializer($serializer);
  }

  /**
   * @throws \ArangoDBClient\ClientException
   */
  public function entryToDocument(
    string $collection,
    string $key,
    mixed $value,
    ?int $expire,
  ): Document {
    $class = $this->getDocumentClass();

    /** @var \ArangoDBClient\Document $document */
    $document = new $class();
    $document->set('collection', $collection);
    $document->set('key', $key);
    $document->set('value', $this->getSerializer()->serialize($value));
    if ($expire !== NULL) {
      $document->set('expire', $expire);
    }

    return $document;
  }

  public function documentToEntry(Document $document): array {
    $values = $document->getAll();
    $values['value'] = $this->getSerializer()->unserialize($values['value']);
    unset(
      $values['_id'],
      $values['_key'],
      $values['_ref'],
    );

    return $values;
  }

}
