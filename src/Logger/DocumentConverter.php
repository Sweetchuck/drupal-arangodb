<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Logger;

use ArangoDBClient\Document;
use Drupal\Core\Logger\LogMessageParserInterface;
use Sweetchuck\CacheBackend\ArangoDb\SerializerInterface;

class DocumentConverter implements DocumentConverterInterface {

  protected string $documentClass = Document::class;

  public function getDocumentClass(): string {
    return $this->documentClass ?: Document::class;
  }

  public function setDocumentClass(string $documentClass): static {
    $this->documentClass = $documentClass;

    return $this;
  }

  protected SerializerInterface $serializer;

  public function getSerializer(): SerializerInterface {
    return $this->serializer;
  }

  public function setSerializer(SerializerInterface $serializer): static {
    $this->serializer = $serializer;

    return $this;
  }

  protected LogMessageParserInterface $parser;

  public function __construct(
    LogMessageParserInterface $parser,
    SerializerInterface $serializer,
  ) {
    $this->setSerializer($serializer);
    $this->parser = $parser;
  }

  /**
   * @throws \ArangoDBClient\ClientException
   */
  public function logEntryToDocument($level, string|\Stringable $message, array $context): Document {
    // Remove backtrace and exception since they may contain an unserializable variable.
    unset(
      $context['backtrace'],
      $context['exception'],
    );

    // Convert PSR3-style messages to \Drupal\Component\Render\FormattableMarkup
    // style, so they can be translated too in runtime.
    $message_placeholders = $this->parser->parseMessagePlaceholders($message, $context);
    foreach ($message_placeholders as $key => $value) {
      if (!is_scalar($value)) {
        $message_placeholders[$key] = (string) $value;
      }
    }

    $class = $this->getDocumentClass();
    /** @var \ArangoDBClient\Document $document */
    $document = new $class();
    // @todo Why it is trimmed?
    $document->set('channel', mb_substr($context['channel'], 0, 64));
    $document->set('timestamp', $context['timestamp']);
    $document->set('level', $level);
    $document->set('uid', $context['uid']);
    $document->set('link', $context['link']);
    $document->set('request_uri', $context['request_uri']);
    $document->set('referer', $context['referer']);
    // @todo Why it is trimmed?
    $document->set('ip', mb_substr($context['ip'], 0, 128));
    $document->set('message', (string) $message);
    $document->set('variables', $this->getSerializer()->serialize($message_placeholders));

    return $document;
  }

}
