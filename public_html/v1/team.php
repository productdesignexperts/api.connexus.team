<?php
/**
 * Team/Board Members Endpoint
 *
 * GET /v1/team - List all board/team members
 *
 * Returns members who have any of these member_status values:
 * board, advisor, president, vice_president, executive
 *
 * Output format matches the $teamMembers array structure used in about.php:
 * - name: Full name (first_name + last_name)
 * - title: Board title (board_title field)
 * - image: Profile photo URL (resolved via resolve_image_url)
 */

require_api_key();

/**
 * Format team member for public display
 * Outputs in same format as legacy $teamMembers array
 */
function format_team_member($doc) {
    $formatted = format_document($doc);

    $firstName = $formatted['first_name'] ?? '';
    $lastName = $formatted['last_name'] ?? '';
    $fullName = trim($firstName . ' ' . $lastName);

    // Resolve photo URL - check local myococ server first
    $photo = '';
    if (!empty($formatted['photo'])) {
        $photo = resolve_image_url($formatted['photo']);
    }

    return [
        'name' => $fullName ?: 'Board Member',
        'title' => $formatted['board_title'] ?? 'Director',
        'image' => $photo
    ];
}

$db = connexus_db();

// Filter for team/board members - anyone with a leadership status
// Excludes 'standard' members (they are just regular members)
$leadershipStatuses = ['board', 'advisor', 'chairman', 'president', 'vice_president', 'executive'];

$filter = [
    'deleted' => ['$ne' => true],
    'member_status' => ['$in' => $leadershipStatuses],
    'show_in_public_team' => true
];

// Get all team members (no pagination needed - typically small list)
$cursor = $db->selectCollection(COL_USERS)->find(
    $filter,
    [
        'projection' => [
            'first_name' => 1,
            'last_name' => 1,
            'board_title' => 1,
            'photo' => 1,
            'member_status' => 1
        ],
        'sort' => ['last_name' => 1, 'first_name' => 1]
    ]
);

$members = iterator_to_array($cursor, false);
$total = count($members);

json_response([
    'data' => array_map('format_team_member', $members),
    'meta' => [
        'total' => $total
    ]
]);
