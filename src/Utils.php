<?php

declare(strict_types = 1);

namespace Drupal\arangodb;

use ArangoDBClient\ConnectionOptions as ArangoDbConnectionOptions;
use Drupal\arangodb\Cache\Backend as ArangoDbCacheBackend;
use Sweetchuck\Utils\Uri;

class Utils {

  public const SERVER_CODE_COLLECTION_NOT_EXISTS = 1203;

  /**
   * @phpstan-param iterable<int|string, mixed> $bins
   *
   * @phpstan-return array<int, drupal-arangodb-connection-with-cache-bins>
   *
   * @see \Drupal\Core\Cache\Cache::getBins()
   */
  public static function groupCacheBinsByConnections(iterable $bins): array {
    $mapping = [];
    foreach ($bins as $binName => $bin) {
      if (!($bin instanceof ArangoDbCacheBackend)) {
        continue;
      }

      $connection = $bin->getPool()->getConnection();
      if (!$connection) {
        continue;
      }

      $connectionId = spl_object_id($connection);
      $mapping += [
        $connectionId => [
          'connection' => $connection,
          'binNames' => [],
        ],
      ];

      $mapping[$connectionId]['binNames'][$binName] = $bin->getPool()->getCollectionName();
    }

    return $mapping;
  }

  /**
   * @phpstan-param drupal-arangodb-connection-uri-options $options
   */
  public static function connectionUri($options): string {
    if (!empty($options[ArangoDbConnectionOptions::OPTION_ENDPOINT])
      && is_array($options[ArangoDbConnectionOptions::OPTION_ENDPOINT])
    ) {
      $endpoint = $options[ArangoDbConnectionOptions::OPTION_ENDPOINT][0];
    }
    else {
      $endpoint = $options[ArangoDbConnectionOptions::OPTION_ENDPOINT] ?? 'tcp://127.0.0.1:8529';
    }

    $uriParts = parse_url($endpoint) ?: [];
    $uriParts['user'] = $options[ArangoDbConnectionOptions::OPTION_AUTH_USER] ?? '';
    $uriParts['path'] = $options[ArangoDbConnectionOptions::OPTION_DATABASE] ?? '';

    return Uri::build($uriParts);
  }

}
