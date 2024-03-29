<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Lock;

interface DocumentConverterInterface {

  public function getDocumentClass(): string;

  public function setDocumentClass(string $documentClass): static;

}
