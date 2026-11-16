<?php

declare(strict_types=1);

namespace App\Services;

/**
 * GameEngine — Strategy Interface for AI Playground Turn Evaluators.
 *
 * Purpose: Defines the common `reply()` contract implemented by AI game engines
 *          (`JokeBattleEngine`, `PunchlineGuessEngine`, `WordChainEngine`).
 * Connections: Consumed by `App\Controllers\GameController` and `App\Models\Game`.
 * OOP: Interface enabling polymorphic engine dispatch in `GameController::turn()`.
 */
interface GameEngine
{
    /**
     * Evaluate a player's turn against conversation history and return a structured result.
     *
     * @param string                           $message Player's input text.
     * @param array<int, array<string, mixed>> $history Prior messages in the session.
     * @return array<string, mixed>
     */
    // Process a single user turn and return validation, score, and AI response data
    public function reply(string $message, array $history): array;
}
