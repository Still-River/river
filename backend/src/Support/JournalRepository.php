<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;

class JournalRepository
{
    private const DEFAULT_JOURNAL_SLUG = 'values-journal';

    public function __construct(private PDO $pdo)
    {
        $this->ensureTables();
        $this->seedDefaults();
    }

    /**
     * @return array<int, array{id:int, slug:string, title:string, description:?string}>
     */
    public function listJournals(): array
    {
        $statement = $this->pdo->query('SELECT id, slug, title, description FROM journals ORDER BY id ASC');

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'slug' => (string) $row['slug'],
                'title' => (string) $row['title'],
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
            ],
            $statement->fetchAll() ?: []
        );
    }

    /**
     * @return array{journal: array{id:int, slug:string, title:string, description:?string}, prompts: array<int, array{id:int, key:string, title:string, question:string, guidance:string, placeholder:string, examples: array<int, string>, optional:bool, position:int}>}|null
     */
    public function getJournalWithPrompts(string $identifier): ?array
    {
        $journal = $this->findJournal($identifier);
        if ($journal === null) {
            return null;
        }

        $promptsStatement = $this->pdo->prepare(
            'SELECT id, prompt_key, title, question, guidance, placeholder, examples, optional, position
             FROM journal_prompts
             WHERE journal_id = :journal_id
             ORDER BY position ASC'
        );
        $promptsStatement->execute(['journal_id' => $journal['id']]);

        $prompts = [];
        foreach ($promptsStatement->fetchAll() ?: [] as $row) {
            try {
                $examples = json_decode((string) $row['examples'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                $examples = [];
            }

            $prompts[] = [
                'id' => (int) $row['id'],
                'key' => (string) $row['prompt_key'],
                'title' => (string) $row['title'],
                'question' => (string) $row['question'],
                'guidance' => (string) $row['guidance'],
                'placeholder' => (string) $row['placeholder'],
                'examples' => array_values(array_map('strval', $examples)),
                'optional' => (bool) $row['optional'],
                'position' => (int) $row['position'],
            ];
        }

        return [
            'journal' => $journal,
            'prompts' => $prompts,
        ];
    }

    public function getJournalIdBySlug(string $slug): ?int
    {
        $statement = $this->pdo->prepare('SELECT id FROM journals WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $slug]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * @return array{activeStep:int, todo:bool, skippedAt:?string, lastUpdated:?string}|null
     */
    public function getUserState(int $userId, int $journalId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT active_step, todo, skipped_at, updated_at
             FROM journal_user_states
             WHERE user_id = :user_id AND journal_id = :journal_id
             LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'journal_id' => $journalId,
        ]);

        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'activeStep' => (int) $row['active_step'],
            'todo' => (bool) $row['todo'],
            'skippedAt' => $row['skipped_at'] !== null ? (string) $row['skipped_at'] : null,
            'lastUpdated' => $row['updated_at'] !== null ? (string) $row['updated_at'] : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getUserResponses(int $userId, int $journalId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT jp.prompt_key, jr.response
             FROM journal_responses jr
             INNER JOIN journal_prompts jp ON jp.id = jr.prompt_id
             WHERE jr.user_id = :user_id AND jr.journal_id = :journal_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'journal_id' => $journalId,
        ]);

        $results = [];
        foreach ($statement->fetchAll() ?: [] as $row) {
            $results[(string) $row['prompt_key']] = (string) $row['response'];
        }

        return $results;
    }

    /**
     * @param array<string, array{id:int, optional:bool}> $promptMap
     * @param array{responses?: array<string, string>, activeStep?: int, todo?: bool, skippedAt?: ?string} $payload
     * @return array{activeStep:int, todo:bool, skippedAt:?string, lastUpdated:string}
     */
    public function saveJournalProgress(int $userId, int $journalId, array $promptMap, array $payload): array
    {
        $responses = $payload['responses'] ?? [];
        if (!is_array($responses)) {
            throw new RuntimeException('Invalid responses payload');
        }

        $activeStep = isset($payload['activeStep']) ? max(0, (int) $payload['activeStep']) : 0;
        $todo = isset($payload['todo']) ? (bool) $payload['todo'] : false;
        $skippedAt = $payload['skippedAt'] ?? null;

        $skippedAtValue = null;
        if ($skippedAt !== null && $skippedAt !== '') {
            $skippedAtValue = $this->normaliseDate($skippedAt);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $nowFormatted = $now->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();

        try {
            if ($responses !== []) {
                $insertResponse = $this->pdo->prepare(
                    'INSERT INTO journal_responses (user_id, journal_id, prompt_id, response, created_at, updated_at)
                     VALUES (:user_id, :journal_id, :prompt_id, :response, :created_at, :updated_at)
                     ON DUPLICATE KEY UPDATE response = VALUES(response), updated_at = VALUES(updated_at)'
                );

                foreach ($responses as $promptKey => $responseText) {
                    if (!isset($promptMap[$promptKey])) {
                        continue;
                    }

                    $insertResponse->execute([
                        'user_id' => $userId,
                        'journal_id' => $journalId,
                        'prompt_id' => $promptMap[$promptKey]['id'],
                        'response' => (string) $responseText,
                        'created_at' => $nowFormatted,
                        'updated_at' => $nowFormatted,
                    ]);
                }
            }

            $stateStatement = $this->pdo->prepare(
                'INSERT INTO journal_user_states (user_id, journal_id, active_step, todo, skipped_at, created_at, updated_at)
                 VALUES (:user_id, :journal_id, :active_step, :todo, :skipped_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    active_step = VALUES(active_step),
                    todo = VALUES(todo),
                    skipped_at = VALUES(skipped_at),
                    updated_at = VALUES(updated_at)'
            );

            $stateStatement->execute([
                'user_id' => $userId,
                'journal_id' => $journalId,
                'active_step' => $activeStep,
                'todo' => $todo ? 1 : 0,
                'skipped_at' => $skippedAtValue,
                'created_at' => $nowFormatted,
                'updated_at' => $nowFormatted,
            ]);

            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }

        return [
            'activeStep' => $activeStep,
            'todo' => $todo,
            'skippedAt' => $skippedAtValue,
            'lastUpdated' => $nowFormatted,
        ];
    }

    /**
     * @return array{id:int, slug:string, title:string, description:?string}|null
     */
    private function findJournal(string $identifier): ?array
    {
        if (ctype_digit($identifier)) {
            $statement = $this->pdo->prepare('SELECT id, slug, title, description FROM journals WHERE id = :id LIMIT 1');
            $statement->execute(['id' => (int) $identifier]);
        } else {
            $statement = $this->pdo->prepare('SELECT id, slug, title, description FROM journals WHERE slug = :slug LIMIT 1');
            $statement->execute(['slug' => $identifier]);
        }

        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'slug' => (string) $row['slug'],
            'title' => (string) $row['title'],
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
        ];
    }

    private function ensureTables(): void
    {
        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS journals (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(64) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );

        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS journal_prompts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                journal_id INT UNSIGNED NOT NULL,
                prompt_key VARCHAR(64) NOT NULL,
                title VARCHAR(255) NOT NULL,
                question TEXT NOT NULL,
                guidance LONGTEXT NOT NULL,
                placeholder TEXT NOT NULL,
                examples JSON NOT NULL,
                optional TINYINT(1) NOT NULL DEFAULT 0,
                position INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY journal_prompt_key_unique (journal_id, prompt_key),
                UNIQUE KEY journal_prompt_position_unique (journal_id, position),
                CONSTRAINT fk_journal_prompt_journal FOREIGN KEY (journal_id) REFERENCES journals(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );

        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS journal_responses (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                journal_id INT UNSIGNED NOT NULL,
                prompt_id INT UNSIGNED NOT NULL,
                response LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_journal_response_user_prompt (user_id, prompt_id),
                KEY idx_journal_response_user_journal (user_id, journal_id),
                CONSTRAINT fk_journal_response_prompt FOREIGN KEY (prompt_id) REFERENCES journal_prompts(id) ON DELETE CASCADE,
                CONSTRAINT fk_journal_response_journal FOREIGN KEY (journal_id) REFERENCES journals(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );

        $this->pdo->exec(
            <<<'SQL'
            CREATE TABLE IF NOT EXISTS journal_user_states (
                user_id INT UNSIGNED NOT NULL,
                journal_id INT UNSIGNED NOT NULL,
                active_step INT UNSIGNED NOT NULL DEFAULT 0,
                todo TINYINT(1) NOT NULL DEFAULT 0,
                skipped_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (user_id, journal_id),
                CONSTRAINT fk_journal_user_state_journal FOREIGN KEY (journal_id) REFERENCES journals(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );
    }

    private function seedDefaults(): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        $journalId = $this->getJournalIdBySlug(self::DEFAULT_JOURNAL_SLUG);
        if ($journalId === null) {
            $insertJournal = $this->pdo->prepare(
                'INSERT INTO journals (slug, title, description, created_at, updated_at) VALUES (:slug, :title, :description, :created_at, :updated_at)'
            );
            $insertJournal->execute([
                'slug' => self::DEFAULT_JOURNAL_SLUG,
                'title' => 'Values Priming Journal',
                'description' => 'Four reflective prompts that surface the values guiding your decisions.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $journalId = (int) $this->pdo->lastInsertId();
        }

        $prompts = $this->defaultPrompts();
        $upsertPrompt = $this->pdo->prepare(
            'INSERT INTO journal_prompts (journal_id, prompt_key, title, question, guidance, placeholder, examples, optional, position, created_at, updated_at)
             VALUES (:journal_id, :prompt_key, :title, :question, :guidance, :placeholder, :examples, :optional, :position, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                question = VALUES(question),
                guidance = VALUES(guidance),
                placeholder = VALUES(placeholder),
                examples = VALUES(examples),
                optional = VALUES(optional),
                position = VALUES(position),
                updated_at = VALUES(updated_at)'
        );

        foreach ($prompts as $index => $prompt) {
            $upsertPrompt->execute([
                'journal_id' => $journalId,
                'prompt_key' => $prompt['key'],
                'title' => $prompt['title'],
                'question' => $prompt['question'],
                'guidance' => $prompt['guidance'],
                'placeholder' => $prompt['placeholder'],
                'examples' => json_encode($prompt['examples'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'optional' => $prompt['optional'] ? 1 : 0,
                'position' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array<int, array{key:string, title:string, question:string, guidance:string, placeholder:string, examples:array<int, string>, optional:bool}>
     */
    private function defaultPrompts(): array
    {
        return [
            [
                'key' => 'strongMemories',
                'title' => 'Strong Memories',
                'question' => 'Bring to mind a vivid, meaningful moment. What happened, who was involved, and which personal values were present or tested?',
                'examples' => [
                    'Helping my sister move apartments reminded me how much I prioritise generosity, reliability, and family support.',
                    'When I turned down a promotion that conflicted with travel plans I had promised friends, I realised how strongly I value loyalty and shared experiences.',
                    'Finishing my first marathon rekindled my commitment to resilience, disciplined practice, and self-respect.',
                ],
                'guidance' => <<<'TEXT'
                When a memory feels powerful, it usually carries an emotional charge that points to what matters most. Describe the scene with sensory detail—the sounds, expressions, and small decisions. Then ask why the moment still stands out: Did you feel proud, frustrated, or affirmed? Those feelings are clues about the values in motion. If the memory was uplifting, name the values you were actively living (perhaps courage, creativity, or belonging). If the memory was difficult, explore which values were squeezed or ignored and how you wished you could have responded. Give yourself permission to write freely without judging whether a recollection is “important enough.” You can always refine later. The aim here is discovering patterns: Do you keep honouring connection? Do you fight for fairness? Do quiet acts of care matter more than headline achievements? As you tag each value, note what it practically looks like for you—what actions, language, and boundaries demonstrate it in everyday life.
                TEXT,
                'placeholder' => 'Capture the scene, who was there, and list the values that were alive for you…',
                'optional' => false,
            ],
            [
                'key' => 'admiredPerson',
                'title' => 'Person You Admire',
                'question' => 'Think of someone you genuinely admire. How do they show up in the world, and which values do they embody that you want to cultivate?',
                'examples' => [
                    'My grandmother writes welcome notes to every new neighbour. She models hospitality, generosity, and steady presence.',
                    'I admire my colleague Amina because she invites quiet voices into meetings. She lives inclusion, curiosity, and accountability.',
                    'The community organiser I follow admits mistakes publicly, demonstrating humility, justice, and courage.',
                ],
                'guidance' => <<<'TEXT'
                Admiration is often projection: the traits you celebrate in someone else are the ones you are ready to practise. Describe specific behaviours rather than vague praise. How does this person treat people when no one is watching? What choices do they make when rushed or under pressure? Detailing concrete scenes keeps your reflection grounded. Next, translate each admired behaviour into a value word and define it in your own language. “Integrity” might mean telling the truth faster, “generosity” could mean unblocking teammates, and “creativity” might be giving yourself permission to iterate out loud. Finally, connect their example to your next decision. Where in your calendar or routines could you mirror one of these values this week? Be honest about any resistance and name the trade-offs you would need to accept. The more tangible you make these observations, the easier it becomes to practise them instead of only admiring them from afar.
                TEXT,
                'placeholder' => 'Describe the person, the behaviours you admire, and the values you want to practise…',
                'optional' => false,
            ],
            [
                'key' => 'recurringSituations',
                'title' => 'Recurring Situations',
                'question' => 'Notice a situation that keeps showing up in your life. When you react, which values are driving you, and which do you want to bring forward next time?',
                'examples' => [
                    'During weekly product reviews I get defensive. I care about craftsmanship, but I want to foreground learning and partnership instead.',
                    'Family dinners often veer into politics, and I withdraw. I value harmony, yet I also want to practise courage and respectful dialogue.',
                    'When deadlines compress, I skip breaks. It comes from ambition and responsibility, but I would rather lead with sustainability and trust.',
                ],
                'guidance' => <<<'TEXT'
                Patterns reveal value conflicts. Start by describing the recurring scene in detail: Who is present, what typically triggers you, and how does your body respond? Then list the values that fuel your default reaction. Perhaps efficiency makes you steamroll teammates, or loyalty makes you stay silent. There is no shame in noticing the values behind imperfect behaviour—they are still values. Next, identify the values you would prefer to lead with. What would choosing collaboration over control look like? How might it sound to defend both truth and care simultaneously? Close by planning a small shift for the next time the situation appears. Practise one sentence, one breath, or one new boundary that reflects your preferred values. These micro-adjustments compound quickly because the situation keeps repeating. Each iteration becomes a rehearsal for living aligned.
                TEXT,
                'placeholder' => 'Map the situation, your default reaction, and the values you will lead with next time…',
                'optional' => false,
            ],
            [
                'key' => 'legacyVision',
                'title' => 'Legacy Vision (Optional)',
                'question' => 'Imagine your 80th-birthday celebration or a future moment where life feels deeply fulfilling. What do people thank you for, and which values shaped that legacy?',
                'examples' => [
                    'At my 80th, friends describe how I built spaces where people felt seen—valuing belonging, hospitality, and steady mentorship.',
                    'My future grandkids share stories about impromptu adventures, pointing to spontaneity, curiosity, and courage.',
                    'Colleagues toast to a culture I protected that paired excellence with compassion and honest feedback.',
                ],
                'guidance' => <<<'TEXT'
                Legacy work is about orientation, not perfection. Picture the setting with colour: the food, music, faces, and small gestures. Let the people around you speak—what do they say you made possible? Translate every compliment into a value you practised consistently. If someone thanks you for “always showing up,” maybe consistency and devotion were your compass. If they celebrate how you challenged them, perhaps growth and truth-telling guided you. Once you surface the values, connect them to actionable rhythms: What would you need to stop, start, or continue this month to make that future story plausible? Embrace the emotional tone as well—are people laughing, feeling safe, or energised? Those cues help you prioritise values that shape atmosphere, not just achievement. Treat this exercise as a living invitation. You can revise it as seasons change, but writing it now gives you a north star that keeps everyday decisions aligned with the legacy you want to leave.
                TEXT,
                'placeholder' => 'Let the future scene unfold and highlight the values people celebrate in you…',
                'optional' => true,
            ],
        ];
    }

    private function normaliseDate(string $input): string
    {
        try {
            $date = new DateTimeImmutable($input);
        } catch (\Exception $exception) {
            throw new RuntimeException('Invalid date format provided');
        }

        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
