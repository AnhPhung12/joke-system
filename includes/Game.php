<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Game — Data-Access Model for AI Playground Sessions & Turn History.
 *
 * Purpose: Manages `game_sessions` and `game_messages` across three AI game modes:
 *          `word_chain`, `punchline_guess`, and `joke_battle`.
 * Connections: Called by `App\Controllers\GameController`; delegates mode-specific
 *              AI turns to `WordChainEngine`, `PunchlineGuessEngine`, and `GameEngine`
 *              callbacks.
 * Data Structures & Algorithms:
 *   - Uses ACID transactions with `FOR UPDATE` row locking during `turn()` to
 *     prevent race conditions on concurrent turn submissions.
 *   - Persists JSON-encoded mode state (`metadata`) for continuous multi-round
 *     Word Chain sessions.
 */
final class Game
{
    // Shared PDO connection for game session and message queries
    private PDO $db;

    /**
     * Initialize the model with the shared PDO database connection.
     */
    public function __construct()
    {
        // Acquire singleton PDO connection
        $this->db = Database::connect();
    }

    /**
     * Create a new game session and seed any mode-specific opening AI message.
     *
     * @param int    $user Owner user ID.
     * @param string $type Game mode ('word_chain', 'punchline_guess', 'joke_battle').
     * @return int Newly created session ID.
     */
    public function create(int $user, string $type): int
    {
        // Initialize optional mode-specific JSON state
        $state = null;

        // Generate initial topic and starting word for Word Chain
        if ($type === 'word_chain') {
            $state = (new \App\Services\WordChainEngine())->start();
        }

        // Generate opening riddle/setup question for Guess the Punchline
        if ($type === 'punchline_guess') {
            $state = ['opening_question' => (new \App\Services\PunchlineGuessEngine())->openingQuestion()];
        }

        // Begin transaction to insert session and seed opening AI prompt atomically
        $this->db->beginTransaction();

        try {
            // Insert the new game session record
            $s = $this->db->prepare('INSERT INTO game_sessions(user_id,game_type,metadata) VALUES(?,?,?)');
            $s->execute([$user, $type, $state === null ? null : json_encode($state)]);
            // Capture the newly generated session ID
            $id = (int) $this->db->lastInsertId();

            // Seed opening prompt for Guess the Punchline
            if ($type === 'punchline_guess') {
                $q = 'First question: ' . $state['opening_question'];
                $s = $this->db->prepare(
                    "INSERT INTO game_messages(session_id,sender,content,round_number,validation_status) VALUES(?,'ai',?,0,'not_applicable')"
                );
                $s->execute([$id, $q]);
            }

            // Seed initial topic and starting word for Word Chain
            if ($type === 'word_chain' && $state !== null) {
                // Initialize round counter and topic history
                $state['round_number'] = 1;
                $state['previous_topics'] = [];

                // Persist updated Word Chain metadata
                $this->db->prepare('UPDATE game_sessions SET metadata=? WHERE id=?')
                    ->execute([json_encode($state), $id]);

                // Format introductory AI message with topic, starting word, and required letter
                $intro = 'Topic: ' . $state['topic'] . "\n"
                    . 'Starting word: ' . $state['last_word'] . "\n"
                    . 'Your word must begin with ' . strtoupper(substr($state['last_word'], -1)) . '. You have 3 attempts.';

                // Insert opening AI message into game transcript
                $s = $this->db->prepare(
                    "INSERT INTO game_messages(session_id,sender,content,round_number,validation_status) VALUES(?,'ai',?,0,'not_applicable')"
                );
                $s->execute([$id, $intro]);
            }

            // Commit session creation and return ID
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            // Roll back on any failure
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Fetch a single game session owned by `$user`, optionally locking the row.
     *
     * @return array<string, mixed>|null
     */
    public function session(int $id, int $user, bool $lock = false): ?array
    {
        // Select session scoped to owner, appending FOR UPDATE when inside a turn transaction
        $s = $this->db->prepare(
            'SELECT * FROM game_sessions WHERE id=? AND user_id=?' . ($lock ? ' FOR UPDATE' : '')
        );
        $s->execute([$id, $user]);

        // Return associative row or null
        return $s->fetch() ?: null;
    }

    /**
     * Retrieve the chronological message transcript for a game session.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messages(int $id): array
    {
        // Query all messages for the session ordered chronologically
        $s = $this->db->prepare(
            'SELECT sender,content,round_number,score,validation_status FROM game_messages WHERE session_id=? ORDER BY id'
        );
        $s->execute([$id]);

        return $s->fetchAll();
    }

    /**
     * Delete a game session owned by `$user`.
     */
    public function delete(int $id, int $user): bool
    {
        // Delete session only if owned by the specified user
        $s = $this->db->prepare('DELETE FROM game_sessions WHERE id=? AND user_id=?');
        $s->execute([$id, $user]);

        // Confirm exactly one row was deleted
        return $s->rowCount() === 1;
    }

    /**
     * Prune empty sessions with no user turns and return the user's 30 most recent games.
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(int $user): array
    {
        // Clean up abandoned sessions where the user never submitted a message
        $cleanup = $this->db->prepare(
            "DELETE gs FROM game_sessions gs WHERE gs.user_id=? AND NOT EXISTS (SELECT 1 FROM game_messages gm WHERE gm.session_id=gs.id AND gm.sender='user')"
        );
        $cleanup->execute([$user]);

        // Fetch the user's 30 most recent active/completed sessions with at least one user turn
        $s = $this->db->prepare(
            "SELECT id,game_type,status,player_score,ai_score,result,started_at FROM game_sessions WHERE user_id=? AND EXISTS (SELECT 1 FROM game_messages gm WHERE gm.session_id=game_sessions.id AND gm.sender='user') ORDER BY id DESC LIMIT 30"
        );
        $s->execute([$user]);

        return $s->fetchAll();
    }

    /**
     * Process a single player turn, persist messages, and update session scores/state.
     *
     * @param int      $id     Session ID.
     * @param int      $user   Owner user ID.
     * @param string   $input  Player's submitted text.
     * @param callable $engine Callback `fn(array $session, array $history, string $input): array`.
     * @return array<string, mixed> Engine result merged with updated score/status metadata.
     */
    public function turn(int $id, int $user, string $input, callable $engine): array
    {
        // Start ACID transaction to serialize concurrent turn submissions
        $this->db->beginTransaction();

        try {
            // Lock session row and ensure it is still in progress
            $session = $this->session($id, $user, true);
            if (!$session || $session['status'] !== 'in_progress') {
                throw new \RuntimeException('The game does not exist or has already ended.');
            }

            // Load prior message transcript for AI context
            $history = $this->messages($id);

            // Word Chain uses stateful JSON metadata and continuous multi-round play
            if ($session['game_type'] === 'word_chain') {
                // Decode and validate Word Chain state from session metadata
                $state = json_decode((string) $session['metadata'], true);
                if (!is_array($state) || !isset($state['topic'], $state['last_word'])) {
                    throw new \RuntimeException('Please start a new Topic Word Chain session.');
                }

                // Evaluate player's word against current topic and chain rules
                $result = (new \App\Services\WordChainEngine())->play($input, $state);
                // Compute 1-based user turn counter
                $round = 1 + count(array_filter($history, fn($m) => $m['sender'] === 'user'));

                // Prepare message insert statement
                $s = $this->db->prepare(
                    'INSERT INTO game_messages(session_id,sender,content,round_number,score,validation_status) VALUES(?,?,?,?,?,?)'
                );
                // Record player's submitted word with validation outcome
                $s->execute([$id, 'user', $input, $round, null, empty($result['valid']) ? 'invalid' : 'valid']);

                // Record AI's feedback/round-end message or next chained word
                if (empty($result['valid']) || !empty($result['finished'])) {
                    $s->execute([$id, 'ai', $result['message'], $round, null, 'not_applicable']);
                } elseif (isset($result['ai'])) {
                    $s->execute([$id, 'ai', $result['ai'], $round, null, 'valid']);
                }

                // Update cumulative player and AI scores
                $score = (float) $session['player_score'] + (empty($result['valid']) ? 0 : 1);
                $aiScore = (float) $session['ai_score'] + (!empty($result['valid']) && empty($result['finished']) ? 1 : 0);
                $finished = !empty($result['finished']);
                $nextState = $result['state'];

                if ($finished) {
                    // A finished topic is a finished ROUND, not the end of the chat session.
                    $previousTopics = $state['previous_topics'] ?? [];
                    $previousTopics[] = $state['topic'];
                    $previousTopics = array_values(array_unique($previousTopics));

                    // Start a fresh Word Chain round on a new topic
                    $nextState = (new \App\Services\WordChainEngine())->start($previousTopics);
                    // Reset topic history after 10 rounds to allow cycling
                    $nextState['previous_topics'] = count($previousTopics) >= 10
                        ? [$nextState['topic']]
                        : $previousTopics;
                    // Increment round number and cumulative win/loss tallies
                    $nextState['round_number'] = (int) ($state['round_number'] ?? 1) + 1;
                    $nextState['rounds_won'] = (int) ($state['rounds_won'] ?? 0) + (($result['result'] ?? '') === 'win' ? 1 : 0);
                    $nextState['rounds_lost'] = (int) ($state['rounds_lost'] ?? 0) + (($result['result'] ?? '') === 'loss' ? 1 : 0);

                    // Format next-round introduction message
                    $intro = 'Round ' . $nextState['round_number'] . ' — New topic: ' . $nextState['topic'] . "\n"
                        . 'Starting word: ' . $nextState['last_word'] . "\n"
                        . 'Your word must begin with ' . strtoupper(substr($nextState['last_word'], -1)) . '. You have 3 attempts.';

                    // Append next-round AI prompt to transcript
                    $s->execute([$id, 'ai', $intro, $round + 1, null, 'not_applicable']);
                }

                // Persist updated Word Chain state and scores while keeping session active
                $this->db->prepare(
                    'UPDATE game_sessions SET metadata=?,player_score=?,ai_score=?,status=?,result=?,ended_at=? WHERE id=?'
                )->execute([json_encode($nextState), $score, $aiScore, 'in_progress', 'pending', null, $id]);

                // Commit Word Chain turn transaction
                $this->db->commit();
                return $result;
            }

            // Standard turn handling for Joke Battle and Guess the Punchline
            $result = $engine($session, $history, $input);
            // Roll back without saving if the engine rejected the turn
            if (empty($result['valid'])) {
                $this->db->rollBack();
                return $result;
            }

            // Compute current round number from prior user messages
            $round = 1 + count(array_filter($history, fn($m) => $m['sender'] === 'user'));
            // Insert player's message and AI's reply
            $s = $this->db->prepare(
                'INSERT INTO game_messages(session_id,sender,content,round_number,score,validation_status) VALUES(?,?,?,?,?,?)'
            );
            $s->execute([$id, 'user', $input, $round, $result['score'] ?? null, 'valid']);

            if (isset($result['ai']) && $result['ai'] !== null) {
                $s->execute([$id, 'ai', $result['ai'], $round, null, 'valid']);
            }

            // Initialize updated score and completion flags
            $score = (float) $session['player_score'];
            $aiScore = (float) $session['ai_score'];
            $finished = !empty($result['finished']);
            $winner = $result['result'] ?? 'pending';

            if ($session['game_type'] === 'word_chain') {
                // Fallback scoring branch for Word Chain
                $score += 1;
                if (isset($result['ai']) && $result['ai'] !== null) {
                    $aiScore += 1;
                }
            } elseif ($session['game_type'] === 'punchline_guess') {
                // Accumulate points awarded by Guess the Punchline engine
                $score += (float) ($result['score'] ?? 0);
            } else {
                // Joke Battle: accumulate score and check configurable round limit
                $score += (float) ($result['score'] ?? 0);
                $aiScore += 50;
                $roundLimit = 5;

                // Read admin-configured round limit from system_settings
                $limit = $this->db->query(
                    "SELECT setting_value FROM system_settings WHERE setting_key='joke_battle_rounds' LIMIT 1"
                )->fetchColumn();

                // Clamp round limit between 1 and 10
                if (ctype_digit((string) $limit)) {
                    $roundLimit = max(1, min(10, (int) $limit));
                }

                // Finalize match outcome once round limit is reached
                if ($round >= $roundLimit) {
                    $finished = true;
                    $winner = $score > $aiScore ? 'win' : ($score < $aiScore ? 'loss' : 'draw');
                }
            }

            // Persist updated scores, completion status, and end timestamp
            $update = $this->db->prepare(
                'UPDATE game_sessions SET player_score=?,ai_score=?,status=?,result=?,ended_at=? WHERE id=?'
            );
            $update->execute([
                $score,
                $aiScore,
                $finished ? 'completed' : 'in_progress',
                $finished ? $winner : 'pending',
                $finished ? date('Y-m-d H:i:s') : null,
                $id,
            ]);

            // Commit turn transaction
            $this->db->commit();

            // Merge updated totals into the returned result array
            $result['finished'] = $finished;
            $result['result'] = $winner;
            $result['player_score'] = $score;
            $result['ai_score'] = $aiScore;

            return $result;
        } catch (\Throwable $e) {
            // Roll back transaction on any error
            $this->db->rollBack();
            throw $e;
        }
    }
}
