<?php
namespace Drupal\autobench\Filesystem;

/**
 *
 */
interface FilesystemInterface {

  /**
   * @param string $file
   * @param string $contents
   */
  function addFile($file, $contents);

  /**
   * @param string $dir
   */
  function addKnownDir($dir);

  /**
   * Returns the equivalent of scandir().
   *
   * @param string $dir
   *
   * @return array|bool
   */
  function getDirContents($dir);

  /**
   * @param string $path
   *
   * @return array
   */
  function getStat($path);

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
  function getFileContents($path);

  /**
   * Unregister the stream wrapper, or delete the tmp folder.
   */
  function cleanUp();
}
