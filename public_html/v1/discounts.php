<?php
/**
 * Discounts Endpoint
 *
 * GET /v1/discounts - List all public discounts
 * GET /v1/discounts/:id - Get single discount
 */

require_api_key();

$db = connexus_db();
$id = $_REQUEST['_id'] ?? null;

// Single discount
if ($id) {
    try {
        $discount = $db->selectCollection(COL_DISCOUNTS)->findOne([
            '_id' => new MongoDB\BSON\ObjectId($id),
            'enabled' => ['$ne' => false],
            '$or' => [
                ['status' => ['$ne' => 'proposed']],
                ['status' => ['$exists' => false]]
            ]
        ]);

        if (!$discount) {
            json_error('Discount not found', 404);
        }

        json_response([
            'data' => format_document($discount)
        ]);
    } catch (Exception $e) {
        json_error('Invalid discount ID', 400);
    }
}

// List discounts (only public/approved ones)
$pagination = get_pagination();

$filter = [
    'enabled' => ['$ne' => false],
    '$or' => [
        ['status' => ['$ne' => 'proposed']],
        ['status' => ['$exists' => false]]
    ]
];

$cursor = $db->selectCollection(COL_DISCOUNTS)->find(
    $filter,
    [
        'sort' => ['created_at' => -1],
        'skip' => $pagination['offset'],
        'limit' => $pagination['limit']
    ]
);

$discounts = iterator_to_array($cursor, false);
$total = $db->selectCollection(COL_DISCOUNTS)->countDocuments($filter);

json_response([
    'data' => format_documents($discounts),
    'meta' => [
        'total' => $total,
        'limit' => $pagination['limit'],
        'offset' => $pagination['offset']
    ]
]);
