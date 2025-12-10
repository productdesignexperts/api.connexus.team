<?php
/**
 * Businesses Endpoint
 *
 * GET /v1/businesses - List all businesses
 * GET /v1/businesses/:id - Get single business
 *
 * Note: Businesses are derived from member profiles that have business info.
 * This provides a business-focused view of the member directory.
 */

require_api_key();

$db = connexus_db();
$id = $_REQUEST['_id'] ?? null;

// Fields to include for business listings
$projection = [
    'password_hash' => 0,
    'api_token' => 0,
    'email' => 0
];

// Single business
if ($id) {
    try {
        $business = $db->selectCollection(COL_USERS)->findOne(
            [
                '_id' => new MongoDB\BSON\ObjectId($id),
                'deleted' => ['$ne' => true],
                'company' => ['$exists' => true, '$ne' => '']
            ],
            ['projection' => $projection]
        );

        if (!$business) {
            json_error('Business not found', 404);
        }

        json_response([
            'data' => format_document($business)
        ]);
    } catch (Exception $e) {
        json_error('Invalid business ID', 400);
    }
}

// List businesses (members with company info)
$pagination = get_pagination();

$filter = [
    'deleted' => ['$ne' => true],
    'company' => ['$exists' => true, '$ne' => '']
];

// Optional category filter
$category = trim($_GET['category'] ?? '');
if ($category !== '') {
    $filter['business_category'] = new MongoDB\BSON\Regex($category, 'i');
}

// Optional search query
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $filter['$or'] = [
        ['company' => new MongoDB\BSON\Regex($q, 'i')],
        ['business_description' => new MongoDB\BSON\Regex($q, 'i')],
        ['business_category' => new MongoDB\BSON\Regex($q, 'i')]
    ];
}

$cursor = $db->selectCollection(COL_USERS)->find(
    $filter,
    [
        'projection' => $projection,
        'sort' => ['company' => 1],
        'skip' => $pagination['offset'],
        'limit' => $pagination['limit']
    ]
);

$businesses = iterator_to_array($cursor, false);
$total = $db->selectCollection(COL_USERS)->countDocuments($filter);

json_response([
    'data' => format_documents($businesses),
    'meta' => [
        'total' => $total,
        'limit' => $pagination['limit'],
        'offset' => $pagination['offset']
    ]
]);
