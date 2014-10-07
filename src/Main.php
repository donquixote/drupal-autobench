<?php

namespace Drupal\autobench;

use Composer\Autoload\ClassLoader;
use Drupal\autobench\ClassLoader\DrupalClassLoader;
use Drupal\autobench\Filesystem\VirtualFilesystem;

class Main {

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

      $duration = $this->run($useClassMap, $useDrupalLoader);

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

  /**
   * @param bool $useClassMap
   * @param bool $useDrupalLoader
   *
   * @return float
   *
   * @throws \Exception
   */
  protected function run($useClassMap, $useDrupalLoader) {

    // Prepare the virtual filesystem.
    $filesystem = Filesystem\StreamWrapper::register('test');
    $modules = $this->generateModules();
    $classFiles = $this->generateModuleClassFiles($filesystem, $modules);
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

  /**
   * @param int $n
   * @param int $strlen
   *
   * @return string[]
   */
  protected function generateModules($n = 200, $strlen = 10) {
    $modules = array();
    for ($i = 0; $i < $n; ++$i) {
      $moduleName = Util::randomIdentifier($strlen);
      $moduleDir = 'test://DRUPAL_ROOT/modules/' . $moduleName;
      $modules[$moduleName] = $moduleDir;
    }
    return $modules;
  }

  /**
   * @param VirtualFilesystem $filesystem
   * @param string[] $modules
   * @param int $nClassesPerModule
   *
   * @return array
   */
  protected function generateModuleClassFiles($filesystem, $modules, $nClassesPerModule = 20) {
    $classFiles = array();
    foreach ($modules as $module => $moduleDir) {
      for ($i = 0; $i < $nClassesPerModule; ++$i) {
        $fragments = array(Util::randomIdentifier(7), Util::randomIdentifier(13));
        $class = 'Drupal\\' . $module . '\\' . implode('\\', $fragments);
        $file = $moduleDir . '/lib/' . implode('/', $fragments) . '.php';
        $filesystem->addClassFile($file, $class);
        $classFiles[$class] = $file;
      }
    }
    return $classFiles;
  }
}
