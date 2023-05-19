<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Queue;

use ArangoDBClient\Document;
use Drupal\advancedqueue\Job;

interface AdvancedDocumentConverterInterface {

  /**
   * @phpstan-return class-string
   * @psalm-return class-string
   */
  public function getDocumentClass(): string;

  public function setDocumentClass(string $documentClass): static;

  public function documentToJob(Document $document): Job;

  public function jobToDocument(string $collectionName, Job $job): Document;

}
