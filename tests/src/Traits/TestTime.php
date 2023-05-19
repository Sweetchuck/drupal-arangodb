<?php

declare(strict_types = 1);

namespace Drupal\Tests\arangodb\Traits;

use Drupal\Component\Datetime\Time;

class TestTime extends Time {

  public function __construct() {
    // Do nothing.
  }

  public float $requestTime = 0;

  public function getRequestTime() {
    return (int) $this->requestTime;
  }

  public function getRequestMicroTime() {
    return $this->requestTime;
  }

  public float $currentTime = 0;

  public function getCurrentTime() {
    return (int) $this->currentTime;
  }

  public function getCurrentMicroTime() {
    return $this->currentTime;
  }

}
