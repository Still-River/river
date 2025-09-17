<?php

declare(strict_types=1);

namespace App\Routes;

use App\Controllers\AuthController;
use App\Controllers\HealthcheckController;
use App\Controllers\JournalController;
use Slim\App;

class ApiRoutes
{
    public static function register(App $app): void
    {
        $app->get('/health', [HealthcheckController::class, 'status']);
        $app->get('/auth/google/url', [AuthController::class, 'googleAuthUrl']);
        $app->get('/auth/google/callback', [AuthController::class, 'googleCallback']);
        $app->get('/auth/me', [AuthController::class, 'currentUser']);
        $app->post('/auth/logout', [AuthController::class, 'logout']);

        $app->get('/journals', [JournalController::class, 'index']);
        $app->get('/journals/{journalId}', [JournalController::class, 'show']);
        $app->put('/journals/{journalId}/responses', [JournalController::class, 'saveResponses']);
    }
}
