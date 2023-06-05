<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Flood;

use ArangoDBClient\Document;
use Drupal\Component\Datetime\TimeInterface;

class DocumentConverter implements DocumentConverterInterface {

  protected TimeInterface $time;

  protected string $documentClass = Document::class;

  public function getDocumentClass(): string {
    return $this->documentClass ?: Document::class;
  }

  public function setDocumentClass(string $documentClass): static {
    $this->documentClass = $documentClass;

    return $this;
  }

  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * @throws \ArangoDBClient\ClientException
   */
  public function eventToDocument(string $name, int $window, string $identifier): Document {
    $class = $this->getDocumentClass();

    $now = $this->time->getCurrentTime();
    /** @var \ArangoDBClient\Document $document */
    $document = new $class();
    $document->set('identifier', $identifier);
    $document->set('name', $name);
    $document->set('created', $now);
    $document->set('expiration', $now + $window);

    return $document;
  }

}
