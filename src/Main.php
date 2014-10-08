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
   * Run all scenarios twice.
   */
  function runAll() {
    $scenarios = $this->getScenarios();
    $m = 4;
    $numbersAll = $this->runAllMultiple($scenarios, 2 * $m + 1);
    foreach ($numbersAll as $key => $numbers) {
      // Calculate the median.
      sort($numbers);
      $median = $numbers[$m];
      $message = number_format($median * 1000, 4) . ' ms ' . $scenarios[$key]->getName();
      drush_log($message, 'ok');
    }
  }

  /**
   * Run all scenarios.
   *
   * @param Scenario[] $scenarios
   * @param int $n
   *
   * @throws \Exception
   * @return float[][]
   */
  private function runAllMultiple(&$scenarios, $n) {
    $numbers = array();
    for ($i = 0; $i < $n; ++$i) {
      drush_log('.', 'ok');
      foreach ($scenarios as $key => $scenario) {
        try {
          $numbers[$key][] = $this->runner->run($scenario);
        }
        catch (\RuntimeException $e) {
          drush_log('APC or APCu extensions not available, skipping scenarios.', 'warning');
          unset($scenarios[$key]);
        }
      }
    }
    return $numbers;
  }

  /**
   * @return Scenario[]
   */
  private function getScenarios() {
    return array(
      'composer' => Scenario::create('Composer PSR-4 lookup'),
      'drupalOptimized' => Scenario::create('Drupal-optimized PSR-4 lookup')->useDrupalLoader(),
      'classmap' => Scenario::create('Classmap')->useClassmap(),
      'apcuHot' => Scenario::create('APCu cache, hot')->useApcu()->preFillApcu(),
      'apcuNotHot' => Scenario::create('APCu cache, NOT hot')->useApcu(),
    );
  }
}
