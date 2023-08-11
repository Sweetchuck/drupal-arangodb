<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Logger;

use ArangoDBClient\Document;

interface DocumentConverterInterface {

  public function getDocumentClass(): string;

  public function setDocumentClass(string $documentClass): static;

  /**
   * @param int|string $level
   *
   * @phpstan-param array<string, mixed> $context
   */
  public function logEntryToDocument($level, string|\Stringable $message, array $context): Document;

}
