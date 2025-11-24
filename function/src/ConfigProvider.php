<?php

declare(strict_types=1);

namespace App;

use Laminas\ServiceManager\AbstractFactory\ReflectionBasedAbstractFactory;

/**
 * Application configuration provider
 */
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                Handler\HealthHandler::class => ReflectionBasedAbstractFactory::class,
                Handler\BenchmarkHandler::class => ReflectionBasedAbstractFactory::class,
            ],
        ];
    }
}