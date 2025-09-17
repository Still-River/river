<?php

declare(strict_types=1);

namespace App\Middleware;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param array{ allowed_origins?: array<int, string>, allowed_methods?: array<int, string>, allowed_headers?: array<int, string>, allow_credentials?: bool } $config
     */
    public function __construct(private array $config)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->applyHeaders($request, new Response(204));
        }

        $response = $handler->handle($request);

        return $this->applyHeaders($request, $response);
    }

    private function applyHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = $this->config['allowed_origins'] ?? [];
        $allowCredentials = (bool) ($this->config['allow_credentials'] ?? false);

        if ($origin !== '' && ($allowedOrigins === ['*'] || in_array($origin, $allowedOrigins, true))) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
        } elseif ($allowedOrigins === ['*']) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        $methods = $this->config['allowed_methods'] ?? ['GET', 'POST', 'OPTIONS'];
        $headers = $this->config['allowed_headers'] ?? ['Content-Type'];

        $response = $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $methods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $headers));

        if ($allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
