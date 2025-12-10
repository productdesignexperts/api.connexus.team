<?php
/**
 * Events Endpoint
 *
 * GET /v1/events - List all events
 * GET /v1/events/:id - Get single event
 */

// Public endpoint - no API key required for read-only event data

/**
 * Format event for page consumption
 * Returns data in exact format events.php expects
 */
function format_event_for_page($doc) {
    // Get base document data
    $event = format_document($doc);

    // Extract date in Y-m-d format from ISO date
    $dateStr = isset($event['date']) ? substr($event['date'], 0, 10) : date('Y-m-d');

    // Format time - DB stores "18:00", convert to "6:00 PM" format
    $timeStr = $event['time'] ?? 'UNKNOWN';
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $m)) {
        $hour = (int)$m[1];
        $min = $m[2];
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 ?: 12;
        $timeStr = "{$hour12}:{$min} {$ampm}";
    }

    // Build CTA label based on price
    $price = $event['price'] ?? 'UNKNOWN';
    $ctaLabel = ($price === 'Free' || $price === 'UNKNOWN') ? 'RSVP' : "Register ({$price})";

    $result = [
        'id' => $event['id'] ?? uniqid(),
        'title' => $event['title'] ?? 'UNKNOWN',
        'date' => $dateStr,
        'time' => $timeStr,
        'location' => $event['location'] ?? 'UNKNOWN',
        'host' => $event['host'] ?? '',
        'description' => $event['description'] ?? 'UNKNOWN',
        'price' => $price,
        'hours' => $event['hours'] ?? 0,
        'eventType' => $event['eventType'] ?? 'UNKNOWN',
        'badges' => $event['badges'] ?? [],
        'presenter' => $event['presenter'] ?? null,
        'keynote' => $event['keynote'] ?? null,
        'member_quote' => $event['member_quote'] ?? null,
        // Recurrence fields for calendar
        'repeat' => $event['repeat'] ?? 'none',
        'repeat_n' => $event['repeat_n'] ?? null,
        'repeat_weekday' => $event['repeat_weekday'] ?? null,
        'primary_cta' => [
            'label' => $ctaLabel,
            'href' => '/event-detail-page.php?id=' . ($event['id'] ?? ''),
        ],
        'secondary_cta' => [
            'label' => 'Details',
            'href' => '/event-detail-page.php?id=' . ($event['id'] ?? ''),
        ]
    ];

    // Only include thumbnail if image exists
    if (!empty($event['image'])) {
        $result['thumbnail'] = [
            'src' => $event['image'],
            'alt' => ($event['title'] ?? 'Event') . ' image'
        ];
    }

    return $result;
}

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
            'data' => format_event_for_page($event)
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
    'data' => array_map('format_event_for_page', $events),
    'meta' => [
        'total' => $total,
        'limit' => $pagination['limit'],
        'offset' => $pagination['offset']
    ]
]);
