<?php

declare(strict_types = 1);

namespace Drupal\arangodb;

use ArangoDBClient\ConnectionOptions as ArangoDbConnectionOptions;
use Drupal\arangodb\Cache\Backend as ArangoDbCacheBackend;

class Utils {

  /**
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
   * @param array|\ArrayAccess $options
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

    $uriParts = parse_url($endpoint);
    $uriParts['user'] = $options[ArangoDbConnectionOptions::OPTION_AUTH_USER] ?? '';
    $uriParts['path'] = $options[ArangoDbConnectionOptions::OPTION_DATABASE] ?? '';

    // @todo This is just a POC.
    // @todo Use \Sweetchuck\Utils\Uri::build().
    return sprintf(
      '%s://%s@%s:%s/%s',
      $uriParts['scheme'],
      $uriParts['user'],
      $uriParts['host'],
      $uriParts['port'],
      $uriParts['path'],
    );
  }

}
