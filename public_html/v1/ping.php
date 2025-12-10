<?php
/**
 * Health Check Endpoint
 *
 * GET /v1/ping
 */

json_response([
    'ok' => true,
    'time' => gmdate('c'),
    'version' => API_VERSION
]);
