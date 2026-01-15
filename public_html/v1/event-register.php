<?php
/**
 * Event Registration Endpoint
 *
 * POST /v1/event-register
 *
 * Handles event registration:
 * - Logged-in users: One-click registration (pass user_id)
 * - New users: Signup with simplified form, then register
 */

require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/sms_helper.php';

set_cors_headers();

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Get JSON body or form data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Required: event_id
$eventId = trim($input['event_id'] ?? '');
if (empty($eventId)) {
    json_error('Event ID is required', 400);
}

$db = connexus_db();
$eventsCollection = $db->selectCollection(COL_EVENTS);
$usersCollection = $db->selectCollection(COL_USERS);

// Validate event exists
try {
    $event = $eventsCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($eventId)]);
    if (!$event) {
        json_error('Event not found', 404);
    }
} catch (Exception $e) {
    json_error('Invalid event ID', 400);
}

// Check if user_id provided (logged-in user flow)
$userId = trim($input['user_id'] ?? '');

if (!empty($userId)) {
    // LOGGED-IN USER FLOW - One-click registration
    try {
        $user = $usersCollection->findOne([
            '_id' => new MongoDB\BSON\ObjectId($userId),
            'deleted' => ['$ne' => true]
        ]);
        if (!$user) {
            json_error('User not found', 404);
        }
    } catch (Exception $e) {
        json_error('Invalid user ID', 400);
    }

    // Check if already registered
    $existingAttendees = $event['attendees'] ?? [];
    foreach ($existingAttendees as $attendee) {
        if ((string)($attendee['user_id'] ?? '') === $userId) {
            json_response([
                'success' => false,
                'error' => 'already_registered',
                'message' => 'You are already registered for this event.'
            ], 409);
        }
    }

    // Add to attendees
    $newAttendee = [
        'user_id' => $userId,
        'registered_at' => new MongoDB\BSON\UTCDateTime(),
        'registration_source' => 'web'
    ];

    $eventsCollection->updateOne(
        ['_id' => new MongoDB\BSON\ObjectId($eventId)],
        [
            '$push' => ['attendees' => $newAttendee],
            '$inc' => ['attendee_count' => 1]
        ]
    );

    json_response([
        'success' => true,
        'message' => 'You have been registered for this event!',
        'attendee' => [
            'user_id' => $userId,
            'registered_at' => date('c')
        ]
    ]);

} else {
    // NEW USER SIGNUP FLOW
    $firstName = trim($input['first_name'] ?? '');
    $lastName = trim($input['last_name'] ?? '');
    $phone = trim($input['phone'] ?? '');
    $companyName = trim($input['company_name'] ?? '');
    $email = strtolower(trim($input['email'] ?? ''));
    $agreeTerms = !empty($input['agree_terms']);

    // Validate required fields
    if (empty($firstName)) {
        json_error('First name is required', 400);
    }
    if (empty($lastName)) {
        json_error('Last name is required', 400);
    }
    if (empty($phone)) {
        json_error('Phone is required', 400);
    }
    if (empty($companyName)) {
        json_error('Business name is required', 400);
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Valid email is required', 400);
    }
    if (!$agreeTerms) {
        json_error('You must agree to the membership terms', 400);
    }

    // Check if email exists
    $existingUser = $usersCollection->findOne([
        'email' => $email,
        'deleted' => ['$ne' => true]
    ]);

    if ($existingUser) {
        json_response([
            'success' => false,
            'error' => 'email_exists',
            'message' => 'An account with this email already exists. Please log in to register.',
            'login_url' => 'https://myococ.connexus.team/login.php?redirect=' .
                           urlencode('https://ococsite.connexus.team/event-register.php?id=' . $eventId)
        ], 409);
    }

    // Create new user
    $randomPassword = bin2hex(random_bytes(16));

    $newUser = [
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone' => $phone,
        'company' => $companyName,
        'company_name' => $companyName,
        'password_hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
        'role' => 'member',
        'is_paid' => false,
        'paid' => false,
        'signed_up_by' => 'register_event',
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'deleted' => false,
        'agreed_to_terms' => true,
        'agreed_to_terms_at' => new MongoDB\BSON\UTCDateTime()
    ];

    try {
        $result = $usersCollection->insertOne($newUser);
        $newUserId = (string)$result->getInsertedId();

        // Notify admins of new signup
        notify_admins_of_signup([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'phone' => $phone
        ], 'Event Registration');

        // Register for event
        $newAttendee = [
            'user_id' => $newUserId,
            'registered_at' => new MongoDB\BSON\UTCDateTime(),
            'registration_source' => 'web'
        ];

        $eventsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($eventId)],
            [
                '$push' => ['attendees' => $newAttendee],
                '$inc' => ['attendee_count' => 1]
            ]
        );

        json_response([
            'success' => true,
            'message' => 'Account created and registered for event!',
            'attendee' => [
                'user_id' => $newUserId,
                'registered_at' => date('c')
            ],
            'new_user' => true
        ]);

    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        // Duplicate key error (race condition)
        if ($e->getCode() === 11000) {
            json_response([
                'success' => false,
                'error' => 'email_exists',
                'message' => 'An account with this email already exists.'
            ], 409);
        }
        throw $e;
    }
}
