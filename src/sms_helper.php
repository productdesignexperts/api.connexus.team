<?php
/**
 * SMS Helper - Reusable SMS sending function using Esendex
 * For api.connexus.team
 */

if (defined('SMS_HELPER_LOADED')) return;
define('SMS_HELPER_LOADED', true);

/** ---- MongoDB Config ---- */
function sms_mongo_manager() {
    $MONGO_DSN = getenv('MONGO_DSN') ?: 'mongodb://127.0.0.1:27017';
    static $mgr = null;
    if (!$mgr) $mgr = new MongoDB\Driver\Manager($MONGO_DSN);
    return $mgr;
}

function sms_log($doc) {
    $MONGO_DB = getenv('OCOC_DB_NAME') ?: 'ococ_portal';
    $LOG_COL = 'sms_debug';
    try {
        $bulk = new MongoDB\Driver\BulkWrite();
        $doc['_created_at'] = new MongoDB\BSON\UTCDateTime((int)(microtime(true)*1000));
        $bulk->insert($doc);
        sms_mongo_manager()->executeBulkWrite("$MONGO_DB.$LOG_COL", $bulk);
    } catch (Throwable $e) {
        error_log('sms_log insert failed: '.$e->getMessage());
    }
}

/**
 * Send an SMS message
 *
 * @param string $phone 10-digit phone number (digits only)
 * @param string $body Message body
 * @param string $subject Optional subject for MMS
 * @param bool $useMMS Whether to send as MMS (default false for simple text)
 * @param string $type Type of SMS for logging (e.g., 'password_reset', 'signup_notification')
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_sms($phone, $body, $subject = 'Orlando Chamber', $useMMS = false, $type = 'general') {
    // Clean phone number
    $phone = preg_replace('/\D+/', '', $phone);

    if (strlen($phone) !== 10) {
        return ['success' => false, 'error' => 'Invalid phone number'];
    }

    // Build Esendex payload
    $payload = [
        "Body"        => $body,
        "Concatenate" => false,
        "From"        => "17273618845",
        "IsUnicode"   => false,
        "LicenseKey"  => "664978ef-2290-4f69-9db3-94bcd9a42918",
        "Subject"     => $subject,
        "To"          => ["1" . $phone],
        "UseMMS"      => $useMMS
    ];

    $json = json_encode($payload);
    $url = 'https://messaging.esendex.us/Messaging.svc/SendMessage';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $result = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    $success = false;
    $error = null;

    if ($curlErr) {
        $error = $curlErr;
    } else {
        $decoded = json_decode($result, true);
        if (is_array($decoded)) {
            $success = true;
        } else {
            $error = 'Unexpected response from SMS service';
        }
    }

    // Log the SMS attempt
    sms_log([
        'type' => $type,
        'phone' => $phone,
        'final_body' => $body,
        'payload' => $payload,
        'request_json' => $json,
        'response_raw' => $result,
        'success' => $success,
        'error' => $error
    ]);

    return ['success' => $success, 'error' => $error];
}

/**
 * Notify all super admins via SMS when a new user signs up
 *
 * @param array $newUser Array with keys: first_name, last_name, company_name, phone
 * @param string $source Source of signup (e.g., 'Event Form', 'Contact Form')
 * @return array ['sent' => int, 'failed' => int, 'errors' => array]
 */
function notify_admins_of_signup($newUser, $source = 'Website') {
    $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

    // Use direct MongoDB driver
    $MONGO_DB = getenv('OCOC_DB_NAME') ?: 'ococ_portal';
    $mgr = sms_mongo_manager();

    // Find all super admins with valid phone numbers (exclude deleted users)
    $query = new MongoDB\Driver\Query([
        'is_super_admin' => true,
        'phone' => ['$exists' => true, '$ne' => ''],
        'deleted' => ['$ne' => true]
    ]);
    $admins = $mgr->executeQuery("$MONGO_DB.users", $query);

    // Build the notification message
    $firstName = $newUser['first_name'] ?? 'N/A';
    $lastName = $newUser['last_name'] ?? 'N/A';
    $companyName = $newUser['company_name'] ?? $newUser['company'] ?? 'N/A';
    $userPhone = $newUser['phone'] ?? 'N/A';

    // Get description for the source
    $sourceDescription = match($source) {
        'Event Form' => 'Signed up for event calendar reminders on homepage',
        'Contact Form' => 'Submitted contact form on website',
        'Direct Signup' => 'Registered via signup page',
        default => $source
    };

    $message = "OCOC New Signup Alert!\n\n";
    $message .= "Source: {$source}\n";
    $message .= "({$sourceDescription})\n\n";
    $message .= "Name: {$firstName} {$lastName}\n";
    $message .= "Company: {$companyName}\n";
    $message .= "Phone: {$userPhone}";

    foreach ($admins as $admin) {
        $adminPhone = preg_replace('/\D+/', '', (string)($admin->phone ?? ''));

        // Skip if no valid phone number
        if (strlen($adminPhone) !== 10) {
            continue;
        }

        $result = send_sms($adminPhone, $message, 'OCOC New Signup', false, 'signup_notification');

        if ($result['success']) {
            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][] = [
                'admin' => $admin->email ?? 'unknown',
                'error' => $result['error']
            ];
        }
    }

    // Log the notification attempt
    sms_log([
        'type' => 'admin_signup_notification',
        'source' => $source,
        'new_user' => [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'phone' => $userPhone
        ],
        'results' => $results
    ]);

    return $results;
}

/**
 * Notify all super admins via SMS when a contact form is submitted
 *
 * @param array $submission Array with keys: first_name, last_name, company_name, email, message
 * @param bool $existingUser Whether this is from an existing user
 * @return array ['sent' => int, 'failed' => int, 'errors' => array]
 */
function notify_admins_of_contact_form($submission, $existingUser = false) {
    $results = ['sent' => 0, 'failed' => 0, 'errors' => []];

    // Use direct MongoDB driver
    $MONGO_DB = getenv('OCOC_DB_NAME') ?: 'ococ_portal';
    $mgr = sms_mongo_manager();

    // Find all super admins with valid phone numbers (exclude deleted users)
    $query = new MongoDB\Driver\Query([
        'is_super_admin' => true,
        'phone' => ['$exists' => true, '$ne' => ''],
        'deleted' => ['$ne' => true]
    ]);
    $admins = $mgr->executeQuery("$MONGO_DB.users", $query);

    // Build the notification message
    $firstName = $submission['first_name'] ?? 'N/A';
    $lastName = $submission['last_name'] ?? 'N/A';
    $companyName = $submission['company_name'] ?? 'N/A';
    $email = $submission['email'] ?? 'N/A';
    $userMessage = $submission['message'] ?? '';

    $message = "OCOC Contact Form Submission\n\n";
    $message .= "Name: {$firstName} {$lastName}\n";
    if (!empty($companyName) && $companyName !== 'N/A') {
        $message .= "Company: {$companyName}\n";
    }
    $message .= "Email: {$email}\n";
    if ($existingUser) {
        $message .= "(Existing member)\n";
    }
    if (!empty($userMessage)) {
        // Truncate message if too long
        $truncatedMsg = strlen($userMessage) > 200 ? substr($userMessage, 0, 200) . '...' : $userMessage;
        $message .= "\nMessage:\n{$truncatedMsg}";
    }

    foreach ($admins as $admin) {
        $adminPhone = preg_replace('/\D+/', '', (string)($admin->phone ?? ''));

        // Skip if no valid phone number
        if (strlen($adminPhone) !== 10) {
            continue;
        }

        $result = send_sms($adminPhone, $message, 'OCOC Contact Form', false, 'contact_form_notification');

        if ($result['success']) {
            $results['sent']++;
        } else {
            $results['failed']++;
            $results['errors'][] = [
                'admin' => $admin->email ?? 'unknown',
                'error' => $result['error']
            ];
        }
    }

    // Log the notification attempt
    sms_log([
        'type' => 'admin_contact_form_notification',
        'existing_user' => $existingUser,
        'submission' => [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'email' => $email,
            'message' => $userMessage
        ],
        'results' => $results
    ]);

    return $results;
}
