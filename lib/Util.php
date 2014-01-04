<?php


namespace Drupal\autobench;


class Util {

  /**
   * Generate a random string made of uppercase and lowercase characters and numbers.
   *
   * @param int $length
   *   Length of the random string to generate
   * @param string $chars
   *   Allowed characters
   * @param string $chars_first
   *   Allowed characters for the first character.
   *
   * @return string
   *   Random string of the specified length
   */
  static function randomString(
    $length = 30,
    $chars = NULL,
    $chars_first = NULL
  ) {

    if (!isset($chars)) {
      $chars = 'abcdefghijklmnopqrstuvwxyz' .
        'ABCDEFGHIJKLMNOPQRSTUVWXYZ' .
        '1234567890';
    }

    if (!isset($chars_first)) {
      $chars_first = $chars;
    }

    // Initialize the randomizer.
    srand((double) microtime() * 1000000);

    $str = substr($chars_first, rand() % strlen($chars_first), 1);
    for ($i = 0; $i < $length; ++$i) {
      $str .= substr($chars, rand() % strlen($chars), 1);
    }

    return $str;
  }

  /**
   * Generate a random string that is a valid PHP identifier.
   *
   * @param int $length
   *   Length of the random string to generate
   *
   * @return string
   *   Random string of the specified length
   */
  static function randomIdentifier($length = 40) {

    // Since PHP is case insensitive, we only user lowercase characters.
    $chars_first = 'abcdefghijklmnopqrstuvwxyz_';
    $chars = 'abcdefghijklmnopqrstuvwxyz_1234567890';

    return self::randomString($length, $chars, $chars_first);
  }
} 