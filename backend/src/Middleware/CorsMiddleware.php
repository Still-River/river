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
            $response = new Response(204);
            return $this->applyHeaders($request, $response);
        }

        $response = $handler->handle($request);

        return $this->applyHeaders($request, $response);
    }

    private function applyHeaders(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $allowedOrigins = $this->normaliseOrigins($this->config['allowed_origins'] ?? []);
        $origin = trim($request->getHeaderLine('Origin'));
        $allowCredentials = (bool) ($this->config['allow_credentials'] ?? false);

        if ($origin !== '') {
            $originToMatch = rtrim($origin, '/');

            if ($allowedOrigins === ['*']) {
                $response = $response->withHeader('Access-Control-Allow-Origin', $allowCredentials ? $origin : '*');
            } elseif (in_array($originToMatch, $allowedOrigins, true)) {
                $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
            }
        } elseif ($allowedOrigins === ['*'] && !$allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Origin', '*');
        }

        if ($response->hasHeader('Access-Control-Allow-Origin')) {
            $response = $response->withAddedHeader('Vary', 'Origin');

            $methods = $this->config['allowed_methods'] ?? ['GET', 'POST', 'OPTIONS'];
            $response = $response->withHeader('Access-Control-Allow-Methods', implode(', ', $methods));

            $requestedHeaders = $request->getHeaderLine('Access-Control-Request-Headers');
            $allowedHeaders = $requestedHeaders !== ''
                ? $requestedHeaders
                : implode(', ', $this->config['allowed_headers'] ?? ['Content-Type', 'Authorization']);

            if ($allowedHeaders !== '') {
                $response = $response->withHeader('Access-Control-Allow-Headers', $allowedHeaders);
            }

            if ($allowCredentials) {
                $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
            }
        }

        return $response;
    }

    /**
     * @param array<int, string> $origins
     * @return array<int, string>
     */
    private function normaliseOrigins(array $origins): array
    {
        if ($origins === []) {
            return [];
        }

        if ($origins === ['*']) {
            return ['*'];
        }

        $normalised = [];
        foreach ($origins as $origin) {
            $trimmed = trim($origin);
            if ($trimmed === '') {
                continue;
            }

            $normalised[] = rtrim($trimmed, '/');
        }

        return $normalised;
    }
}
