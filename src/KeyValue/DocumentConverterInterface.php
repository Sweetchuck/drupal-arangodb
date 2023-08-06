<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use ArangoDBClient\Document;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;

interface DocumentConverterInterface {

  public function getDocumentClass(): string;

  public function setDocumentClass(string $documentClass): static;

  public function getSerializer(): SerializerInterface;

  public function entryToDocument(
    string $collection,
    string $key,
    mixed $value,
    ?int $expire,
  ): Document;

  public function documentToEntry(Document $document): array;

}
