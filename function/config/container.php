<?php

declare(strict_types=1);

use Laminas\ServiceManager\ServiceManager;

/**
 * Container configuration
 *
 * Returns a configured PSR-11 container (Laminas ServiceManager)
 */

// Load configuration
$config = require __DIR__ . '/config.php';

// Create container
$dependencies = $config['dependencies'] ?? [];
$container = new ServiceManager($dependencies);

// Inject config into container
$container->setService('config', $config);

return $container;
