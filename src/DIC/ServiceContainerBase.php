<?php


namespace Drupal\autobench\DIC;

/**
 * Base class for a simple dependency injection container, as in
 * @link http://dqxtech.net/blog/2014-06-13/simple-do-it-yourself-php-service-container
 */
abstract class ServiceContainerBase {

  /**
   * @var mixed[]
   */
  private $services = array();

  /**
   * Allows to set a service before it is being lazy-created for the first time.
   *
   * @param string $key
   * @param object $service
   *
   * @throws \Exception
   */
  public function __set($key, $service) {
    if (isset($this->services[$key])) {
      throw new \Exception("Service '$key' already set.");
    }
    $method = 'get_' . $key;
    if (!method_exists($this, $method)) {
      throw new \Exception("Service key '$key' not allowed.");
    }
    $this->services[$key] = $service;
  }

  /**
   * @param string $key
   *
   * @return object
   *
   * @throws \Exception
   */
  public function __get($key) {
    return isset($this->services[$key])
      ? $this->services[$key]
      : $this->services[$key] = $this->createService($key);
  }

  /**
   * @param string $key
   *
   * @throws \Exception
   * @return object
   */
  private function createService($key) {
    $method = 'get_' . $key;
    if (!method_exists($this, $method)) {
      throw new \Exception("Service key '$key' not allowed.");
    }
    $service = $this->$method();
    if (!is_object($service)) {
      throw new \Exception("Invalid service returned for '$key'.");
    }
    return $this->services[$key] = $service;
  }

}
