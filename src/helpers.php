<?php
/**
 * API Helper Functions
 */

if (defined('CONNEXUS_HELPERS_LOADED')) return;
define('CONNEXUS_HELPERS_LOADED', true);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Send JSON response and exit
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response
 */
function json_error($message, $status = 400) {
    json_response(['error' => $message], $status);
}

/**
 * Set CORS headers for cross-origin requests
 */
function set_cors_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Extract API key from request
 */
function get_api_key() {
    // Check Authorization header first
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if (preg_match('/Bearer\s+(.+)/', $auth, $matches)) {
        return $matches[1];
    }

    // Fall back to query parameter
    return $_GET['api_key'] ?? null;
}

/**
 * Validate API key (placeholder - currently accepts any non-empty key)
 * TODO: Implement proper API key validation against database
 */
function validate_api_key($key) {
    if (empty($key)) {
        return false;
    }

    // For now, accept any non-empty key during development
    // Later: check against COL_API_KEYS collection
    return true;
}

/**
 * Require valid API key or return 401
 */
function require_api_key() {
    $key = get_api_key();
    if (!validate_api_key($key)) {
        json_error('Invalid or missing API key', 401);
    }
    return $key;
}

/**
 * Get pagination parameters from request
 */
function get_pagination($default_limit = 20, $max_limit = 100) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : $default_limit;
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

    // Enforce limits
    $limit = max(1, min($limit, $max_limit));
    $offset = max(0, $offset);

    return ['limit' => $limit, 'offset' => $offset];
}

/**
 * Format MongoDB document for API response
 * Converts ObjectId to string, UTCDateTime to ISO string
 */
function format_document($doc) {
    if (!$doc) {
        return null;
    }

    $result = [];
    foreach ($doc as $key => $value) {
        if ($value instanceof MongoDB\BSON\ObjectId) {
            $result[$key] = (string) $value;
        } elseif ($value instanceof MongoDB\BSON\UTCDateTime) {
            $result[$key] = $value->toDateTime()->format('c');
        } elseif (is_array($value) || $value instanceof MongoDB\Model\BSONArray) {
            $result[$key] = array_map('format_document', (array) $value);
        } elseif ($value instanceof MongoDB\Model\BSONDocument) {
            $result[$key] = format_document($value);
        } else {
            $result[$key] = $value;
        }
    }

    // Rename _id to id for cleaner API
    if (isset($result['_id'])) {
        $result['id'] = $result['_id'];
        unset($result['_id']);
    }

    return $result;
}

/**
 * Format array of documents
 */
function format_documents($docs) {
    return array_map('format_document', $docs);
}
