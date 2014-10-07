<?php

namespace Drupal\autobench;

use Composer\Autoload\ClassLoader;
use Drupal\autobench\ClassLoader\DrupalClassLoader;
use Drupal\autobench\Generator\ModuleGenerator;

/**
 *
 */
class Main {

  /**
   * @var \Drupal\autobench\Runner
   */
  private $runner;

  /**
   * @param \Drupal\autobench\Runner $runner
   */
  function __construct(Runner $runner) {
    $this->runner = $runner;
  }

  /**
   * @return float
   *   Duration
   */
  function runAll() {
    foreach (array(
      array(FALSE, FALSE),
      array(FALSE, TRUE),
      array(TRUE, FALSE),
      array(TRUE, TRUE),
      array(FALSE, FALSE),
      array(FALSE, TRUE),
      array(TRUE, FALSE),
      array(TRUE, TRUE),
    ) as $scenario) {
      list($useClassMap, $useDrupalLoader) = $scenario;

      $duration = $this->runner->run($useClassMap, $useDrupalLoader);

      $message = $duration . ' seconds';
      $message .= $useClassMap
        ? ' with classmap'
        : ' without classmap';
      $message .= $useDrupalLoader
        ? ' with Drupal-optimized loader'
        : ' with Composer loader';
      drush_log($message, 'ok');
    }
  }
}
