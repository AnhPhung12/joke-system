<?php

declare(strict_types=1);

namespace App\Services;

/**
 * WordChainEngine — AI-refereed topic Word Chain game engine.
 *
 * Purpose:
 *   Starts a themed one-word chain session from a curated topic list and validates/responds
 *   to player turns using strict letter-chaining rules, duplicate tracking, and a 3-mistake limit.
 * Connections:
 *   - Implements App\Services\GameEngine.
 *   - Calls App\Services\GeminiClient::json() for topic/word selection and semantic validation.
 *   - Invoked by GameController during playground sessions.
 * OOP:
 *   - Final service class implementing GameEngine.
 * Data Structures & Algorithms:
 *   - State Machine: Tracks `topic`, `last_word`, `used` (list of seen words), and `mistakes` (0..3).
 *   - Validation: Local regex/chaining/duplicate checks run before invoking Gemini for topic relevance.
 */
final class WordChainEngine implements GameEngine
{
    // Curated list of allowed Word Chain topics.
    private const TOPICS = [
        'School',
        'Animals',
        'Food',
        'Technology',
        'Sports',
        'Nature',
        'Music',
        'Travel',
        'Space',
        'Health',
    ];

    /**
     * Normalize a word to lowercase trimmed form.
     */
    private function norm(string $s): string
    {
        // Trim surrounding whitespace and convert to lowercase.
        return strtolower(trim($s));
    }

    /**
     * Initialize a new Word Chain session by asking Gemini for a topic and starting noun.
     */
    public function start(array $excludeTopics = []): array
    {
        // Filter out recently played topics when possible, falling back to all topics.
        $available = array_values(array_diff(self::TOPICS, $excludeTopics));
        if (!$available) {
            $available = self::TOPICS;
        }

        if (!GeminiClient::configured() && GeminiClient::isLocalFallback()) {
            $starters = [
                'School' => 'teacher', 'Animals' => 'tiger', 'Food' => 'apple',
                'Technology' => 'laptop', 'Sports' => 'soccer', 'Nature' => 'river',
                'Music' => 'guitar', 'Travel' => 'airport', 'Space' => 'planet', 'Health' => 'doctor',
            ];
            $topic = $available[array_rand($available)];
            $word = $starters[$topic] ?? 'apple';
            return [
                'topic'     => $topic,
                'last_word' => $word,
                'used'      => [$word],
                'mistakes'  => 0,
            ];
        }

        // Ask Gemini to pick one topic from the available list and a valid starting noun.
        $r = (new GeminiClient())->json(
            'You are Baby Joke hosting a STRICT one-word topic word chain. Choose one topic from this exact list: '
            . implode(', ', $available)
            . '. Choose a common English noun associated directly with the topic as the first word, ending in a letter with several possible topic-related follow-ups. Return JSON with topic and word only.',
            'Choose a topic and starting word.'
        );

        // Extract and normalize the returned topic and starting word.
        $topic = (string) ($r['topic'] ?? '');
        $word = $this->norm((string) ($r['word'] ?? ''));

        // Validate that the AI selected an allowed topic and a 2-25 letter word.
        if (!in_array($topic, $available, true) || !preg_match('/^[a-z]{2,25}$/', $word)) {
            throw new \RuntimeException('Could not start Word Chain. Please try again.');
        }

        // Return the initial session state metadata.
        return [
            'topic' => $topic,
            'last_word' => $word,
            'used' => [$word],
            'mistakes' => 0,
        ];
    }

    /**
     * Unsupported generic GameEngine entry point; Word Chain requires explicit session state.
     */
    public function reply(string $message, array $history): array
    {
        // Enforce use of play() which carries the Word Chain state metadata.
        throw new \LogicException('Word Chain requires session metadata.');
    }

    /**
     * Validate a player's word against format, uniqueness, starting-letter, and AI topic rules,
     * then return either the AI's counter-word or an updated mistake count.
     */
    public function play(string $message, array $state): array
    {
        // Normalize the player's submitted word and read current game state fields.
        $word = $this->norm($message);
        $topic = (string) $state['topic'];
        $last = (string) $state['last_word'];
        $used = $state['used'] ?? [];
        $error = null;

        // If the player gives up ("i dont know", "idk", "give up"), end the round as a loss immediately.
        if (preg_match('/^(?:i\s*(?:do\s*not|don.?t|dont)\s*know|idk|no\s*idea|i\s*give\s*up|give\s*up|skip)[.!?\s]*$/iu', trim($message))) {
            $state['mistakes'] = 3;
            return [
                'valid'    => false,
                'message'  => 'You gave up! You lose this round — start a new topic to play again.',
                'finished' => true,
                'result'   => 'loss',
                'state'    => $state,
            ];
        }

        // Run local structural, uniqueness, and letter-chaining checks before calling Gemini.
        if (!preg_match('/^[a-z]{2,30}$/', $word)) {
            $error = 'Enter exactly one English word (letters only).';
        } elseif (in_array($word, $used, true)) {
            $error = 'That word has already been used.';
        } elseif ($word[0] !== substr($last, -1)) {
            $error = 'Your word must start with ' . strtoupper(substr($last, -1)) . '.';
        }

        // Proceed to AI semantic validation and counter-move only if local checks passed.
        if ($error === null) {
            if (!GeminiClient::configured() && GeminiClient::isLocalFallback()) {
                $pool = ['apple','elephant','tiger','rabbit','train','notebook','kite','eagle','earth','house','energy','yellow','whale','echo','orange','egg','grape','eagle','island','dog','garden','night','tree','river','rock','king','goat','time','moon','nest','star','road','desk','key','yarn','note','eye','ear','ring','gold','door'];
                $need = substr($word, -1);
                $usedAll = array_merge($used, [$word]);
                $ai = null;
                foreach ($pool as $cand) {
                    if ($cand[0] === $need && !in_array($cand, $usedAll, true)) {
                        $ai = $cand;
                        break;
                    }
                }
                $state['used'][] = $word;
                if ($ai !== null) {
                    $state['used'][] = $ai;
                }
                $state['last_word'] = $ai ?? $word;
                return [
                    'valid'    => true,
                    'message'  => 'Accepted! (Local Offline Mode)',
                    'ai'       => $ai === null ? 'I cannot find another word. You win!' : $ai,
                    'finished' => $ai === null,
                    'result'   => $ai === null ? 'win' : 'pending',
                    'state'    => $state,
                ];
            }

            // Build the referee prompt with topic, previous word, and already-used words.
            $instructions = 'You are the fair referee and opponent in a STRICT English one-word chain game. Topic: '
                . $topic
                . '. Previous word: '
                . $last
                . '. Previously used words: '
                . implode(', ', $used)
                . '. First decide if the submitted word is a REAL standard English word and DIRECTLY relevant to the topic (a typical item, person, place, activity or concept in the topic). Reject invented words, spelling mistakes and tenuous metaphorical links. Be fair to words with multiple legitimate meanings. If invalid, return {"accepted":false,"reason":"short explanation"}. If valid, find a REAL, directly topic-related, UNUSED single English word starting with the LAST LETTER of the player word, and return {"accepted":true,"reason":"short praise","ai_word":"word"}. If you cannot find one, return {"accepted":true,"reason":"You win! I cannot find another word.","ai_word":null}. Do not invent words. Output JSON only.';

            // Send the referee prompt and player word to Gemini.
            $r = (new GeminiClient())->json($instructions, $word);

            // Capture the rejection reason if the AI rejected the player's word.
            if (empty($r['accepted'])) {
                $error = mb_substr((string) ($r['reason'] ?? 'That word does not fit the topic.'), 0, 240);
            } else {
                // Normalize the AI's counter-word if one was provided.
                $ai = isset($r['ai_word']) && is_string($r['ai_word']) ? $this->norm($r['ai_word']) : null;

                // Verify the AI's counter-word obeys length, chaining, and uniqueness rules.
                if (
                    $ai !== null
                    && (
                        !preg_match('/^[a-z]{2,30}$/', $ai)
                        || $ai[0] !== substr($word, -1)
                        || in_array($ai, array_merge($used, [$word]), true)
                    )
                ) {
                    throw new \RuntimeException('Baby Joke generated an invalid word. Please send your word again.');
                }

                // Append the player's word and optional AI counter-word to the used list.
                $state['used'][] = $word;
                if ($ai !== null) {
                    $state['used'][] = $ai;
                }
                // Advance the chain's last word pointer.
                $state['last_word'] = $ai ?? $word;

                // Return the accepted turn result, declaring a win if the AI could not reply.
                return [
                    'valid' => true,
                    'message' => 'Accepted!',
                    'ai' => $ai === null ? 'I cannot find another word. You win!' : $ai,
                    'finished' => $ai === null,
                    'result' => $ai === null ? 'win' : 'pending',
                    'state' => $state,
                ];
            }
        }

        // Increment the mistake counter (capped at 3) and compute remaining attempts.
        $state['mistakes'] = min(3, (int) ($state['mistakes'] ?? 0) + 1);
        $remaining = 3 - $state['mistakes'];
        // Format the error feedback with remaining attempts or game-over notice.
        $feedback = $error . ' ' . (
            $remaining > 0
                ? 'Attempts left: ' . $remaining . '. Your word must start with ' . strtoupper(substr($last, -1)) . '.'
                : 'You have used all 3 attempts. Game over!'
        );

        // Return the invalid-turn result and updated mistake state.
        return [
            'valid' => false,
            'message' => $feedback,
            'finished' => $remaining === 0,
            'result' => $remaining === 0 ? 'loss' : 'pending',
            'state' => $state,
        ];
    }
}
