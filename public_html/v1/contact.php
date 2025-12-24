<?php
/**
 * Contact Form Endpoint
 *
 * POST /v1/contact
 *
 * Handles contact form submissions:
 * - If email exists: Enable event_reminder notifications (email + sms)
 * - If email not found: Create unpaid member with contact_form_signup flag
 * - Always stores the contact submission in contact_submissions collection
 */

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/sms_helper.php';

set_cors_headers();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Get JSON body
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fall back to form data
    $input = $_POST;
}

// Required fields
$email = strtolower(trim($input['email'] ?? $input['business_email'] ?? $input['business-email'] ?? ''));
$firstName = trim($input['first_name'] ?? $input['first-name'] ?? '');
$lastName = trim($input['last_name'] ?? $input['last-name'] ?? '');

// Optional fields
$businessName = trim($input['business_name'] ?? $input['business-name'] ?? '');
$businessPhone = trim($input['business_phone'] ?? $input['business-phone'] ?? '');
$mobilePhone = trim($input['mobile_phone'] ?? $input['mobile-phone'] ?? '');
$message = trim($input['message'] ?? '');
$optIn = !empty($input['opt_in'] ?? $input['opt-in'] ?? false);

// Validate required fields
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_error('Valid email address is required', 400);
}
if (empty($firstName)) {
    json_error('First name is required', 400);
}
if (empty($lastName)) {
    json_error('Last name is required', 400);
}

$db = connexus_db();
$usersCollection = $db->selectCollection(COL_USERS);
$contactCollection = $db->selectCollection(COL_CONTACT_SUBMISSIONS);

// Store the contact submission
$contactSubmission = [
    'email' => $email,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'business_name' => $businessName,
    'business_phone' => $businessPhone,
    'mobile_phone' => $mobilePhone,
    'message' => $message,
    'opt_in' => $optIn,
    'submitted_at' => new MongoDB\BSON\UTCDateTime(),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
];

$contactCollection->insertOne($contactSubmission);

// Check if user already exists
$existingUser = $usersCollection->findOne(['email' => $email]);

if ($existingUser) {
    // User exists - update notification preferences to enable event_reminder for email and sms
    $currentPrefs = $existingUser['notification_preferences'] ?? [];
    $currentPrefs['event_reminder'] = [
        'email' => true,
        'sms' => true
    ];

    $usersCollection->updateOne(
        ['_id' => $existingUser['_id']],
        ['$set' => ['notification_preferences' => $currentPrefs]]
    );

    // Notify admins of contact form submission
    notify_admins_of_contact_form([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'company_name' => $businessName,
        'email' => $email,
        'message' => $message
    ], true);

    json_response([
        'success' => true,
        'message' => 'Thank you for contacting us! We will be in touch shortly.',
        'existing_user' => true
    ]);
} else {
    // New user - create unpaid member
    $randomPassword = bin2hex(random_bytes(16));
    $phone = !empty($mobilePhone) ? $mobilePhone : $businessPhone;

    $newUser = [
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'company' => $businessName,
        'company_name' => $businessName,
        'company_phone' => $businessPhone,
        'password_hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
        'role' => 'member',
        'paid' => false,
        'signed_up_by' => 'contact_form',
        'notification_preferences' => [
            'event_reminder' => [
                'email' => true,
                'sms' => true
            ]
        ],
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'deleted' => false
    ];

    try {
        $usersCollection->insertOne($newUser);

        // Notify super admins of new signup via SMS
        notify_admins_of_signup([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $businessName,
            'phone' => $phone
        ], 'Contact Form');

        // Also notify about the contact form submission with message
        notify_admins_of_contact_form([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $businessName,
            'email' => $email,
            'message' => $message
        ], false);

        json_response([
            'success' => true,
            'message' => 'Thank you for contacting us! We will be in touch shortly.',
            'existing_user' => false
        ]);
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        // Duplicate key error (race condition - email was just added)
        if ($e->getCode() === 11000) {
            json_response([
                'success' => true,
                'message' => 'Thank you for contacting us! We will be in touch shortly.',
                'existing_user' => true
            ]);
        }
        throw $e;
    }
}
