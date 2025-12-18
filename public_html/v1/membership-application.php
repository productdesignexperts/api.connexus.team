<?php
/**
 * Membership Application Endpoint
 *
 * POST /v1/membership-application
 *
 * Handles membership application submissions from join.php form:
 * - Creates new member with is_paid=false
 * - Or updates existing member if update_existing=true
 * - Handles file uploads for company photos
 * - Creates admin notification for new applications
 */

require_once __DIR__ . '/../../src/helpers.php';

set_cors_headers();

// TEMPORARY DEBUG - log incoming data
$debugLog = '/tmp/membership_debug.log';
file_put_contents($debugLog, date('Y-m-d H:i:s') . " === NEW REQUEST ===\n", FILE_APPEND);
file_put_contents($debugLog, "Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none') . "\n", FILE_APPEND);
file_put_contents($debugLog, "POST data: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($debugLog, "Raw input: " . file_get_contents('php://input') . "\n", FILE_APPEND);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed', 405);
}

// Get input data - support both JSON and multipart form data
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

// Required fields
$email = strtolower(trim($input['email'] ?? $input['businessEmail'] ?? ''));
$firstName = trim($input['firstName'] ?? $input['first_name'] ?? '');
$lastName = trim($input['lastName'] ?? $input['last_name'] ?? '');
$businessName = trim($input['businessName'] ?? $input['company_name'] ?? '');
$businessPhone = trim($input['businessPhone'] ?? $input['company_phone'] ?? '');
$businessAddress = trim($input['businessStreet'] ?? $input['company_address'] ?? '');
$businessCity = trim($input['businessCity'] ?? $input['company_city'] ?? '');
$businessState = trim($input['businessState'] ?? $input['company_state'] ?? '');
$businessZip = trim($input['businessZip'] ?? $input['company_zip'] ?? '');
$businessCategory = trim($input['businessCategory'] ?? $input['business_category'] ?? '');
$businessDescription = trim($input['businessDescription'] ?? $input['company_description'] ?? '');
$password = $input['password'] ?? '';

// Optional fields
$middleName = trim($input['middleName'] ?? $input['middle_name'] ?? '');
$contactTitle = trim($input['contactTitle'] ?? $input['contact_title'] ?? '');
$contactMobilePhone = trim($input['contactMobilePhone'] ?? $input['phone'] ?? '');
$businessWebsite = trim($input['businessWebsite'] ?? $input['company_website'] ?? '');
$numEmployees = trim($input['numEmployees'] ?? $input['num_employees'] ?? '');
$hearAboutUs = trim($input['hearAboutUs'] ?? $input['hear_about_us'] ?? '');
$preferredStartMonth = trim($input['preferredStartMonth'] ?? $input['preferred_start_month'] ?? '');
$additionalNotes = trim($input['additionalNotes'] ?? $input['application_notes'] ?? '');
$videoUrl = trim($input['video'] ?? $input['video_url'] ?? '');
$businessFaqs = trim($input['businessFaqs'] ?? $input['business_faqs'] ?? '');

// Social media
$socialMedia = [
    'facebook' => trim($input['facebook'] ?? $input['social_media']['facebook'] ?? ''),
    'instagram' => trim($input['instagram'] ?? $input['social_media']['instagram'] ?? ''),
    'x' => trim($input['x'] ?? $input['social_media']['x'] ?? ''),
    'linkedin' => trim($input['linkedin'] ?? $input['social_media']['linkedin'] ?? ''),
    'youtube' => trim($input['youtube'] ?? $input['social_media']['youtube'] ?? ''),
];

// Interests
$interests = [];
if (isset($input['interests'])) {
    $interests = is_array($input['interests']) ? $input['interests'] : [$input['interests']];
}

// Business hours
$businessHours = [];
$daysOfWeek = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
foreach ($daysOfWeek as $day) {
    if (isset($input['businessHours'][$day]) || isset($input['business_hours'][$day])) {
        $dayData = $input['businessHours'][$day] ?? $input['business_hours'][$day] ?? [];
        $businessHours[$day] = [
            'status' => $dayData['status'] ?? 'closed',
            'open' => $dayData['open'] ?? '',
            'close' => $dayData['close'] ?? '',
        ];
    }
}

// Update flags
$updateExisting = !empty($input['update_existing']);
$existingMemberId = $input['existing_member_id'] ?? null;

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
if (empty($businessName)) {
    json_error('Business name is required', 400);
}
if (empty($businessPhone)) {
    json_error('Business phone is required', 400);
}
if (empty($businessAddress)) {
    json_error('Business address is required', 400);
}
if (empty($businessCity)) {
    json_error('Business city is required', 400);
}
if (empty($businessState)) {
    json_error('Business state is required', 400);
}
if (empty($businessZip)) {
    json_error('Business ZIP code is required', 400);
}
if (empty($businessCategory)) {
    json_error('Business category is required', 400);
}
if (empty($businessDescription)) {
    json_error('Business description is required', 400);
}

$db = connexus_db();
$usersCollection = $db->selectCollection(COL_USERS);
$contactCollection = $db->selectCollection(COL_CONTACT_SUBMISSIONS);

// Check if email exists
$existingUser = $usersCollection->findOne([
    'email' => $email,
    'deleted' => ['$ne' => true]
]);

$isUpdate = false;
$memberId = null;

// Upload directory on myococ server
$uploadDir = '/var/www/myococ.connexus.team/public_html/uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

// Handle photo uploads
$companyPhotos = [];
if (isset($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    foreach ($_FILES['photos']['name'] as $key => $name) {
        if ($_FILES['photos']['error'][$key] === UPLOAD_ERR_OK && !empty($name)) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: 'jpg');
            $unique = uniqid() . '_' . bin2hex(random_bytes(4));
            $filename = 'company_app_' . $unique . '.' . $ext;
            $dest = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['photos']['tmp_name'][$key], $dest)) {
                $companyPhotos[] = '/uploads/' . $filename;
            }
        }
    }
}

if ($existingUser) {
    // User exists
    $isPaid = !empty($existingUser['is_paid']) || !empty($existingUser['paid']);
    $memberId = (string)$existingUser['_id'];

    if (!$updateExisting) {
        // Not requesting update - return error with user data
        json_response([
            'success' => false,
            'error' => 'email_exists',
            'is_paid' => $isPaid,
            'message' => $isPaid
                ? 'This email is already registered as a paid member.'
                : 'This email is already registered. You can update your information or log in.',
            'member_id' => $memberId
        ], 409);
    }

    // Update existing member
    $isUpdate = true;

    // Preserve existing photos if we have new ones, append them
    $existingPhotos = (array)($existingUser['company_photos'] ?? []);
    if (!empty($companyPhotos)) {
        $companyPhotos = array_merge($existingPhotos, $companyPhotos);
    } else {
        $companyPhotos = $existingPhotos;
    }

    // Build update data
    $updateData = [
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'contact_title' => $contactTitle,
        'phone' => $contactMobilePhone,
        'company_name' => $businessName,
        'company' => $businessName,
        'company_phone' => $businessPhone,
        'company_website' => $businessWebsite,
        'company_address' => $businessAddress,
        'company_city' => $businessCity,
        'company_state' => $businessState,
        'company_zip' => $businessZip,
        'company_description' => $businessDescription,
        'business_description' => $businessDescription,
        'business_category' => $businessCategory,
        'num_employees' => $numEmployees,
        'video_url' => $videoUrl,
        'social_media' => $socialMedia,
        'business_hours' => $businessHours,
        'business_faqs' => $businessFaqs,
        'hear_about_us' => $hearAboutUs,
        'preferred_start_month' => $preferredStartMonth,
        'interests' => $interests,
        'application_notes' => $additionalNotes,
        'application_status' => 'pending_invoice',
        'application_date' => new MongoDB\BSON\UTCDateTime(),
        'company_photos' => $companyPhotos,
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
    ];

    // Update password if provided (for existing members, password is optional)
    if (!empty($password)) {
        $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    $usersCollection->updateOne(
        ['_id' => $existingUser['_id']],
        ['$set' => $updateData]
    );

} else {
    // New member - password is required
    if (empty($password)) {
        json_error('Password is required for new members', 400);
    }

    // Create new member
    $newUser = [
        'email' => $email,
        'first_name' => $firstName,
        'middle_name' => $middleName,
        'last_name' => $lastName,
        'contact_title' => $contactTitle,
        'phone' => $contactMobilePhone,
        'company_name' => $businessName,
        'company' => $businessName,
        'company_phone' => $businessPhone,
        'company_email' => $email,
        'company_website' => $businessWebsite,
        'company_address' => $businessAddress,
        'company_city' => $businessCity,
        'company_state' => $businessState,
        'company_zip' => $businessZip,
        'company_description' => $businessDescription,
        'business_description' => $businessDescription,
        'business_category' => $businessCategory,
        'num_employees' => $numEmployees,
        'video_url' => $videoUrl,
        'social_media' => $socialMedia,
        'business_hours' => $businessHours,
        'business_faqs' => $businessFaqs,
        'company_photos' => $companyPhotos,
        'hear_about_us' => $hearAboutUs,
        'preferred_start_month' => $preferredStartMonth,
        'interests' => $interests,
        'application_notes' => $additionalNotes,
        'application_status' => 'pending_invoice',
        'application_date' => new MongoDB\BSON\UTCDateTime(),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'member',
        'is_paid' => false,
        'paid' => false,
        'show_in_business_directory' => true,
        'show_in_member_directory' => true,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'deleted' => false,
        'signed_up_by' => 'join_form',
    ];

    try {
        $result = $usersCollection->insertOne($newUser);
        $memberId = (string)$result->getInsertedId();
    } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
        // Duplicate key error
        if ($e->getCode() === 11000) {
            json_error('This email address is already registered', 409);
        }
        throw $e;
    }
}

// Store the application submission for tracking
$applicationSubmission = [
    'member_id' => $memberId,
    'email' => $email,
    'first_name' => $firstName,
    'last_name' => $lastName,
    'business_name' => $businessName,
    'is_update' => $isUpdate,
    'submitted_at' => new MongoDB\BSON\UTCDateTime(),
    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    'source' => 'join_form',
];
$contactCollection->insertOne($applicationSubmission);

// Create admin notification in messages collection
try {
    $messagesCollection = $db->selectCollection('messages');
    $adminNotification = [
        'type' => 'membership_application',
        'subject' => $isUpdate ? 'Membership Application Updated' : 'New Membership Application',
        'body' => ($isUpdate ? 'Updated' : 'New') . " membership application received:\n\n" .
                  "Name: {$firstName} {$lastName}\n" .
                  "Business: {$businessName}\n" .
                  "Email: {$email}\n" .
                  "Phone: {$businessPhone}\n" .
                  "Status: Pending Invoice\n\n" .
                  "View member details in the admin panel.",
        'member_id' => $memberId,
        'from' => 'system',
        'to' => 'admin',
        'read' => false,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
    ];
    $messagesCollection->insertOne($adminNotification);
} catch (Exception $e) {
    // Don't fail the request if notification fails
    error_log("Failed to create admin notification: " . $e->getMessage());
}

json_response([
    'success' => true,
    'message' => $isUpdate
        ? 'Your membership application has been updated successfully. We will be in touch regarding your invoice.'
        : 'Thank you for your membership application! We will send you an invoice shortly.',
    'member_id' => $memberId,
    'is_update' => $isUpdate,
]);
