<?php


namespace Drupal\autobench;

use Composer\Autoload\ClassLoader;
use Drupal\autobench\ClassLoader\DrupalClassLoader;
use Symfony\Component\ClassLoader\ApcClassLoader;

/**
 *
 */
class Scenario {

  /**
   * @var string
   */
  private $name;

  /**
   * @var bool
   */
  private $useClassMap = FALSE;

  /**
   * @var bool
   */
  private $useDrupalLoader = FALSE;

  /**
   * @var bool
   */
  private $useApcu = FALSE;

  /**
   * @var bool
   */
  private $preFillApcu = FALSE;

  /**
   * @return static
   */
  static function create($name) {
    return new static($name);
  }

  /**
   * @param string $name
   */
  function __construct($name) {
    $this->name = $name;
  }

  /**
   * @return string
   */
  function getName() {
    return $this->name;
  }

  /**
   * @return $this
   */
  function useClassmap() {
    $this->useClassMap = TRUE;
    return $this;
  }

  /**
   * @return $this
   */
  function useDrupalLoader() {
    $this->useDrupalLoader = TRUE;
    return $this;
  }

  /**
   * @return $this
   */
  function useApcu() {
    $this->useApcu = TRUE;
    return $this;
  }

  /**
   * Start with a hot APCu/APC cache.
   *
   * @return $this
   */
  function preFillApcu() {
    $this->preFillApcu = TRUE;
    return $this;
  }

  /**
   * @param string[] $modules
   * @param string[] $classFiles
   *
   * @return ClassLoader|DrupalClassLoader
   */
  public function buildLoader(array $modules, array $classFiles) {
    $loader = $this->useDrupalLoader
      ? new DrupalClassLoader()
      : new ClassLoader();

    if ($this->useClassMap) {
      $loader->addClassMap($classFiles);
    }
    else {
      foreach ($modules as $module => $moduleDir) {
        $loader->addPsr4('Drupal\\' . $module . '\\', $moduleDir . '/src');
      }
    }

    if ($this->useApcu) {
      // Use an APCu-cached loader.
      $apcuPrefix = Util::randomString();
      $loader = new ApcClassLoader($apcuPrefix, $loader);
      if ($this->preFillApcu) {
        // Artificially make the cache hot.
        foreach ($classFiles as $class => $file) {
          apc_store($apcuPrefix . $class, $file);
        }
      }
    }

    return $loader;
  }
} 
