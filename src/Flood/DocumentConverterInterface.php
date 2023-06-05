<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Flood;

use ArangoDBClient\Document;

interface DocumentConverterInterface {

  public function getDocumentClass(): string;

  public function setDocumentClass(string $documentClass): static;

  public function eventToDocument(string $name, int $window, string $identifier): Document;

}
