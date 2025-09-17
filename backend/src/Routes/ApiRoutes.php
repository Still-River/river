<?php

namespace App\Routes;

use App\Controllers\HealthcheckController;
use Slim\App;

class ApiRoutes
{
    public static function register(App ): void
    {
        ->get('/health', [HealthcheckController::class, 'status']);
    }
}
