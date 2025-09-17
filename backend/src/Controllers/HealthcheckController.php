<?php

namespace App\Controllers;

use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthcheckController
{
    public function status(Request , Response ): Response
    {
         = [
            'service' => 'river-api',
            'status' => 'ok',
            'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        ->getBody()->write((string) json_encode(, JSON_THROW_ON_ERROR));

        return 
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
}
