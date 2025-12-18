<?php
/**
 * Check Email Endpoint
 *
 * GET /v1/check-email?email=test@example.com
 *
 * Checks if an email address exists in the member database.
 * Returns member data (excluding sensitive fields) for existing members.
 * Used by join.php form for real-time email validation.
 */

require_once __DIR__ . '/../../src/helpers.php';

set_cors_headers();

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

// Get email from query parameter
$email = strtolower(trim($_GET['email'] ?? ''));

// Validate email
if (empty($email)) {
    json_error('Email address is required', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Invalid email address format', 400);
}

$db = connexus_db();
$usersCollection = $db->selectCollection(COL_USERS);

// Find user by email
$user = $usersCollection->findOne([
    'email' => $email,
    'deleted' => ['$ne' => true]
]);

if (!$user) {
    // Email not found
    json_response([
        'success' => true,
        'exists' => false,
        'email' => $email
    ]);
}

// Email found - format member data (exclude sensitive fields)
$is_paid = !empty($user['is_paid']) || !empty($user['paid']);

// Build member data for response (all fields for comparison modal)
$member = [
    'first_name' => $user['first_name'] ?? '',
    'middle_name' => $user['middle_name'] ?? '',
    'last_name' => $user['last_name'] ?? '',
    'contact_title' => $user['contact_title'] ?? '',
    'phone' => $user['phone'] ?? '',
    'company_name' => $user['company_name'] ?? $user['company'] ?? '',
    'company_phone' => $user['company_phone'] ?? '',
    'company_email' => $user['company_email'] ?? '',
    'company_website' => $user['company_website'] ?? '',
    'company_address' => $user['company_address'] ?? '',
    'company_city' => $user['company_city'] ?? '',
    'company_state' => $user['company_state'] ?? '',
    'company_zip' => $user['company_zip'] ?? '',
    'company_description' => $user['company_description'] ?? $user['business_description'] ?? '',
    'business_category' => $user['business_category'] ?? '',
    'num_employees' => $user['num_employees'] ?? '',
    'video_url' => $user['video_url'] ?? '',
    'social_media' => $user['social_media'] ?? [],
    'business_hours' => $user['business_hours'] ?? [],
    'business_faqs' => $user['business_faqs'] ?? '',
    'interests' => (array)($user['interests'] ?? []),
    'company_photo' => !empty($user['company_photo']) ? resolve_image_url($user['company_photo']) : '',
    'company_photos' => array_map('resolve_image_url', (array)($user['company_photos'] ?? [])),
    'photo' => !empty($user['photo']) ? resolve_image_url($user['photo']) : '',
];

json_response([
    'success' => true,
    'exists' => true,
    'is_paid' => $is_paid,
    'email' => $email,
    'member' => $member
]);
