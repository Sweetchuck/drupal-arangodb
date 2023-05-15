<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use ArangoDBClient\Document;

interface DocumentConverterInterface {

  public function getDocumentClass(): string;

  public function setDocumentClass(string $documentClass): static;

  public function documentToItem(Document $document): object;

  public function itemToDocument(string $queueName, object $item): Document;

  public function dataToDocument(string $queueName, mixed $data): Document;

}
