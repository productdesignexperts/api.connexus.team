<?php
/**
 * Featured Events Endpoint
 *
 * GET /v1/featured-events - Get randomly selected featured event(s)
 * GET /v1/featured-events?count=N - Get N featured events (default: 1)
 */

// Public endpoint - no API key required for read-only event data

// Default placeholder image
define('DEFAULT_EVENT_IMAGE', '/images/Leadership.jpg');

/**
 * Format event for featured display
 */
function format_featured_event($doc) {
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

    // Image - use default if not present, resolve to full URL if on myococ server
    $imageSrc = !empty($event['image']) ? resolve_image_url($event['image']) : resolve_image_url(DEFAULT_EVENT_IMAGE);
    $imageAlt = ($event['title'] ?? 'Event') . ' image';

    return [
        'id' => $event['id'] ?? null,
        'title' => $event['title'] ?? 'UNKNOWN',
        'date' => $dateStr,
        'time' => $timeStr,
        'location' => $event['location'] ?? 'UNKNOWN',
        'description' => $event['description'] ?? 'UNKNOWN',
        'image' => [
            'src' => $imageSrc,
            'alt' => $imageAlt
        ],
        'primary_cta' => [
            'label' => $ctaLabel,
            'href' => '/event-detail-page.php?id=' . ($event['id'] ?? ''),
        ],
        'secondary_cta' => [
            'label' => 'Details',
            'href' => '/event-detail-page.php?id=' . ($event['id'] ?? ''),
        ]
    ];
}

$db = connexus_db();

// Get count parameter (how many featured events to return)
$count = isset($_GET['count']) ? max(1, min(10, (int)$_GET['count'])) : 1;

// Get all events, then randomly select
$cursor = $db->selectCollection(COL_EVENTS)->find(
    [],
    ['sort' => ['date' => 1]]
);

$events = iterator_to_array($cursor, false);

if (empty($events)) {
    // Return fallback if no events
    json_response([
        'data' => [[
            'id' => null,
            'title' => 'UNKNOWN',
            'date' => date('Y-m-d'),
            'time' => 'UNKNOWN',
            'location' => 'UNKNOWN',
            'description' => 'No events available.',
            'image' => [
                'src' => resolve_image_url(DEFAULT_EVENT_IMAGE),
                'alt' => 'Event placeholder'
            ],
            'primary_cta' => [
                'label' => 'View Events',
                'href' => '/events.php',
            ],
            'secondary_cta' => null
        ]]
    ]);
}

// Randomly select event(s)
$selectedKeys = (array)array_rand($events, min($count, count($events)));
$featured = [];

foreach ($selectedKeys as $key) {
    $featured[] = format_featured_event($events[$key]);
}

json_response([
    'data' => $count === 1 ? $featured[0] : $featured
]);
