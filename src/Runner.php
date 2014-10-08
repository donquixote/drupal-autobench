<?php


namespace Drupal\autobench;

use Composer\Autoload\ClassLoader;
use Drupal\autobench\ClassLoader\DrupalClassLoader;
use Drupal\autobench\Filesystem\TmpFilesystem;
use Drupal\autobench\Generator\ModuleGenerator;

/**
 *
 */
class Runner {

  /**
   * @var \Drupal\autobench\Generator\ModuleGenerator
   */
  private $generator;

  /**
   * @param ModuleGenerator $generator
   */
  public function __construct(ModuleGenerator $generator) {
    $this->generator = $generator;
  }

  /**
   * @param \Drupal\autobench\Scenario $scenario
   *
   * @throws \Exception
   * @return float
   *   Duration for one class load.
   */
  public function run(Scenario $scenario) {

    $nModules = 200;
    $nClassesPerModule = 10;

    // Prepare the virtual filesystem.
    $filesystem = new TmpFilesystem();
    $pathPrefix = make_tmp() . '/modules/';
    $modules = $this->generator->generateModules($pathPrefix, $nModules);
    $classFiles = $this->generator->generateModuleClassFiles($filesystem, $modules, $nClassesPerModule);
    $classes = array_keys($classFiles);
    shuffle($classes);

    // Prepare the class loader.
    $loader = $scenario->buildLoader($modules, $classFiles);

    // Run benchmarks.
    $t0 = microtime(TRUE);
    foreach ($classes as $class) {
      $loader->loadClass($class);
    }
    $t1 = microtime(TRUE);
    $duration = $t1 - $t0;

    // Verify that all classes were loaded.
    foreach ($classes as $class) {
      if (!class_exists($class, FALSE)) {
        throw new \Exception("Class $class was not loaded.");
      }
    }

    // Clean up and return.
    $filesystem->cleanUp();
    return $duration / $nClassesPerModule / $nModules;
  }

} 
