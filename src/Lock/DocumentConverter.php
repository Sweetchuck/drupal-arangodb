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

}
