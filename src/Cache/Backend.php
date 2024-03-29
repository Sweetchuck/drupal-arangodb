<?php

declare(strict_types = 1);

namespace Drupal\arangodb\Cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Sweetchuck\CacheBackend\ArangoDb\CacheItem;
use Sweetchuck\CacheBackend\ArangoDb\CacheItemPool;

class Backend implements CacheBackendInterface, CacheTagsInvalidatorInterface {

  protected CacheItemPool $pool;

  public function getPool(): CacheItemPool {
    return $this->pool;
  }

  public function __construct(CacheItemPool $pool) {
    $this->pool = $pool;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    $cacheIds = [$cid];
    $cacheItems = $this->getMultiple($cacheIds, $allow_invalid);

    return reset($cacheItems);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string> $cids
   *
   * @phpstan-return array<string, mixed>
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $items = $this->pool->getItems($cids, $allow_invalid);
    $cacheEntries = [];
    foreach ($items as $item) {
      if (!$item->isHit()) {
        continue;
      }

      $cacheEntries[$item->getKey()] = (object) $this->cacheItemToCacheArray($item);
    }

    $cids = array_diff($cids, array_keys($cacheEntries));

    return $cacheEntries;
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string> $tags
   *
   * @phpstan-return void
   */
  public function set($cid, $data, $expire = Cache::PERMANENT, array $tags = []) {
    $this->setMultiple([
      $cid => [
        'data' => $data,
        'expire' => $expire,
        'tags' => $tags,
      ],
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string, drupal-arangodb-cache-array-lazy> $items
   *
   * @return void
   */
  public function setMultiple(array $items) {
    foreach ($items as $key => $array) {
      $item = $this->cacheArrayToCacheItem($key, $array);
      $this->pool->save($item);
    }
  }

  /**
   * @phpstan-return drupal-arangodb-cache-array-final
   *
   * @link https://www.arangodb.com/docs/stable/indexing-index-basics.html#ttl-time-to-live-index
   */
  protected function cacheItemToCacheArray(CacheItem $item): array {
    return [
      'cid' => $item->getKey(),
      'data' => $item->get(),
      'tags' => $item->getTags(),
      'created' => $item->getCreatedTimestamp(),
      'expire' => $item->getExpirationTimestamp() ?: CacheBackendInterface::CACHE_PERMANENT,
      'valid' => $item->isAlive(),
    ];
  }

  /**
   * @phpstan-param drupal-arangodb-cache-array-lazy $array
   *
   * @link https://www.arangodb.com/docs/stable/indexing-index-basics.html#ttl-time-to-live-index
   */
  protected function cacheArrayToCacheItem(string $key, array $array): CacheItem {
    $item = new CacheItem($key, [], $this->pool->getValidator());
    $item->set($array['data']);

    if (!empty($array['tags'])) {
      try {
        $item->setTags($array['tags']);
      }
      catch (\TypeError $e) {
        throw new \AssertionError($e->getMessage(), 1, $e);
      }
    }

    if (isset($array['expire']) && $array['expire'] !== CacheBackendInterface::CACHE_PERMANENT) {
      $item->expiresAt(new \DateTime("@{$array['expire']}"));
    }

    if (isset($array['created'])) {
      $item->setCreatedTimestamp(floatval($array['created']));
    }

    return $item;
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function delete($cid) {
    $this->pool->delete($cid);
  }

  /**
   * {@inheritdoc}
   *
   * @phpstan-param array<string> $cids
   *
   * @return void
   */
  public function deleteMultiple(array $cids) {
    $this->pool->deleteMultiple($cids);
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function deleteAll() {
    $this->pool->clear();
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function invalidate($cid) {
    $this->invalidateMultiple([$cid]);
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function invalidateMultiple(array $cids) {
    $this->pool->invalidateMultiple($cids);
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function invalidateAll() {
    $this->pool->invalidateAll();
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function garbageCollection() {
    $this->pool->garbageCollection();
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function removeBin() {
    $this->pool->removeBin();
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function invalidateTags(array $tags) {
    $this->pool->invalidateTags($tags);
  }

}
