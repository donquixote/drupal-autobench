<?php

use Drupal\autobench\Main;

/**
 * Implements hook_drush_command()
 */
function autobench_drush_command() {
  $items['autobench'] = array(
    'description' => dt('Runs a number of benchmarks for different class loaders.'),
  );
  return $items;
}

/**
 * Drush callback for "drush autobench"
 */
function drush_autobench() {
  $main = new Main();
  $main->runAll();
}

