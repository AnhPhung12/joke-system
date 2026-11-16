<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router — Lightweight HTTP request router supporting static and numeric-parameterized routes.
 *
 * Purpose:
 *   Registers GET and POST route handlers, normalizes request paths against the base URL,
 *   dispatches matching handlers, and renders a 404 page when no route matches.
 * Connections:
 *   - Uses App\Core\Url::base() to strip subdirectory prefixes and View::render() for 404 responses.
 *   - Loaded as RouterCore.php via bootstrap.php.
 * OOP:
 *   - Final core class storing a method-indexed route table.
 * Data Structures & Algorithms:
 *   - Route Table: Associative array $routes[$method][$pattern] = callable.
 *   - Dispatching: O(1) exact lookup first, followed by linear regex matching for `{param}` numeric segments.
 */
final class Router
{
    // Method-indexed route table mapping HTTP method and path pattern to callable handlers.
    private array $routes = [];

    /**
     * Register a GET route handler.
     */
    public function get(string $path, callable $handler): void
    {
        // Store the handler under the GET method and path key.
        $this->routes['GET'][$path] = $handler;
    }

    /**
     * Register a POST route handler.
     */
    public function post(string $path, callable $handler): void
    {
        // Store the handler under the POST method and path key.
        $this->routes['POST'][$path] = $handler;
    }

    /**
     * Resolve the current HTTP method and normalized path, then dispatch to a handler.
     */
    public function dispatch(): void
    {
        // Read the incoming HTTP request method, defaulting to GET.
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        // Extract the URL path component without query parameters.
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Resolve the application base subdirectory prefix.
        $base = Url::base();

        // Strip the base directory prefix from the request path when present.
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        }

        // Normalize the path to have a single leading slash and no trailing slash.
        $path = '/' . trim($path, '/');
        // Collapse double-slash root path into a single slash.
        if ($path === '//') {
            $path = '/';
        }

        // Dispatch the normalized path and HTTP method to matching routes.
        $this->dispatchPath($path, $method);
    }

    /**
     * Match a normalized path and HTTP method against registered static and parameterized routes.
     */
    public function dispatchPath(string $path, string $method): void
    {
        // Execute an exact static route match immediately if registered.
        if (isset($this->routes[$method][$path])) {
            ($this->routes[$method][$path])();
            return;
        }

        // Iterate over registered routes for the method to match parameterized patterns.
        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            // Skip static routes without parameter placeholders.
            if (!str_contains($pattern, '{')) {
                continue;
            }

            // Escape regex metacharacters and replace unescaped placeholders with numeric capture groups.
            $regex = preg_replace('/\\{([a-zA-Z_][a-zA-Z0-9_]*)\\}/', '([0-9]+)', preg_quote($pattern, '~'));
            // preg_quote escapes braces: remove their escaping before substitution.
            $regex = preg_replace('/\\\\\\{[a-zA-Z_][a-zA-Z0-9_]*\\\\\\}/', '([0-9]+)', preg_quote($pattern, '~'));

            // Test the compiled regex against the request path.
            if (preg_match('~^' . $regex . '$~D', $path, $matches)) {
                // Drop the full-match element so only captured parameters remain.
                array_shift($matches);
                // Invoke the route handler with integer-cast captured parameters.
                $handler(...array_map('intval', $matches));
                return;
            }
        }

        // Send a 404 Not Found status when no route matches.
        http_response_code(404);
        // Render the 404 error template.
        View::render('errors/404', ['title' => '404 · Not found']);
    }
}
