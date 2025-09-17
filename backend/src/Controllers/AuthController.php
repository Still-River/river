<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\GoogleOAuthService;
use App\Support\SessionManager;
use App\Support\UserRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class AuthController
{
    private string $defaultOrigin;

    public function __construct(
        private GoogleOAuthService $googleOAuthService,
        private UserRepository $userRepository,
        private SessionManager $sessionManager,
        private string $defaultRedirect
    ) {
        $this->defaultOrigin = $this->extractOrigin($defaultRedirect);
    }

    public function googleAuthUrl(Request $request, Response $response): Response
    {
        $state = $this->sessionManager->generateStateToken();
        $this->sessionManager->storeState($state);

        $params = $request->getQueryParams();
        $redirect = isset($params['redirect']) ? trim((string) $params['redirect']) : null;
        $resolvedRedirect = $this->sanitizeRedirect($redirect);
        $this->sessionManager->rememberPostLoginRedirect($resolvedRedirect);

        try {
            $authUrl = $this->googleOAuthService->buildAuthorizationUrl($state);
        } catch (RuntimeException $exception) {
            return $this->json(
                $response,
                [
                    'error' => 'configuration_error',
                    'message' => $exception->getMessage(),
                ],
                500
            );
        }

        return $this->json($response, ['authUrl' => $authUrl]);
    }

    public function googleCallback(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $redirectBase = $this->sessionManager->popPostLoginRedirect($this->defaultRedirect);

        if (isset($query['error'])) {
            return $this->redirect($response, $this->appendQuery($redirectBase, [
                'error' => (string) $query['error'],
            ]));
        }

        $state = isset($query['state']) ? (string) $query['state'] : null;
        if (!$this->sessionManager->validateState($state)) {
            return $this->redirect($response, $this->appendQuery($redirectBase, [
                'error' => 'invalid_state',
            ]));
        }

        $code = isset($query['code']) ? (string) $query['code'] : null;
        if ($code === null) {
            return $this->redirect($response, $this->appendQuery($redirectBase, [
                'error' => 'missing_code',
            ]));
        }

        try {
            $tokens = $this->googleOAuthService->fetchTokens($code);
            $userInfo = $this->googleOAuthService->fetchUserInfo($tokens['access_token']);
        } catch (RuntimeException $exception) {
            return $this->redirect($response, $this->appendQuery($redirectBase, [
                'error' => 'google_auth_failed',
                'message' => $exception->getMessage(),
            ]));
        }

        $userRecord = $this->userRepository->upsertGoogleUser([
            'google_id' => $userInfo['sub'],
            'email' => $userInfo['email'] ?? '',
            'name' => $userInfo['name'] ?? null,
            'avatar_url' => $userInfo['picture'] ?? null,
        ]);

        $this->sessionManager->regenerate();
        $this->sessionManager->setAuthenticatedUserId((int) $userRecord['id']);

        return $this->redirect($response, $this->appendQuery($redirectBase, [
            'login' => 'success',
        ]));
    }

    public function currentUser(Request $request, Response $response): Response
    {
        $userId = $this->sessionManager->getAuthenticatedUserId();
        if ($userId === null) {
            return $this->json($response, ['user' => null]);
        }

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            $this->sessionManager->clearAuthentication();
            return $this->json($response, ['user' => null]);
        }

        return $this->json($response, [
            'user' => [
                'id' => (int) $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'avatarUrl' => $user['avatar_url'],
            ],
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->sessionManager->clearAuthentication();
        $this->sessionManager->regenerate();

        return $this->json($response, ['loggedOut' => true]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function redirect(Response $response, string $location, int $status = 302): Response
    {
        return $response
            ->withHeader('Location', $location)
            ->withStatus($status);
    }

    /**
     * @param array<string, string> $params
     */
    private function appendQuery(string $url, array $params): string
    {
        if ($params === []) {
            return $url;
        }

        $query = http_build_query($params);
        if ($query === '') {
            return $url;
        }

        $fragment = '';
        $hashPosition = strpos($url, '#');
        if ($hashPosition !== false) {
            $fragment = substr($url, $hashPosition);
            $url = substr($url, 0, $hashPosition);
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . $query . $fragment;
    }

    private function sanitizeRedirect(?string $redirect): string
    {
        if ($redirect === null || $redirect === '') {
            return $this->defaultRedirect;
        }

        if (filter_var($redirect, FILTER_VALIDATE_URL) === false) {
            return $this->defaultRedirect;
        }

        $origin = $this->extractOrigin($redirect);
        if ($this->defaultOrigin !== '' && $origin !== $this->defaultOrigin) {
            return $this->defaultRedirect;
        }

        return $redirect;
    }

    private function extractOrigin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }
}
