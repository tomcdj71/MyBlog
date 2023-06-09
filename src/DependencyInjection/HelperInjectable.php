<?php

declare(strict_types=1);

namespace App\DependencyInjection;

use App\Helper\SecurityHelper;
use App\Helper\StringHelper;
use App\Helper\TwigHelper;
use App\Manager\UserManager;
use App\Router\Route;
use App\Router\ServerRequest;
use App\Router\Session;

class HelperInjectable
{
    public const HELPER = [
        'image' => ImageHelper::class,
        'security' => SecurityHelper::class,
        'string' => StringHelper::class,
        'twig' => TwigHelper::class,
    ];

    public const HELPER_DEPENDENCIES = [
        'security' => [UserManager::class, Session::class],
        'string' => [\Normalizer::class],
        'twig' => [ServerRequest::class, Route::class],
    ];

    public static function register(Container $container): void
    {
        // build helpers
        foreach (self::HELPER as $helperKey => $helperClass) {
            $container->set($helperClass, function (Container $container) use ($helperClass, $helperKey) {
                $dependencies = [];
                foreach (self::HELPER_DEPENDENCIES[$helperKey] ?? [] as $dependencyClass) {
                    $dependencies[] = $container->get($dependencyClass);
                }

                if (ImageHelper::class === $helperClass) {
                    $uploadDir = 'uploads/';
                    $height = 1200;
                    $width = 900;
                    array_push($dependencies, $uploadDir, $height, $width);
                }

                return new $helperClass(...$dependencies);
            });
        }
    }
}
