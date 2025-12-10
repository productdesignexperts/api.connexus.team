<?php
/**
 * Announcements Endpoint
 *
 * GET /v1/announcements - List public announcements
 * GET /v1/announcements/:id - Get single announcement
 */

require_api_key();

$db = connexus_db();
$id = $_REQUEST['_id'] ?? null;

// Single announcement
if ($id) {
    try {
        $announcement = $db->selectCollection(COL_PUBLIC_COMMENTS)->findOne([
            '_id' => new MongoDB\BSON\ObjectId($id)
        ]);

        if (!$announcement) {
            json_error('Announcement not found', 404);
        }

        json_response([
            'data' => format_document($announcement)
        ]);
    } catch (Exception $e) {
        json_error('Invalid announcement ID', 400);
    }
}

// List announcements
$pagination = get_pagination(50);

$cursor = $db->selectCollection(COL_PUBLIC_COMMENTS)->find(
    [],
    [
        'sort' => ['created_at' => -1],
        'skip' => $pagination['offset'],
        'limit' => $pagination['limit']
    ]
);

$announcements = iterator_to_array($cursor, false);
$total = $db->selectCollection(COL_PUBLIC_COMMENTS)->countDocuments([]);

json_response([
    'data' => format_documents($announcements),
    'meta' => [
        'total' => $total,
        'limit' => $pagination['limit'],
        'offset' => $pagination['offset']
    ]
]);
