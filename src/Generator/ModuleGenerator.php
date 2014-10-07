<?php

namespace Drupal\autobench\Generator;

use Drupal\autobench\Filesystem\VirtualFilesystem;
use Drupal\autobench\Util;

/**
 *
 */
class ModuleGenerator {

  /**
   * @param int $n
   * @param int $strlen
   *
   * @return string[]
   */
  function generateModules($n = 200, $strlen = 10) {
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
  public function generateModuleClassFiles($filesystem, $modules, $nClassesPerModule = 20) {
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
