<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

class GoogleOAuthService
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $redirectUri,
        private array $scopes,
        private string $prompt,
        private string $accessType
    ) {
    }

    public function buildAuthorizationUrl(string $state, ?string $nonce = null): string
    {
        $this->assertConfiguration();

        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'access_type' => $this->accessType,
            'prompt' => $this->prompt,
            'include_granted_scopes' => 'true',
            'state' => $state,
        ];

        if ($nonce !== null) {
            $params['nonce'] = $nonce;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * @return array{ access_token: string, expires_in?: int, refresh_token?: string, id_token?: string }
     */
    public function fetchTokens(string $code): array
    {
        $this->assertConfiguration();

        $response = $this->request(
            'https://oauth2.googleapis.com/token',
            [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ]
        );

        if (!isset($response['access_token'])) {
            throw new RuntimeException('Google token response missing access_token');
        }

        return $response;
    }

    /**
     * @return array{ sub: string, email?: string, name?: string, picture?: string }
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$accessToken}\r\n",
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $response = file_get_contents('https://openidconnect.googleapis.com/v1/userinfo', false, $context);
        $statusCode = $this->extractStatusCode($http_response_header ?? []);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Failed to fetch Google user information');
        }

        /** @var array{sub?: string, email?: string, name?: string, picture?: string} $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['sub'])) {
            throw new RuntimeException('Invalid Google user information payload');
        }

        return $data;
    }

    /**
     * @param array<string, string> $payload
     * @return array<string, mixed>
     */
    private function request(string $url, array $payload): array
    {
        $this->assertConfiguration();

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($payload),
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $statusCode = $this->extractStatusCode($http_response_header ?? []);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Google OAuth token request failed');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        return $data;
    }

    /**
     * @param string[] $headers
     */
    private function extractStatusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }

    private function assertConfiguration(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->redirectUri === '') {
            throw new RuntimeException('Missing Google OAuth configuration. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI.');
        }
    }
}
