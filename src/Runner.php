<?php


namespace Drupal\autobench;

use Composer\Autoload\ClassLoader;
use Drupal\autobench\ClassLoader\DrupalClassLoader;
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
   * @param bool $useClassMap
   * @param bool $useDrupalLoader
   *
   * @return float
   *
   * @throws \Exception
   */
  public function run($useClassMap, $useDrupalLoader) {

    // Prepare the virtual filesystem.
    $filesystem = Filesystem\StreamWrapper::register('test');
    $modules = $this->generator->generateModules();
    $classFiles = $this->generator->generateModuleClassFiles($filesystem, $modules);
    $classes = array_keys($classFiles);
    shuffle($classes);

    // Prepare the class loader.
    $loader = $useDrupalLoader
      ? new DrupalClassLoader()
      : new ClassLoader();

    if ($useClassMap) {
      $loader->addClassMap($classFiles);
    }
    else {
      foreach ($modules as $module => $moduleDir) {
        $loader->addPsr4('Drupal\\' . $module . '\\', $moduleDir . '/lib');
      }
    }

    // Run benchmarks.
    $t0 = microtime(TRUE);
    foreach ($classes as $class) {
      $loader->loadClass($class);
      if (!class_exists($class, FALSE)) {
        throw new \Exception("Class $class was not loaded.");
      }
    }
    $t1 = microtime(TRUE);
    $duration = $t1 - $t0;

    // Clean up and return.
    stream_wrapper_unregister('test');
    return $duration;
  }

} 
