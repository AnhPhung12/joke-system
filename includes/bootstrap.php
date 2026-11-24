<?php

declare(strict_types=1);

/**
 * bootstrap.php — Application Front-Bootstrap & PSR-Style Class Map Autoloader.
 *
 * Purpose: Registers an O(1) hash-map autoloader connecting `App\Core`,
 *          `App\Models`, `App\Controllers`, and `App\Services` without Composer,
 *          and defines global HTML escaping and joke-sanitization helpers.
 * Connections: Required by `includes/dispatch.php` and legacy wrapper classes;
 *              loads `includes/Locale.php` at the end of bootstrap.
 * Data Structures & Algorithms:
 *   - Associative class-map array for O(1) class-to-file resolution.
 *   - Recursive DOM tree traversal (`DOMDocument`) for server-side HTML
 *     allowlist sanitization (`sanitize_joke_html()`).
 */
// Register PSR-style O(1) class-map autoloader
spl_autoload_register(static function (string $class): void {
    // Explicit class-to-file map for zero-filesystem-scan autoloading
    $map = [
        'App\\Core\\Session' => 'SessionCore.php',
        'App\\Core\\Database' => 'DatabaseCore.php',
        'App\\Core\\Router' => 'RouterCore.php',
        'App\\Core\\Auth' => 'AuthCore.php',
        'App\\Core\\Url' => 'UrlCore.php',
        'App\\Core\\View' => 'ViewCore.php',
        'App\\Core\\Csrf' => 'CsrfCore.php',
        'App\\Models\\Admin' => 'Admin.php',
        'App\\Models\\Comment' => 'Comment.php',
        'App\\Models\\Game' => 'Game.php',
        'App\\Models\\Profile' => 'Profile.php',
        'App\\Models\\Joke' => 'Joke.php',
        'App\\Models\\User' => 'User.php',
        'App\\Models\\Moderation' => 'Moderation.php',
        'App\\Models\\PasswordReset' => 'PasswordReset.php',
        'App\\Controllers\\AdminController' => 'AdminController.php',
        'App\\Controllers\\AuthorController' => 'AuthorController.php',
        'App\\Controllers\\GameController' => 'GameController.php',
        'App\\Controllers\\CommunityController' => 'CommunityController.php',
        'App\\Controllers\\JokeController' => 'JokeController.php',
        'App\\Controllers\\AuthController' => 'AuthController.php',
        'App\\Controllers\\DashboardController' => 'DashboardController.php',
        'App\\Controllers\\ModerationController' => 'ModerationController.php',
        'App\\Controllers\\ProfileController' => 'ProfileController.php',
        'App\\Controllers\\PasswordResetController' => 'PasswordResetController.php',
        'App\\Services\\SmtpMailer' => 'SmtpMailer.php',
        'App\\Services\\GameEngine' => 'GameEngine.php',
        'App\\Services\\GeminiClient' => 'GeminiClient.php',
        'App\\Services\\WordChainEngine' => 'WordChainEngine.php',
        'App\\Services\\JokeBattleEngine' => 'JokeBattleEngine.php',
        'App\\Services\\PunchlineGuessEngine' => 'PunchlineGuessEngine.php',
    ];

    // Load the mapped PHP file if the requested class is registered
    if (isset($map[$class])) {
        require_once __DIR__ . '/' . $map[$class];
    }
});

/**
 * Escape a string for safe HTML output using UTF-8 encoding.
 */
function e(?string $value): string
{
    // Convert special characters and quotes to HTML entities safely
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Keep the small, useful subset of formatting produced by the joke editor.
 * This is deliberately server-side: browser-side editor rules alone are not a
 * security boundary.
 */
function sanitize_joke_html(string $html): string
{
    // Trim leading/trailing whitespace and return early if empty
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    // Fall back to stripping all tags if PHP DOM extension is unavailable
    if (!class_exists('DOMDocument')) {
        return trim(strip_tags($html));
    }

    // Parse fragment into UTF-8 DOMDocument while suppressing malformed-HTML warnings
    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML(
        '<?xml encoding="utf-8" ?><div>' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    // Tag allowlist for rich-text joke formatting
    $allowed = [
        'p' => true,
        'br' => true,
        'strong' => true,
        'b' => true,
        'em' => true,
        'i' => true,
        'u' => true,
        'ul' => true,
        'ol' => true,
        'li' => true,
        'a' => true,
    ];

    // Recursively serialize allowed DOM nodes and strip dangerous elements/attributes
    $render = static function (DOMNode $node) use (&$render, $allowed): string {
        // Escape text node content
        if ($node instanceof DOMText) {
            return e($node->nodeValue);
        }

        // Unwrap non-element nodes (such as document/wrapper fragments)
        if (!($node instanceof DOMElement)) {
            $out = '';
            foreach ($node->childNodes as $child) {
                $out .= $render($child);
            }
            return $out;
        }

        // Completely drop active/executable container tags and their children
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed'], true)) {
            return '';
        }

        // Recursively sanitize all child nodes
        $children = '';
        foreach ($node->childNodes as $child) {
            $children .= $render($child);
        }

        // Strip disallowed wrapper tags while keeping their sanitized inner text
        if (!isset($allowed[$tag])) {
            return $children;
        }

        // Preserve self-closing line breaks
        if ($tag === 'br') {
            return '<br>';
        }

        // Allow anchor tags only with safe http/https/mailto or relative URLs
        if ($tag === 'a') {
            $href = trim((string) $node->getAttribute('href'));
            $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
            $safeHref = $href !== '' && ($scheme === '' || in_array($scheme, ['http', 'https', 'mailto'], true));

            return $safeHref
                ? '<a href="' . e($href) . '" rel="noopener noreferrer">' . $children . '</a>'
                : $children;
        }

        // Reconstruct allowed tag without arbitrary HTML attributes
        return '<' . $tag . '>' . $children . '</' . $tag . '>';
    };

    // Return trimmed sanitized HTML string
    return trim($render($document->documentElement));
}

/**
 * Convert sanitized joke HTML into plain decoded text for previews and length checks.
 */
function joke_plain_text(string $html): string
{
    // Sanitize HTML, strip remaining tags, and decode HTML entities to UTF-8 plain text
    return trim(html_entity_decode(strip_tags(sanitize_joke_html($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Load localization/translation helpers
require_once __DIR__ . '/Locale.php';
