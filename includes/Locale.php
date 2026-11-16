<?php

declare(strict_types=1);

/**
 * Locale — Minimalist translation and language helper for the JOKÉ application.
 *
 * Purpose:
 *   Provides a consistent global helper interface for language detection and string output.
 *   JOKÉ is an English-only UK coursework project, so all strings pass through unchanged.
 * Connections:
 *   Loaded globally via bootstrap.php and called across templates.
 * OOP:
 *   Procedural helper functions in the global namespace.
 * Data Structures & Algorithms:
 *   Direct O(1) pass-through return.
 */

/**
 * Return the active application language code ('en').
 */
function joke_lang(): string
{
    // Return the fixed English language code for the application.
    return 'en';
}

/**
 * Return the given English UI string unchanged.
 */
function tr(string $english): string
{
    // Pass through the English text without translation.
    return $english;
}
