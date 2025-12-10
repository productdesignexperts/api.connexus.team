<?php
/**
 * Connexus API - Main Router
 *
 * Routes requests to appropriate version handlers.
 * Base URL: https://api.connexus.team
 */

require_once __DIR__ . '/../src/helpers.php';

// Set CORS headers for all requests
set_cors_headers();

// Parse the request path
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = trim($path, '/');

// Root endpoint - API info
if (empty($path)) {
    json_response([
        'name' => 'Connexus API',
        'version' => API_VERSION,
        'documentation' => 'https://docs.connexus.team/api',
        'endpoints' => [
            'events' => '/v1/events',
            'members' => '/v1/members',
            'businesses' => '/v1/businesses',
            'discounts' => '/v1/discounts',
            'announcements' => '/v1/announcements',
            'ping' => '/v1/ping'
        ]
    ]);
}

// Route to version handlers
$segments = explode('/', $path);
$version = $segments[0] ?? '';

if ($version === 'v1') {
    $endpoint = $segments[1] ?? '';
    $id = $segments[2] ?? null;

    // Store ID for endpoint handlers
    $_REQUEST['_id'] = $id;

    $endpoint_file = __DIR__ . '/v1/' . $endpoint . '.php';

    if (file_exists($endpoint_file)) {
        require $endpoint_file;
    } else {
        json_error('Endpoint not found', 404);
    }
} else {
    json_error('Invalid API version. Use /v1/', 400);
}
