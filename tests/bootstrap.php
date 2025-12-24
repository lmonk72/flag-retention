<?php

/**
 * @file
 * Bootstrap file for PHPUnit tests.
 */

// Define DRUPAL_ROOT if not already defined.
if (!defined('DRUPAL_ROOT')) {
  // Try to find Drupal root by looking for autoload.php
  $possible_roots = [
    dirname(__DIR__, 4),  // modules/custom/flag_retention
    dirname(__DIR__, 3),  // modules/flag_retention
    dirname(__DIR__),     // flag_retention (standalone testing)
  ];
  
  foreach ($possible_roots as $root) {
    if (file_exists($root . '/autoload.php')) {
      define('DRUPAL_ROOT', $root);
      break;
    }
  }
  
  if (!defined('DRUPAL_ROOT')) {
    die('Unable to locate Drupal root directory. Tests must be run from Drupal installation.');
  }
}

// Load the autoloader.
$autoloader = require DRUPAL_ROOT . '/autoload.php';

// Set up environment variables for testing.
putenv('SIMPLETEST_BASE_URL=http://localhost');
putenv('SIMPLETEST_DB=sqlite://localhost/sites/default/files/.ht.sqlite');

return $autoloader;
