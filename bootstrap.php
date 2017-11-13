<?php

// Find composer autoloader.
// Where it is will depend on your workflow.
// The first is for my initial dev environment and the second for travis.

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    include_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}
