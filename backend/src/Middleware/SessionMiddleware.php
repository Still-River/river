<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class SessionMiddleware implements MiddlewareInterface
{
    /**
     * @param array{ name?: string, lifetime?: int, domain?: string, secure?: bool, same_site?: string } $config
     */
    public function __construct(private array $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $sessionStartedHere = false;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $name = $this->config['name'] ?? 'river_session';
            if ($name !== '') {
                session_name($name);
            }

            session_set_cookie_params([
                'lifetime' => $this->config['lifetime'] ?? 0,
                'path' => '/',
                'domain' => $this->config['domain'] ?? '',
                'secure' => (bool) ($this->config['secure'] ?? false),
                'httponly' => true,
                'samesite' => $this->config['same_site'] ?? 'Lax',
            ]);

            session_start();
            $sessionStartedHere = true;
        }

        try {
            return $handler->handle($request);
        } finally {
            if ($sessionStartedHere && session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }
    }
}
