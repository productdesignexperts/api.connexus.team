<?php
/**
 * Events Endpoint
 *
 * GET /v1/events - List all events
 * GET /v1/events/:id - Get single event
 */

// Public endpoint - no API key required for read-only event data

$db = connexus_db();
$id = $_REQUEST['_id'] ?? null;

// Single event
if ($id) {
    try {
        $event = $db->selectCollection(COL_EVENTS)->findOne([
            '_id' => new MongoDB\BSON\ObjectId($id)
        ]);

        if (!$event) {
            json_error('Event not found', 404);
        }

        json_response([
            'data' => format_document($event)
        ]);
    } catch (Exception $e) {
        json_error('Invalid event ID', 400);
    }
}

// List events
$pagination = get_pagination();

$cursor = $db->selectCollection(COL_EVENTS)->find(
    [],
    [
        'sort' => ['start' => 1, 'date' => 1, 'created_at' => -1],
        'skip' => $pagination['offset'],
        'limit' => $pagination['limit']
    ]
);

$events = iterator_to_array($cursor, false);
$total = $db->selectCollection(COL_EVENTS)->countDocuments([]);

json_response([
    'data' => format_documents($events),
    'meta' => [
        'total' => $total,
        'limit' => $pagination['limit'],
        'offset' => $pagination['offset']
    ]
]);
