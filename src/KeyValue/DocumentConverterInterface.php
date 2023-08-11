<?php

declare(strict_types = 1);

namespace Drupal\arangodb\KeyValue;

use ArangoDBClient\Document;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;

interface DocumentConverterInterface {

  /**
   * @phpstan-return class-string
   */
  public function getDocumentClass(): string;

  /**
   * @phpstan-param class-string $documentClass
   */
  public function setDocumentClass(string $documentClass): static;

  public function getSerializer(): SerializerInterface;

  public function entryToDocument(
    string $collection,
    string $key,
    mixed $value,
    ?int $expire,
  ): Document;

  /**
   * @phpstan-return drupal-arangodb-keyvalue-entry
   */
  public function documentToEntry(Document $document): array;

}
