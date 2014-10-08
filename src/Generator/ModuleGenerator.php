<?php

namespace Drupal\autobench\Generator;

use Drupal\autobench\Filesystem\FilesystemInterface;
use Drupal\autobench\Util;

/**
 *
 */
class ModuleGenerator {

  /**
   * Generates module names and paths.
   *
   * This does not create anything in the filesystem yet, it only generates the
   * array of names and paths.
   *
   * @param string $pathSuffix
   *   E.g. 'test://DRUPAL_ROOT/modules/'
   * @param int $n
   *   Number of modules to generate.
   * @param int $strlen
   *   String length for generated module names.
   *
   * @return string[]
   *   Format: array($moduleName => $moduleDir)
   */
  function generateModules($pathSuffix, $n, $strlen = 10) {
    $modules = array();
    for ($i = 0; $i < $n; ++$i) {
      $moduleName = Util::randomIdentifier($strlen);
      $moduleDir = $pathSuffix . $moduleName;
      $modules[$moduleName] = $moduleDir;
    }
    return $modules;
  }

  /**
   * @param FilesystemInterface $filesystem
   *   Filesystem where the class files should be generated.
   * @param string[] $modules
   *   Format: array($moduleName => $moduleDir)
   * @param int $nClassesPerModule
   *   Number of class files to generate per module.
   *
   * @return array
   */
  public function generateModuleClassFiles($filesystem, $modules, $nClassesPerModule) {
    $classFiles = array();
    foreach ($modules as $module => $moduleDir) {
      for ($i = 0; $i < $nClassesPerModule; ++$i) {
        $fragments = array(Util::randomIdentifier(7), Util::randomIdentifier(13));
        $class = 'Drupal\\' . $module . '\\' . implode('\\', $fragments);
        $file = $moduleDir . '/src/' . implode('/', $fragments) . '.php';
        $filesystem->addFile($file, $this->buildClassFileContents($class));
        $classFiles[$class] = $file;
      }
    }
    return $classFiles;
  }

  /**
   * This method is public, so it can be unit-tested.
   *
   * @param $class
   *   Class name with or without namespace.
   *
   * @return string
   *   PHP code for the class file
   */
  public function buildClassFileContents($class) {
    if (FALSE === ($pos = strrpos($class, '\\'))) {
      // Class without namespace.
      return <<<EOT
<?php
class $class {}

EOT;
    }

    // Class without namespace.
    $namespace = substr($class, 0, $pos);
    $classname = substr($class, $pos + 1);
    return <<<EOT
<?php
namespace $namespace;
class $classname {}

EOT;
  }

} 
