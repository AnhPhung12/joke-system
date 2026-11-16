<?php

/**
 * Standalone Gemini API connectivity test script.
 *
 * Bypasses includes/dispatch.php and sends a direct generateContent request
 * to the Google Gemini API using JOKE_AI_API_KEY and JOKE_AI_MODEL.
 */

// Output plain text so diagnostics are easy to read in a browser or CLI.
header('Content-Type: text/plain; charset=utf-8');

// Read the Gemini API key from environment variables.
$key = getenv('JOKE_AI_API_KEY');
// Read the Gemini model name or fall back to gemini-2.5-flash.
$model = getenv('JOKE_AI_MODEL') ?: 'gemini-2.5-flash';

// Check whether the API key is configured.
if (!$key) {
    // Abort execution with an error message when the key is missing.
    exit('ERROR: API key is missing.');
}

// Build the Gemini v1beta generateContent endpoint URL for the chosen model.
$url = 'https://generativelanguage.googleapis.com/v1beta/models/'
    . rawurlencode($model) // URL-encode the model identifier safely.
    . ':generateContent'; // Append the generateContent action suffix.

// Construct the minimal chat payload asking Gemini for a short English joke.
$data = [
    'contents' => [ // Wrap conversation turns in the contents array.
        [
            'role' => 'user', // Identify the prompt sender as the user.
            'parts' => [ // Provide the message content parts.
                [
                    'text' => 'Tell me a funny joke in English.' // Prompt text sent to Gemini.
                ]
            ]
        ]
    ]
];

// Initialize a cURL handle targeting the Gemini endpoint.
$ch = curl_init($url);

// Configure cURL options for a JSON POST request with the API key header and timeout.
curl_setopt_array($ch, [
    CURLOPT_POST => true, // Use HTTP POST for the request.
    CURLOPT_RETURNTRANSFER => true, // Return the response body as a string.
    CURLOPT_HTTPHEADER => [ // Set required request headers.
        'Content-Type: application/json', // Declare JSON request payload.
        'x-goog-api-key: ' . $key // Authenticate using the Gemini API key header.
    ],
    CURLOPT_POSTFIELDS => json_encode($data), // Encode the request payload as JSON.
    CURLOPT_TIMEOUT => 40 // Enforce a 40-second request timeout.
]);

// Execute the HTTP request and capture the raw response body.
$response = curl_exec($ch);
// Capture any cURL transport error message.
$error = curl_error($ch);
// Retrieve the HTTP status code from the completed transfer.
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Release the cURL handle.
curl_close($ch);

// Print the selected Gemini model name.
echo "Model: " . $model . "\n";
// Print the HTTP status code returned by the API.
echo "HTTP Status: " . $status . "\n\n";

// Check whether cURL failed at the transport layer.
if ($response === false) {
    // Terminate and display the cURL connection error.
    exit("Connection error: " . $error);
}

// Decode the JSON response body into an associative array.
$result = json_decode($response, true);

// Check whether the API returned HTTP 200 OK.
if ($status === 200) {
    // Print the generated joke text or a fallback notice if empty.
    echo $result['candidates'][0]['content']['parts'][0]['text']
        ?? 'No text returned.';
} else {
    // Print a header for API-level errors.
    echo "API Error:\n";
    // Print the API error message or a generic fallback.
    echo $result['error']['message']
        ?? 'Unknown error';

    // Print a separator before dumping the raw response body.
    echo "\n\nRaw response:\n";
    // Print the raw JSON response for debugging.
    echo $response;
}

