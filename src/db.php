<?php
/**
 * Database Connection
 *
 * Provides MongoDB connection using singleton pattern.
 */

if (defined('CONNEXUS_DB_LOADED')) return;
define('CONNEXUS_DB_LOADED', true);

require_once __DIR__ . '/config.php';

function connexus_db() {
    static $db = null;
    if ($db !== null) {
        return $db;
    }

    require_once __DIR__ . '/../vendor/autoload.php';

    $client = new MongoDB\Client(CONNEXUS_MONGO_URI);
    $db = $client->selectDatabase(CONNEXUS_DB_NAME);

    return $db;
}
