<?php


namespace Drupal\autobench\Filesystem;


/**
 *
 */
class TmpFilesystem implements FilesystemInterface {

  /**
   * Adds a file and the directory, if not exists.
   *
   * @param string $file
   * @param string $contents
   *
   * @throws \Exception
   */
  function addFile($file, $contents) {
    if (file_exists($file)) {
      throw new \Exception("File '$file' already exists.");
    }
    $this->addKnownDir(dirname($file));
    file_put_contents($file, $contents);
  }

  /**
   * @param string $dir
   */
  function addKnownDir($dir) {
    if (is_dir($dir)) {
      return;
    }
    // Need to create parents first.
    $this->addKnownDir(dirname($dir));
    mkdir($dir);
  }

  /**
   * @param string $dir
   *
   * @return array|bool
   */
  function getDirContents($dir) {
    return scandir($dir);
  }

  /**
   * @param string $path
   *
   * @return array
   */
  function getStat($path) {
    return stat($path);
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
    return file_get_contents($path);
  }

  /**
   * Unregister the stream wrapper, or delete the tmp folder.
   */
  function cleanUp() {
    // @todo Delete the tmp directory?
  }
}
