<?php
/**
 * Connexus API Configuration
 *
 * Environment variables take precedence over defaults.
 */

if (defined('CONNEXUS_CONFIG_LOADED')) return;
define('CONNEXUS_CONFIG_LOADED', true);

// Database
define('CONNEXUS_MONGO_URI', getenv('CONNEXUS_MONGO_URI') ?: 'mongodb://localhost:27017');
define('CONNEXUS_DB_NAME', getenv('CONNEXUS_DB_NAME') ?: 'ococ_portal');

// API Settings
define('API_VERSION', 'v1');
define('API_DEBUG', getenv('API_DEBUG') === 'true');

// Collection names (match existing dashboard)
define('COL_USERS', 'users');
define('COL_EVENTS', 'events');
define('COL_DISCOUNTS', 'discounts');
define('COL_PUBLIC_COMMENTS', 'public_comments');
define('COL_GROUPS', 'groups');
define('COL_GROUP_ANNOUNCEMENTS', 'group_announcements');
define('COL_GROUP_EVENTS', 'group_events');

// API Keys collection (new for this API)
define('COL_API_KEYS', 'api_keys');
