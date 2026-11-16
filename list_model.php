<?php

/**
 * Diagnostic utility: List available Gemini OpenAI-compatible AI models.
 *
 * Standalone script (does not use includes/dispatch.php) that queries the
 * Google Generative Language OpenAI-compatible models endpoint using the
 * JOKE_AI_API_KEY environment variable and prints each model ID.
 */

// Send plain-text UTF-8 output rather than HTML.
header('Content-Type: text/plain; charset=utf-8'); // Set response content type to plain text with UTF-8 encoding.

// Read the AI API key from the environment.
$key = getenv('JOKE_AI_API_KEY'); // Retrieve the JOKE_AI_API_KEY environment variable.

// Abort early if the API key is not configured.
if (!$key) { // Check whether the API key is missing or empty.
    exit("API key is not configured."); // Print an error message and terminate execution.
}

// Target the Gemini OpenAI-compatible models listing endpoint.
$url = 'https://generativelanguage.googleapis.com/v1beta/openai/models'; // Define the Gemini models API endpoint URL.

// Initialize a cURL handle for the GET request.
$ch = curl_init($url); // Create a new cURL session targeting the models endpoint.

// Configure cURL to return the response body, pass the Bearer token, and enforce a 30s timeout.
curl_setopt_array($ch, [ // Apply cURL options for the HTTP request.
    CURLOPT_RETURNTRANSFER => true, // Return the transfer as a string instead of outputting directly.
    CURLOPT_HTTPHEADER => [ // Set custom HTTP request headers.
        'Authorization: Bearer ' . $key, // Attach the API key as a Bearer authorization token.
    ],
    CURLOPT_TIMEOUT => 30, // Enforce a 30-second maximum request timeout.
]);

// Execute the HTTP request and capture the status code and any transport error.
$response = curl_exec($ch); // Perform the cURL HTTP request and store the response body.
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Retrieve the HTTP status code from the completed transfer.
$error = curl_error($ch); // Capture any cURL transport-level error message.

// Release the cURL handle.
curl_close($ch); // Close the cURL session to free system resources.

// Print the HTTP status code returned by the API.
echo "HTTP Status: $status\n\n"; // Output the HTTP status code header line.

// Exit with the cURL error message if the request failed at the transport layer.
if ($response === false) { // Check if curl_exec failed to receive a response.
    exit("Connection error: $error"); // Print the transport error message and terminate.
}

// Decode the JSON response payload into an associative array.
$data = json_decode($response, true); // Parse the JSON response string into an associative PHP array.

// If the API returned a non-200 status, print the raw response body and stop.
if ($status !== 200) { // Check if the HTTP status indicates an error response.
    echo "API response:\n"; // Print a label before the raw error payload.
    echo $response; // Output the raw API response body for debugging.
    exit; // Terminate script execution.
}

// Iterate over returned model entries and print each model ID.
foreach ($data['data'] ?? [] as $model) { // Loop through each model object in the 'data' array.
    echo ($model['id'] ?? 'Unknown') . "\n"; // Print the model ID or 'Unknown' if missing.
}
