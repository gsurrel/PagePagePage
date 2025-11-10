<?php

define('PAGEPAGEPAGE_TESTING', true);

// Autoload classes based on namespace and directory
spl_autoload_register(function ($class) {
    // Only autoload PagePagePage* namespaces
    if (!str_starts_with($class, 'PagePagePage')) {
        return;
    }

    // Convert namespace to file path
    $baseDir = __DIR__ . '/../';
    $relativePath = str_replace('\\', '/', $class) . '.php';
    $fullPath = $baseDir . $relativePath;

    if (file_exists($fullPath)) {
        require_once $fullPath;
    }
});

require_once __DIR__ . '/helpers/TestContextFactory.php';