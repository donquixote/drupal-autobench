<?php


namespace Drupal\autobench\DIC;

use Drupal\autobench\Generator\ModuleGenerator;
use Drupal\autobench\Main;
use Drupal\autobench\Runner;

/**
 * @property \Drupal\autobench\Main $main
 * @property \Drupal\autobench\Runner $runner
 * @property \Drupal\autobench\Generator\ModuleGenerator generator
 */
class ServiceContainer extends ServiceContainerBase {

  /**
   * @return \Drupal\autobench\Main
   */
  protected function get_main() {
    return new Main($this->runner);
  }

  /**
   * @return \Drupal\autobench\Runner
   */
  protected function get_runner() {
    return new Runner($this->generator);
  }

  /**
   * @return \Drupal\autobench\Generator\ModuleGenerator
   */
  protected function get_generator() {
    return new ModuleGenerator();
  }

} 
