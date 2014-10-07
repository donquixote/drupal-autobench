<?php

namespace Drupal\autobench\ClassLoader;

/**
 * Implements a class loader for PSR-0 and the proposed PSR-4. See
 * - https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md
 * - https://github.com/pmjones/fig-standards/blob/master/proposed/psr-4-autoloader/psr-4-autolader.md
 *
 * The class is mostly based on:
 * 1. A pull request for Composer, that will add PSR-4 class loading to
 *    Composer\Autoload\ClassLoader.
 *    See https://github.com/composer/composer/pull/2121.
 * 2. A change proposed to Composer, that will improve autoload performance in
 *    cases where many of the registered namespaces have the same first
 *    character. E.g. many sub-namespaces of 'Drupal\\', where the first
 *    character is always 'D'.
 *    See https://github.com/composer/composer/issues/2112
 *
 * The pull request (1.) has been adapted to comply with the Drupal coding
 * standards, and enhanced with docblock comments.
 * The predictor logic (2.) was added to that in a custom fashion.
 */
class DrupalClassLoader {

  /**
   * @var array
   *   PSR-4 namespaces mapped to their string length, to avoid a strlen() on
   *   class lookups.
   *
   *   Namespaces are represented with trailing namespace separator, but without
   *   a preceding namespace separator.
   *
   *   The array has a nested structure, where namespaces are grouped by
   *   predictor characters picked at specific positions in the namespace.
   *   The first position is always [0], the second position is determined by
   *   self::PREDICTOR_INDEX, which is hardcoded to [9].
   *   If the namespace is shorter than that, only the first character is used.
   *
   *   E.g. a possible value of this variable could be:
   *
   *   array(
   *     'Dr' => array('Drupal\Core\\' => 12),
   *     'Dm' => array(
   *       'Drupal\Component\\' => 17,
   *       'Drupal\number\\' => 14,
   *     ),
   *     'S' => array('Symfony\\' => 8),
   *   )
   */
  private $prefixLengthsPsr4 = array();

  /**
   * @var array
   *   Namespaces mapped to PSR-4 directories.
   *
   *   Namespaces are represented with trailing namespace separator, but without
   *   a preceding namespace separator.
   *
   *   Directories for each namespace are represented as a numeric array.
   *   The loader is designed to work whether or not the directories have a
   *   trailing directory separator.
   *
   *   E.g. a possible value of this variable could be:
   *
   *   array(
   *     'Drupal\Core\\' => array(DRUPAL_ROOT . '/core/lib/Drupal/Core'),
   *     'Drupal\Component\\' => array(DRUPAL_ROOT . '/core/lib/Drupal/Component'),
   *   )
   */
  private $prefixDirsPsr4 = array();

  /**
   * @var array
   *   PSR-4 directories to use if no matching namespace is found.
   */
  private $fallbackDirsPsr4 = array();

  /**
   * @var array
   *   Prefixes mapped to PSR-0 directories.
   *
   *   The array has a nested structure, where prefixes are grouped by their
   *   first character.
   *
   *   E.g. a possible value of this variable could be:
   *
   *   array(
   *     'D' => array(
   *       'Drupal\Core\\' => array(DRUPAL_ROOT . '/core/lib'),
   *       'Drupal\Component\\' => array(DRUPAL_ROOT . '/core/lib'),
   *       'Drupal\system\\' => array(DRUPAL_ROOT . '/core/modules/system/lib'),
   *     ),
   *     'S' => array(
   *       'Symfony\Component\Routing\\' => array(..),
   *       'Symfony\Component\Process\\' => array(..),
   *     ),
   *   ),
   */
  private $prefixesPsr0 = array();

  /**
   * @var array
   *   PSR-0 directories to use if no matching prefix is found.
   */
  private $fallbackDirsPsr0 = array();

  /**
   * @var bool
   *   TRUE, if the autoloader uses the include path to check for classes.
   */
  private $useIncludePath = FALSE;

  /**
   * @var array
   *   Specific classes mapped to specific PHP files.
   */
  private $classMap = array();

  /**
   * @const
   *   Position in a fully-qualified class name where a character should be
   *   picked to build the index for $prefixLengthsPsr4.
   *   The position [9] has been chosen because it provides a good distribution
   *   of the typical namespaces in a Drupal project.
   */
  const PREDICTOR_INDEX = 9;

  /**
   * Gets the registered prefixes for PSR-0 directories.
   *
   * @return array
   *   Registered prefixes mapped to PSR-0 directories.
   */
  public function getPrefixes() {
    return call_user_func_array('array_merge', $this->prefixesPsr0);
  }

  /**
   * Gets the registered namespaces for PSR-4 directories.
   *
   * @return array
   *   Namespaces mapped to PSR-4 directories.
   */
  public function getPrefixesPsr4() {
    return $this->prefixDirsPsr4;
  }

  /**
   * Gets the PSR-0 fallback directories.
   *
   * @return array
   *   PSR-0 directories to use if no matching prefix is found.
   */
  public function getFallbackDirs() {
    return $this->fallbackDirsPsr0;
  }

  /**
   * Gets the PSR-4 fallback directories.
   *
   * @return array
   *   PSR-0 directories to use if no matching prefix is found.
   */
  public function getFallbackDirsPsr4() {
    return $this->fallbackDirsPsr4;
  }

  /**
   * Gets the class map.
   *
   * @return array
   *   Specific classes mapped to specific PHP files.
   */
  public function getClassMap() {
    return $this->classMap;
  }

  /**
   * Adds a class map.
   *
   * @param array $classMap
   *   Specific classes mapped to specific PHP files.
   */
  public function addClassMap(array $classMap) {
    if ($this->classMap) {
      $this->classMap = array_merge($this->classMap, $classMap);
    }
    else {
      $this->classMap = $classMap;
    }
  }

  /**
   * Adds a set of PSR-0 directories for a given prefix.
   *
   * The directories will be appended or prepended to the ones previously set
   * for this prefix, depending on the $prepend parameter.
   *
   * @param string $prefix
   *   The prefix.
   * @param array|string $paths
   *   The PSR-0 root directories.
   * @param bool $prepend
   *   (optional) Whether to prepend the directories.
   */
  public function add($prefix, $paths, $prepend = FALSE) {
    if (!$prefix) {
      if ($prepend) {
        $this->fallbackDirsPsr0 = array_merge(
          (array) $paths,
          $this->fallbackDirsPsr0
        );
      }
      else {
        $this->fallbackDirsPsr0 = array_merge(
          $this->fallbackDirsPsr0,
          (array) $paths
        );
      }

      return;
    }

    $first = $prefix[0];
    if (!isset($this->prefixesPsr0[$first][$prefix])) {
      $this->prefixesPsr0[$first][$prefix] = (array) $paths;

      return;
    }
    if ($prepend) {
      $this->prefixesPsr0[$first][$prefix] = array_merge(
        (array) $paths,
        $this->prefixesPsr0[$first][$prefix]
      );
    }
    else {
      $this->prefixesPsr0[$first][$prefix] = array_merge(
        $this->prefixesPsr0[$first][$prefix],
        (array) $paths
      );
    }
  }

  /**
   * Adds a set of PSR-4 directories for a given namespace.
   *
   * The directories will be appended or prepended to the ones previously set
   * for this prefix, depending on the $prepend parameter.
   *
   * @param string $prefix
   *   The prefix/namespace, with trailing '\\'.
   * @param array|string $paths
   *   The PSR-0 base directories.
   * @param bool $prepend
   *   (optional) Whether to prepend the directories.
   *
   * @throws \Exception
   *   Throws an exception if the prefix does not end with a trailing namespace
   *   separator.
   */
  public function addPsr4($prefix, $paths, $prepend = FALSE) {
    if (!$prefix) {
      // Register directories for the root namespace.
      if ($prepend) {
        $this->fallbackDirsPsr4 = array_merge(
          (array) $paths,
          $this->fallbackDirsPsr4
        );
      }
      else {
        $this->fallbackDirsPsr4 = array_merge(
          $this->fallbackDirsPsr4,
          (array) $paths
        );
      }
    }
    elseif (!isset($this->prefixDirsPsr4[$prefix])) {
      // Register directories for a new namespace.
      $length = strlen($prefix);
      if ('\\' !== $prefix[$length - 1]) {
        throw new \Exception("A non-empty PSR-4 prefix must end with a namespace separator.");
      }
      if ($length > self::PREDICTOR_INDEX) {
        // The namespace is long enough to have a character at position [9].
        $predictor = $prefix[0] . $prefix[self::PREDICTOR_INDEX];
        $this->prefixLengthsPsr4[$predictor][$prefix] = $length;
      }
      else {
        // The namespace is too short to have a character at position [9].
        $this->prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
      }
      $this->prefixDirsPsr4[$prefix] = (array) $paths;
    }
    elseif ($prepend) {
      // Prepend directories for an already registered namespace.
      $this->prefixDirsPsr4[$prefix] = array_merge(
        (array) $paths,
        $this->prefixDirsPsr4[$prefix]
      );
    }
    else {
      // Append directories for an already registered namespace.
      $this->prefixDirsPsr4[$prefix] = array_merge(
        $this->prefixDirsPsr4[$prefix],
        (array) $paths
      );
    }
  }

  /**
   * Sets/Overwrites the PSR-0 directories for a given prefix.
   *
   * This will replace any directories that were previously set for this prefix.
   *
   * @param string $prefix
   *   The prefix.
   * @param array|string $paths
   *   The PSR-0 base directories.
   */
  public function set($prefix, $paths) {
    if (!$prefix) {
      $this->fallbackDirsPsr0 = (array) $paths;
    }
    else {
      $this->prefixesPsr0[$prefix[0]][$prefix] = (array) $paths;
    }
  }

  /**
   * Sets/Overwrites the PSR-4 directories for a given prefix.
   *
   * This will replace any directories that were previously set for this prefix.
   *
   * @param string $prefix
   *   The prefix/namespace, with trailing '\\'
   * @param array|string $paths
   *   The PSR-4 base directories
   *
   * @throws \Exception
   *   Throws an exception if the prefix does not end with a trailing namespace
   *   separator.
   */
  public function setPsr4($prefix, $paths) {
    if (!$prefix) {
      $this->fallbackDirsPsr4 = (array) $paths;
    }
    else {
      $length = strlen($prefix);
      if ('\\' !== $prefix[$length - 1]) {
        throw new \Exception("A non-empty PSR-4 prefix must end with a namespace separator.");
      }
      if ($length > self::PREDICTOR_INDEX) {
        $predictor = $prefix[0] . $prefix[self::PREDICTOR_INDEX];
        $this->prefixLengthsPsr4[$predictor][$prefix] = $length;
      }
      else {
        $this->prefixLengthsPsr4[$prefix[0]][$prefix] = $length;
      }
      $this->prefixDirsPsr4[$prefix] = (array) $paths;
    }
  }

  /**
   * Turns on searching the include path for class files.
   *
   * @param bool $useIncludePath
   */
  public function setUseIncludePath($useIncludePath) {
    $this->useIncludePath = $useIncludePath;
  }

  /**
   * Can be used to check if the autoloader uses the include path to check
   * for classes.
   *
   * @return bool
   */
  public function getUseIncludePath() {
    return $this->useIncludePath;
  }

  /**
   * Registers this instance as an autoloader.
   *
   * @param bool $prepend
   *   (optional) Whether to prepend the autoloader or not
   */
  public function register($prepend = FALSE) {
    spl_autoload_register(array($this, 'loadClass'), TRUE, $prepend);
  }

  /**
   * Unregisters this instance as an autoloader.
   */
  public function unregister() {
    spl_autoload_unregister(array($this, 'loadClass'));
  }

  /**
   * Loads the given class or interface.
   *
   * @param string $class
   *   The name of the class.
   *
   * @return bool|null
   *   TRUE if loaded, NULL otherwise.
   */
  public function loadClass($class) {
    if ($file = $this->findFile($class)) {
      include $file;

      return TRUE;
    }

    return NULL;
  }

  /**
   * Finds the path to the file where the class is defined.
   *
   * @param string $class
   *   The name of the class.
   *
   * @return string|FALSE
   *   The path if found, FALSE otherwise.
   */
  public function findFile($class) {
    // work around for PHP 5.3.0 - 5.3.2 https://bugs.php.net/50731.
    if ('\\' == $class[0]) {
      $class = substr($class, 1);
    }

    // class map lookup.
    if (isset($this->classMap[$class])) {
      return $this->classMap[$class];
    }

    // PSR-4 lookup.
    $logicalPathPsr4 = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

    // Check if the class is in any of the namespaces registered for PSR-4,
    // and that are long enough to have a character at the predictor index.
    $first = $class[0];
    if (isset($class[self::PREDICTOR_INDEX])) {
      $predictor = $first . $class[self::PREDICTOR_INDEX];
      if (isset($this->prefixLengthsPsr4[$predictor])) {
        foreach ($this->prefixLengthsPsr4[$predictor] as $prefix => $length) {
          # echo '.';
          if (0 === strpos($class, $prefix)) {
            foreach ($this->prefixDirsPsr4[$prefix] as $dir) {
              if (file_exists($file = $dir . DIRECTORY_SEPARATOR . substr($logicalPathPsr4, $length))) {
                # echo "\n";
                return $file;
              }
            }
          }
        }
        # echo "\n";
      }
    }

    // Check if the class is in any of the namespaces registered for PSR-4,
    // that are too short to have a character at the predictor index.
    if (isset($this->prefixLengthsPsr4[$first])) {
      foreach ($this->prefixLengthsPsr4[$first] as $prefix => $length) {
        if (0 === strpos($class, $prefix)) {
          foreach ($this->prefixDirsPsr4[$prefix] as $dir) {
            if (file_exists($file = $dir . DIRECTORY_SEPARATOR . substr($logicalPathPsr4, $length))) {
              return $file;
            }
          }
        }
      }
    }

    // PSR-4 fallback dirs.
    foreach ($this->fallbackDirsPsr4 as $dir) {
      if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr4)) {
        return $file;
      }
    }

    // PSR-0 lookup.
    if (FALSE !== $pos = strrpos($class, '\\')) {
      // namespaced class name.
      $logicalPathPsr0
        = substr($logicalPathPsr4, 0, $pos + 1)
        . strtr(substr($logicalPathPsr4, $pos + 1), '_', DIRECTORY_SEPARATOR)
      ;
    }
    else {
      // PEAR-like class name.
      $logicalPathPsr0 = strtr($class, '_', DIRECTORY_SEPARATOR) . '.php';
    }

    // Check if the class matches any of the prefixes registered for PSR-0.
    if (isset($this->prefixesPsr0[$first])) {
      foreach ($this->prefixesPsr0[$first] as $prefix => $dirs) {
        if (0 === strpos($class, $prefix)) {
          foreach ($dirs as $dir) {
            if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
              return $file;
            }
          }
        }
      }
    }

    // PSR-0 fallback dirs.
    foreach ($this->fallbackDirsPsr0 as $dir) {
      if (file_exists($file = $dir . DIRECTORY_SEPARATOR . $logicalPathPsr0)) {
        return $file;
      }
    }

    // PSR-0 include paths.
    if ($this->useIncludePath && $file = stream_resolve_include_path($logicalPathPsr0)) {
      return $file;
    }

    // Remember that this class does not exist.
    return $this->classMap[$class] = FALSE;
  }

}
