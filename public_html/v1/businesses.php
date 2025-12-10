<?php
/**
 * Businesses Endpoint
 *
 * GET /v1/businesses - List all businesses
 * GET /v1/businesses/:id - Get single business
 *
 * Note: Businesses are derived from member profiles that have business info.
 * This provides a business-focused view of the member directory.
 * Only returns businesses where show_in_business_directory is true.
 */

require_api_key();

$db = connexus_db();
$id = $_REQUEST['_id'] ?? null;

/**
 * Format a user document as a business for the directory
 * Maps internal fields to the expected business format
 */
function format_business($doc) {
    if (!$doc) return null;

    $formatted = format_document($doc);

    // Map to business directory format
    // Use company_* fields if available, fall back to legacy fields
    return [
        'id' => $formatted['id'] ?? '',
        'businessName' => $formatted['company_name'] ?? $formatted['company'] ?? '',
        'logoUrl' => $formatted['company_photo'] ?? '',
        'category' => $formatted['business_category'] ?? '',
        'addressLine1' => $formatted['company_address'] ?? '',
        'city' => $formatted['company_city'] ?? '',
        'state' => $formatted['company_state'] ?? '',
        'zip' => $formatted['company_zip'] ?? '',
        'phone' => $formatted['company_phone'] ?? $formatted['phone'] ?? '',
        'websiteUrl' => $formatted['company_website'] ?? '',
        'description' => $formatted['company_description'] ?? $formatted['business_description'] ?? '',
        // Include contact name for reference
        'contactName' => trim(($formatted['first_name'] ?? '') . ' ' . ($formatted['last_name'] ?? ''))
    ];
}

// Single business
if ($id) {
    try {
        $business = $db->selectCollection(COL_USERS)->findOne(
            [
                '_id' => new MongoDB\BSON\ObjectId($id),
                'deleted' => ['$ne' => true],
                'show_in_business_directory' => true
            ]
        );

        if (!$business) {
            json_error('Business not found', 404);
        }

        json_response([
            'data' => format_business($business)
        ]);
    } catch (Exception $e) {
        json_error('Invalid business ID', 400);
    }
}

// List businesses (members with company info who opted into directory)
$pagination = get_pagination();

$filter = [
    'deleted' => ['$ne' => true],
    'show_in_business_directory' => true
];

// Optional category filter
$category = trim($_GET['category'] ?? '');
if ($category !== '') {
    $filter['business_category'] = new MongoDB\BSON\Regex($category, 'i');
}

// Optional city filter
$city = trim($_GET['city'] ?? '');
if ($city !== '') {
    $filter['company_city'] = new MongoDB\BSON\Regex($city, 'i');
}

// Optional search query
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $filter['$or'] = [
        ['company' => new MongoDB\BSON\Regex($q, 'i')],
        ['company_name' => new MongoDB\BSON\Regex($q, 'i')],
        ['company_description' => new MongoDB\BSON\Regex($q, 'i')],
        ['business_description' => new MongoDB\BSON\Regex($q, 'i')],
        ['business_category' => new MongoDB\BSON\Regex($q, 'i')]
    ];
}

$cursor = $db->selectCollection(COL_USERS)->find(
    $filter,
    [
        'sort' => ['company_name' => 1, 'company' => 1],
        'skip' => $pagination['offset'],
        'limit' => $pagination['limit']
    ]
);

$businesses = [];
foreach ($cursor as $doc) {
    $businesses[] = format_business($doc);
}

$total = $db->selectCollection(COL_USERS)->countDocuments($filter);

json_response([
    'data' => $businesses,
    'meta' => [
        'total' => $total,
        'limit' => $pagination['limit'],
        'offset' => $pagination['offset']
    ]
]);
