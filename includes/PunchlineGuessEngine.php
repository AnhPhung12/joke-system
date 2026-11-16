<?php

declare(strict_types=1);

namespace App\Services;

/**
 * PunchlineGuessEngine — AI-powered 5-round "Guess the Punchline" quiz engine.
 *
 * Purpose:
 *   Generates original joke setups, evaluates player punchline guesses, reveals actual punchlines
 *   (including when the player gives up), and advances through a 5-round quiz.
 * Connections:
 *   - Implements App\Services\GameEngine.
 *   - Calls App\Services\GeminiClient::json() for structured AI responses.
 *   - Invoked by GameController during playground sessions.
 * OOP:
 *   - Final service class implementing the GameEngine interface.
 * Data Structures & Algorithms:
 *   - Round Tracking: Counts prior 'user' messages in $history to determine completion at round 5.
 *   - History Parsing: Scans AI messages in reverse to extract the most recent 'Next question:' or 'First question:' prompt.
 */
final class PunchlineGuessEngine implements GameEngine
{
    /**
     * Return a deterministic fallback joke setup question.
     */
    public static function question(int $round): string
    {
        // Return a deterministic fallback joke setup when history has no prior prompt.
        return 'Why did the computer bring a ladder to work?';
    }

    /**
     * Ask Gemini to generate a fresh opening joke setup question.
     */
    public static function openingQuestion(): string
    {
        if (!GeminiClient::configured() && GeminiClient::isLocalFallback()) {
            return 'Why did the computer go to the doctor?';
        }

        // Request a new original joke setup question from Gemini as JSON.
        $r = (new GeminiClient())->json(
            'You host a family-friendly English Guess the Punchline game. Generate ONE fresh original joke setup, written as a question. Return JSON with question only. Do not reveal the answer.',
            'Create a new joke question.'
        );

        // Trim and validate the generated question length.
        $q = trim((string) ($r['question'] ?? ''));
        if ($q === '' || mb_strlen($q) > 250) {
            throw new \RuntimeException('Gemini could not create a punchline question. Please retry.');
        }

        // Return the validated opening setup question.
        return $q;
    }

    /**
     * Evaluate the player's punchline guess, reveal the answer, and generate the next round's question.
     */
    public function reply(string $message, array $history): array
    {
        // Count prior user turns to determine the current quiz round.
        $round = count(array_filter($history, static fn($m) => ($m['sender'] ?? '') === 'user'));
        // Extract the most recent setup question from the conversation history.
        $question = self::lastQuestion($history);

        // Detect explicit give-up/skip phrases so the player receives 0 points for the round.
        $giveUp = (bool) preg_match(
            '/^(?:i\s*(?:do not|don.t|dont)\s*know|idk|no idea|i give up|give up|tell me(?: the answer)?|reveal(?: the answer)?|skip)[.!?\s]*$/iu',
            trim($message)
        );

        if (!GeminiClient::configured() && GeminiClient::isLocalFallback()) {
            $bank = [
                'Why did the computer go to the doctor?' => 'Because it had a virus!',
                'Why did the math book look so sad?' => 'Because it had too many problems!',
                'Why can’t you give Elsa a balloon?' => 'Because she will let it go!',
                'What do you call a sleeping dinosaur?' => 'A dino-snore!',
                'Why did the bicycle fall over?' => 'Because it was two-tired!',
                'Why did the scarecrow win an award?' => 'Because he was outstanding in his field!',
            ];
            $actual = $bank[$question] ?? 'Because it wanted to make you smile!';
            $finished = $round + 1 >= 5;
            $score = $giveUp ? 0 : (mb_strlen(trim($message)) >= 4 ? 1 : 0);
            $keys = array_keys($bank);
            $next = $keys[($round + 1) % count($keys)];
            $feedback = ($giveUp ? 'Here is the punchline: ' : 'Fun guess! The punchline is: ') . $actual;
            $ai = $feedback . ($finished ? ' Quiz complete!' : ' Next question: ' . $next);
            return [
                'valid'    => true,
                'score'    => $score,
                'message'  => $feedback,
                'ai'       => $ai,
                'finished' => $finished,
                'result'   => $finished ? 'win' : 'pending',
            ];
        }

        // Build the referee system prompt instructing Gemini to score, reveal, and advance.
        $rules = 'You are Baby Joke running an English Guess the Punchline quiz. The CURRENT joke setup is: '
            . json_encode($question)
            . '. The player input is a punchline guess. If the player asks for a translation or asks in Vietnamese (e.g. "nghĩa tiếng việt là gì"), briefly translate and explain the joke in Vietnamese inside feedback, then still reveal the actual English punchline and give next_question. If the player says they do not know, gives up, asks for the answer or skips, DO NOT score them as correct: reveal the ACTUAL funny punchline clearly and explain it briefly. Otherwise judge creative guesses generously, and reveal the actual punchline regardless of whether they were right. Generate ONE new original funny question for the next round. Return JSON with score (0 or 1), feedback (MUST include the actual punchline), next_question (new setup question). Do not reuse old questions. Do not reveal the next punchline. No markdown.';

        // Call Gemini with the rules, user input (or give-up directive), and conversation history.
        $r = (new GeminiClient())->json(
            $rules,
            $giveUp ? 'The player gives up. Reveal the punchline for this question, then ask the next question.' : $message,
            $history
        );

        // Clamp the round score to 0 or 1, forcing 0 when the player gave up.
        $score = $giveUp ? 0 : max(0, min(1, (int) ($r['score'] ?? 0)));
        // Mark the quiz complete after the 5th round.
        $finished = $round + 1 >= 5;

        // Extract and validate the punchline reveal feedback from Gemini.
        $feedback = trim(mb_substr((string) ($r['feedback'] ?? ''), 0, 700));
        if ($feedback === '') {
            throw new \RuntimeException('Gemini did not reveal the punchline. Please try again.');
        }

        // Extract and validate the next round's question when the quiz continues.
        $next = trim((string) ($r['next_question'] ?? ''));
        if (!$finished && ($next === '' || mb_strlen($next) > 250)) {
            throw new \RuntimeException('Gemini did not provide the next question. Please try again.');
        }

        // Format the combined AI response message for the game transcript.
        $ai = $feedback . ($finished ? ' Quiz complete!' : ' Next question: ' . $next);

        // Return the structured turn result to GameController.
        return [
            'valid' => true,
            'score' => $score,
            'message' => $feedback,
            'ai' => $ai,
            'finished' => $finished,
            'result' => $finished ? 'win' : 'pending',
        ];
    }

    /**
     * Extract the most recent joke setup question from AI messages in the conversation history.
     */
    private static function lastQuestion(array $history): string
    {
        // Scan conversation history backwards to find the latest AI prompt.
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['sender'] ?? '') !== 'ai') {
                continue;
            }

            // Look for the 'Next question: ' marker in subsequent round messages.
            $text = (string) $history[$i]['content'];
            $pos = mb_strripos($text, 'Next question: ');
            if ($pos !== false) {
                return trim(mb_substr($text, $pos + mb_strlen('Next question: ')));
            }

            // Fall back to the initial 'First question: ' prefix from round 1.
            if (str_starts_with($text, 'First question: ')) {
                return trim(substr($text, strlen('First question: ')));
            }
        }

        // Return the deterministic fallback question if no prompt was found in history.
        return self::question(0);
    }
}
