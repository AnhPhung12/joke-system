<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\{Auth, Csrf, Session, Url, View};
use App\Models\Game;
use App\Services\{JokeBattleEngine, WordChainEngine};

/**
 * GameController — MVC Controller for the AI Playground.
 *
 * Purpose: Manages the AI Playground hub, starting new game sessions, rendering
 *          active game chat rooms, processing player turns, and deleting history.
 * Connections: Coordinates `App\Models\Game` with `App\Services\{JokeBattleEngine,
 *              WordChainEngine, PunchlineGuessEngine}` and `games/*` templates.
 * Security: Requires login, enforces CSRF tokens on all POST actions, and scopes
 *           session queries/deletions to the authenticated user's ID.
 */
final class GameController
{
    /**
     * Render the AI Playground lobby with recent game history and a surprise prompt.
     */
    public function index(): void
    {
        // Require authenticated user
        Auth::requireLogin();

        // Define surprise mode cards displayed in the playground lobby
        $prompts = [
            'word_chain' => [
                'label' => 'Word Chain',
                'prompt' => 'Surprise topic! Can you keep a word chain going without making three mistakes?',
                'emoji' => '🔗',
            ],
            'punchline_guess' => [
                'label' => 'Guess the Punchline',
                'prompt' => 'Why did the computer go to the doctor? Can you guess the punchline?',
                'emoji' => '🎯',
            ],
            'joke_battle' => [
                'label' => 'Tell a Joke',
                'prompt' => 'Can you make Baby Joke laugh? Tell your funniest joke!',
                'emoji' => '🎤',
            ],
        ];

        // Pick a random game mode for the "Surprise Me" prompt
        $type = array_rand($prompts);

        // Render the AI Playground lobby with user's game history and AI availability
        View::render('games/index', [
            'title' => 'AI Playground',
            'games' => (new Game())->history((int) Auth::user()['id']),
            'aiReady' => JokeBattleEngine::configured(),
            'surpriseType' => $type,
            'surprisePrompt' => $prompts[$type],
        ]);
    }

    /**
     * Create a new AI game session for the selected mode and redirect to its room.
     */
    public function start(): void
    {
        // Require authenticated user and valid CSRF token
        Auth::requireLogin();
        Csrf::enforce();

        // Validate requested game mode against supported enum
        $type = (string) ($_POST['type'] ?? '');
        if (!in_array($type, ['joke_battle', 'word_chain', 'punchline_guess'], true)) {
            http_response_code(422);
            exit('Invalid game');
        }

        // Prevent starting a session when Gemini API credentials are missing
        if (!JokeBattleEngine::configured()) {
            Session::flash('Gemini API is not configured for AI Playground.');
            Url::redirect('/playground');
        }

        try {
            // Create session and seed initial AI prompt
            $id = (new Game())->create((int) Auth::user()['id'], $type);
        } catch (\RuntimeException $e) {
            Session::flash($e->getMessage());
            Url::redirect('/playground');
        } catch (\PDOException $e) {
            error_log('JOKE game start database error: ' . $e->getMessage());
            Session::flash('Unable to start the game. Please ensure database/playground_modes_migration.sql has been imported.');
            Url::redirect('/playground');
        }

        // Redirect into the newly created game room
        Url::redirect('/playground/' . $id);
    }

    /**
     * Display an individual game session and its message transcript.
     */
    public function show(int $id): void
    {
        // Require authenticated user
        Auth::requireLogin();

        // Load session scoped to current user
        $g = new Game();
        $session = $g->session($id, (int) Auth::user()['id']);

        // Render 404 page if session does not exist or belongs to another user
        if (!$session) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Game not found']);
            return;
        }

        // Render the game room view with full message history
        View::render('games/show', [
            'title' => 'JOKÉ · Game',
            'game' => $session,
            'messages' => $g->messages($id),
        ]);
    }

    /**
     * Delete a saved game session from the user's profile history.
     */
    public function delete(int $id): void
    {
        // Require authenticated user and valid CSRF token
        Auth::requireLogin();
        Csrf::enforce();

        // Delete session owned by the current user and flash result
        $deleted = (new Game())->delete($id, (int) Auth::user()['id']);
        Session::flash($deleted ? 'Game history deleted.' : 'Game session not found.');
        // Return to user profile history
        Url::redirect('/profile');
    }

    /**
     * Submit a player turn, invoke the matching AI engine, and persist the response.
     */
    public function turn(int $id): void
    {
        // Require authenticated user and valid CSRF token
        Auth::requireLogin();
        Csrf::enforce();

        // Validate player message length (1 to 1,000 characters)
        $input = trim((string) ($_POST['message'] ?? ''));
        if ($input === '' || mb_strlen($input) > 1000) {
            Session::flash('Enter between 1 and 1,000 characters.');
            Url::redirect('/playground/' . $id);
        }

        // Verify session exists and belongs to the current user
        $g = new Game();
        $session = $g->session($id, (int) Auth::user()['id']);

        if (!$session) {
            http_response_code(404);
            exit('Game not found');
        }

        try {
            // Select the AI engine corresponding to the session's game mode
            $engine = match ($session['game_type']) {
                'word_chain' => new WordChainEngine(),
                'punchline_guess' => new \App\Services\PunchlineGuessEngine(),
                default => new JokeBattleEngine(),
            };

            // Execute turn transactionally and persist AI evaluation
            $result = $g->turn($id, (int) Auth::user()['id'], $input, fn($s, $h, $m) => $engine->reply($m, $h));

            // Flash feedback for rejected inputs in non-Word-Chain modes
            if (empty($result['valid']) && $session['game_type'] !== 'word_chain') {
                Session::flash((string) ($result['message'] ?? 'Please try again.'));
            }
        } catch (\RuntimeException $e) {
            Session::flash($e->getMessage());
        } catch (\PDOException $e) {
            error_log('JOKE game turn database error: ' . $e->getMessage());
            Session::flash('Unable to save this turn. Please check the game database tables.');
        }

        // Redirect back to the game room to display the new turn
        Url::redirect('/playground/' . $id);
    }
}
