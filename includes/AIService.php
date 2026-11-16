<?php

declare(strict_types=1);

// Load the central autoloader and helper definitions
require_once __DIR__ . '/bootstrap.php';

/**
 * AIService — Legacy Compatibility Wrapper for AI Configuration Checks.
 *
 * Purpose: Exposes a simple availability check for server-side AI credentials
 *          without leaking API keys to client-side JavaScript.
 * Connections: Bootstraps autoloading via `bootstrap.php`. Actual AI game logic
 *              lives in `App\Services\JokeBattleEngine`, `WordChainEngine`,
 *              and `PunchlineGuessEngine`.
 * OOP: Final utility class.
 */
// AI game logic is implemented by JokeBattleEngine and WordChainEngine.
// Provider configuration stays server-side; no API keys in browser JavaScript.
final class AIService
{
    /**
     * Check whether both the AI endpoint URL and API key environment variables are set.
     */
    public function available(): bool
    {
        // Confirm both the endpoint URL and API key exist in environment variables
        return (bool) getenv('JOKE_AI_API_URL') && (bool) getenv('JOKE_AI_API_KEY');
    }
}
