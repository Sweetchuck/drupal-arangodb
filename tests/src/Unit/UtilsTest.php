<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Unit;

use Drupal\arangodb\Utils;
use Drupal\Tests\UnitTestCase;

/**
 * @group arangodb
 *
 * @covers \Drupal\arangodb\Utils
 */
class UtilsTest extends UnitTestCase {

  public function casesConnectionUri(): array {
    $expected = 'tcp://me@1.2.3.4:5678/my_database_01';
    $values = [
      'endpoint' => 'tcp://1.2.3.4:5678',
      'AuthUser' => 'me',
      'database' => 'my_database_01',
    ];

    return [
      'array' => [$expected, $values],
      'ArrayAccess' => [$expected, new \ArrayObject($values)],
    ];
  }

  /**
   * @dataProvider casesConnectionUri
   */
  public function testConnectionUri(string $expected, $options) {
    static::assertEquals($expected, Utils::connectionUri($options));
  }

}
