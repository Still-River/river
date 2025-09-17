<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\JournalRepository;
use App\Support\SessionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class JournalController
{
    public function __construct(
        private JournalRepository $journalRepository,
        private SessionManager $sessionManager
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $journals = $this->journalRepository->listJournals();

        return $this->json($response, ['journals' => $journals]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $journalIdentifier = (string) ($args['journalId'] ?? '');
        $result = $this->journalRepository->getJournalWithPrompts($journalIdentifier);

        if ($result === null) {
            return $this->json($response, [
                'error' => 'not_found',
                'message' => 'Journal not found',
            ], 404);
        }

        $journal = $result['journal'];
        $prompts = $result['prompts'];

        $userId = $this->sessionManager->getAuthenticatedUserId();
        $userState = null;
        $responses = [];

        if ($userId !== null) {
            $userState = $this->journalRepository->getUserState($userId, $journal['id']);
            $responses = $this->journalRepository->getUserResponses($userId, $journal['id']);
        }

        return $this->json($response, [
            'journal' => [
                'id' => $journal['id'],
                'slug' => $journal['slug'],
                'title' => $journal['title'],
                'description' => $journal['description'],
                'prompts' => $prompts,
            ],
            'userState' => $userState,
            'responses' => $responses,
        ]);
    }

    public function saveResponses(Request $request, Response $response, array $args): Response
    {
        $journalIdentifier = (string) ($args['journalId'] ?? '');
        $result = $this->journalRepository->getJournalWithPrompts($journalIdentifier);

        if ($result === null) {
            return $this->json($response, [
                'error' => 'not_found',
                'message' => 'Journal not found',
            ], 404);
        }

        $userId = $this->sessionManager->getAuthenticatedUserId();
        if ($userId === null) {
            return $this->json($response, [
                'error' => 'unauthorised',
                'message' => 'Sign in to save journal responses.',
            ], 401);
        }

        $promptMap = [];
        foreach ($result['prompts'] as $prompt) {
            $promptMap[$prompt['key']] = [
                'id' => $prompt['id'],
                'optional' => (bool) $prompt['optional'],
            ];
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return $this->json($response, [
                'error' => 'invalid_payload',
                'message' => 'Expected JSON body.',
            ], 400);
        }

        $responses = isset($body['responses']) && is_array($body['responses'])
            ? $body['responses']
            : [];

        $payload = [
            'responses' => $responses,
            'activeStep' => isset($body['activeStep']) ? (int) $body['activeStep'] : 0,
            'todo' => isset($body['todo']) ? (bool) $body['todo'] : false,
            'skippedAt' => isset($body['skippedAt']) ? (string) $body['skippedAt'] : null,
        ];

        try {
            $state = $this->journalRepository->saveJournalProgress(
                $userId,
                $result['journal']['id'],
                $promptMap,
                $payload
            );
        } catch (RuntimeException $exception) {
            return $this->json($response, [
                'error' => 'invalid_payload',
                'message' => $exception->getMessage(),
            ], 422);
        }

        $savedResponses = $this->journalRepository->getUserResponses($userId, $result['journal']['id']);

        return $this->json($response, [
            'state' => $state,
            'responses' => $savedResponses,
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string) json_encode($data, JSON_THROW_ON_ERROR));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
