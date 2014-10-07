<?php


namespace Drupal\autobench\Filesystem;


use Drupal\autobench\Util;

/**
 *
 */
class VirtualFilesystem implements FilesystemInterface {

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

  /**
   * The protocol, e.g. "test" for "test://.." file paths.
   * This is only used for the cleanUp() method.
   *
   * @var string
   */
  private $protocol;

  const NOTHING = FALSE;
  const DIR = '(dir)';

  /**
   * @param string $protocol
   */
  function __construct($protocol) {
    $this->instanceKey = Util::randomString();
    self::$instances[$this->instanceKey] = $this;
    $this->protocol;
  }

  /**
   * Adds a file and the directory, if not exists.
   *
   * @param string $file
   * @param string $contents
   *
   * @throws \Exception
   */
  function addFile($file, $contents) {
    if (!empty($this->knownPaths[$file])) {
      throw new \Exception("File '$file' already exists.");
    }
    $this->addKnownDir(dirname($file));
    $this->knownPaths[$file] = $contents;
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
   * Returns the equivalent of scandir().
   *
   * @param string $dir
   *
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

  /**
   * Unregister the stream wrapper, or delete the tmp folder.
   */
  function cleanUp() {
    stream_wrapper_unregister($this->protocol);
  }
}
