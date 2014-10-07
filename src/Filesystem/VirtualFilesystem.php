<?php


namespace Drupal\autobench\Filesystem;


use Drupal\autobench\Util;

class VirtualFilesystem {

  /**
   * @var VirtualFilesystem[]
   */
  protected static $instances = array();

  /**
   * @var string
   */
  protected $instanceKey;

  /**
   * @var string[]
   */
  protected $knownPaths = array();

  const NOTHING = FALSE;
  const DIR = '(dir)';

  function __construct() {
    $this->instanceKey = Util::randomString();
    self::$instances[$this->instanceKey] = $this;
  }

  /**
   * @param string $file
   * @param string $class
   * @throws \Exception
   */
  function addClassFile($file, $class) {
    if (!empty($this->knownPaths[$file])) {
      throw new \Exception("File '$file' already exists. Cannot create class file for '$class' at this path.");
    }
    $this->addKnownDir(dirname($file));
    $this->knownPaths[$file] = $this->buildClassFileContents($class);
  }

  /**
   * @param $class
   *   Class name with or without namespace.
   *
   * @return string
   *   PHP code for the class file
   */
  protected function buildClassFileContents($class) {
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

  /**
   * @param string $dir
   */
  function addKnownDir($dir) {
    if (FALSE === strpos($dir, '://')) {
      return;
    }
    if (!isset($this->knownPaths[$dir])) {
      // Need to set parents first.
      $this->addKnownDir(dirname($dir));
    }
    $this->knownPaths[$dir] = self::DIR;
  }

  /**
   * @param string $dir
   * @return array|bool
   */
  function getDirContents($dir) {
    if (empty($this->knownPaths[$dir]) || self::DIR !== $this->knownPaths[$dir]) {
      return FALSE;
    }
    $pos = strlen($dir . '/');
    $contents = array('.', '..');
    foreach ($this->knownPaths as $path => $type) {
      if ($dir . '/' !== substr($path, 0, $pos)) {
        continue;
      }
      $name = substr($path, $pos);
      if (FALSE !== strpos($name, '/')) {
        // This is a deeper subdirectory.
        continue;
      }
      if ('' === $name) {
        continue;
      }
      $contents[] = $name;
    }
    return $contents;
  }

  /**
   * @param string $path
   *
   * @return array
   */
  function getStat($path) {
    if (!isset($this->knownPaths[$path])) {
      // File does not exist.
      return FALSE;
    }
    elseif (self::DIR === $this->knownPaths[$path]) {
      return stat(__DIR__);
    }
    else {
      // Create a tmp file with the contents and get its stats.
      $contents = $this->getFileContents($path);
      $resource = tmpfile();
      fwrite($resource, $contents);
      $stat = fstat($resource);
      fclose($resource);
      return $stat;
    }
  }

  /**
   * @param $path
   *   The file path.
   *
   * @return string
   *   The file contents.
   *
   * @throws \Exception
   *   Exception thrown if there is no file at $path.
   */
  function getFileContents($path) {
    if (!isset($this->knownPaths[$path])) {
      // File does not exist.
      throw new \Exception("Assumed file '$path' does not exist.");
    }
    elseif (self::DIR === $this->knownPaths[$path]) {
      throw new \Exception("Assumed file '$path' is a directory.");
    }

    return $this->knownPaths[$path];
  }
}
