<?php

namespace App\Bootstrap;

use App\Routes\ApiRoutes;
use DI\Container;
use Dotenv\Dotenv;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

class AppFactory
{
    public static function create(): App
    {
        self::loadEnvironment();

         = new Container();
        self::registerSettings();

        SlimAppFactory::setContainer();
         = SlimAppFactory::create();

        ->addBodyParsingMiddleware();
        ->addRoutingMiddleware();

        ApiRoutes::register();

        return ;
    }

    private static function loadEnvironment(): void
    {
         = Dotenv::createImmutable(__DIR__ . '/../');
        ->safeLoad();
    }

    private static function registerSettings(Container ): void
    {
         = require __DIR__ . '/../config/settings.php';

        foreach ( as  => ) {
            ->set(, );
        }
    }
}
