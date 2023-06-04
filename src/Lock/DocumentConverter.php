<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Lock;

use ArangoDBClient\Document;

class DocumentConverter implements DocumentConverterInterface {

  protected string $documentClass = Document::class;

  public function getDocumentClass(): string {
    return $this->documentClass ?: Document::class;
  }

  public function setDocumentClass(string $documentClass): static {
    $this->documentClass = $documentClass;

    return $this;
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
