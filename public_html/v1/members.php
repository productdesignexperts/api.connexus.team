<?php
/**
 * Members Endpoint
 *
 * GET /v1/members - List all members (public profiles)
 * GET /v1/members/:id - Get single member
 */

require_api_key();

/**
 * Format member document with resolved image URLs
 */
function format_member($doc) {
    $formatted = format_document($doc);

    // Resolve image URLs for photo fields
    if (!empty($formatted['photo'])) {
        $formatted['photo'] = resolve_image_url($formatted['photo']);
    }
    if (!empty($formatted['company_photo'])) {
        $formatted['company_photo'] = resolve_image_url($formatted['company_photo']);
    }

    return $formatted;
}

$db = connexus_db();
$id = $_REQUEST['_id'] ?? null;

// Fields to exclude from public API (sensitive data)
$projection = [
    'password_hash' => 0,
    'api_token' => 0,
    'email' => 0  // Hide email for privacy
];

// Single member
if ($id) {
    try {
        $member = $db->selectCollection(COL_USERS)->findOne(
            [
                '_id' => new MongoDB\BSON\ObjectId($id),
                'deleted' => ['$ne' => true]
            ],
            ['projection' => $projection]
        );

        if (!$member) {
            json_error('Member not found', 404);
        }

        json_response([
            'data' => format_member($member)
        ]);
    } catch (Exception $e) {
        json_error('Invalid member ID', 400);
    }
}

// List members
$pagination = get_pagination();

// Optional search query
$filter = ['deleted' => ['$ne' => true]];
$q = trim($_GET['q'] ?? '');

if ($q !== '') {
    $filter['$or'] = [
        ['first_name' => new MongoDB\BSON\Regex($q, 'i')],
        ['last_name' => new MongoDB\BSON\Regex($q, 'i')],
        ['company' => new MongoDB\BSON\Regex($q, 'i')],
        ['business_description' => new MongoDB\BSON\Regex($q, 'i')]
    ];
}

$cursor = $db->selectCollection(COL_USERS)->find(
    $filter,
    [
        'projection' => $projection,
        'sort' => ['last_name' => 1, 'first_name' => 1],
        'skip' => $pagination['offset'],
        'limit' => $pagination['limit']
    ]
);

$members = iterator_to_array($cursor, false);
$total = $db->selectCollection(COL_USERS)->countDocuments($filter);

json_response([
    'data' => array_map('format_member', $members),
    'meta' => [
        'total' => $total,
        'limit' => $pagination['limit'],
        'offset' => $pagination['offset']
    ]
]);
