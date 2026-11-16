<?php
declare(strict_types=1); // Enforce strict scalar type checking for this script.

/**
 * Entry point: User avatar image endpoint.
 *
 * Streams a user's stored avatar binary directly from the database via
 * \App\Models\Profile::avatar(), bypassing the main router dispatch.
 */

// Load application bootstrap (autoloader, configuration, and database helpers).
require __DIR__ . '/includes/bootstrap.php'; // Include bootstrap file to initialize app dependencies.

// Read and validate the requested user ID from the query string.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT); // Parse the 'id' GET parameter as a validated integer.

// Return 404 Not Found if the ID is missing or not a positive integer.
if (!$id || $id < 1) { // Check that the user ID is present and at least 1.
    http_response_code(404); // Send HTTP 404 Not Found status code.
    exit; // Terminate script execution immediately.
}

try { // Begin guarded block to catch database or stream errors.
    // Fetch the avatar MIME type and binary payload for the given user ID.
    $avatar = (new \App\Models\Profile())->avatar($id); // Query the Profile model for the user's avatar record.

    // Return 404 Not Found if no avatar record exists for this user.
    if (!$avatar) { // Verify that an avatar record was returned.
        http_response_code(404); // Send HTTP 404 Not Found status code.
        exit; // Terminate script execution immediately.
    }

    // Extract the stored MIME type and allow only safe raster image formats.
    $mime = $avatar['avatar_mime']; // Retrieve the stored MIME type string.
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) { // Ensure the MIME type is an allowed image format.
        http_response_code(415); // Send HTTP 415 Unsupported Media Type status code.
        exit; // Terminate script execution immediately.
    }

    // Retrieve the raw image bytes; convert stream resources to a string if needed.
    $data = $avatar['avatar_data']; // Read the stored binary image payload.
    if (is_resource($data)) { // Check if the database driver returned a stream resource.
        $data = stream_get_contents($data); // Read the entire stream into a binary string.
    }

    // Send content, security, and private cache-control headers before the body.
    header('Content-Type: ' . $mime); // Set the Content-Type header to the validated image MIME type.
    header('Content-Length: ' . strlen($data)); // Set the Content-Length header to the byte size of the image.
    header('X-Content-Type-Options: nosniff'); // Prevent browsers from MIME-sniffing the response.
    header('Cache-Control: private, no-store'); // Disable shared and persistent caching of private avatar data.

    // Output the binary image data to the client.
    echo $data; // Write the raw image bytes to the HTTP response body.
} catch (\Throwable $e) { // Catch any unexpected exceptions or runtime errors.
    // Log unexpected database or stream errors and return 500 Internal Server Error.
    error_log('JOKE avatar retrieval failed: ' . $e->getMessage()); // Record the error details to the PHP error log.
    http_response_code(500); // Send HTTP 500 Internal Server Error status code.
}
