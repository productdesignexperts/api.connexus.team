<?php
/**
 * Team/Board Members Endpoint
 *
 * GET /v1/team - List all board/team members
 *
 * Returns members who have any of these member_status values:
 * board, advisor, president, vice_president, executive, chairman
 *
 * Output format matches the $teamMembers array structure used in about.php:
 * - name: Full name (first_name + last_name)
 * - title: Board title (board_title field)
 * - image: Profile photo URL (resolved via resolve_image_url)
 *
 * SORTING: Members are sorted by role hierarchy (highest role first):
 * 1. Chairman / President
 * 2. Executive / Vice President
 * 3. Board (Directors)
 * 4. Advisor
 * Within the same hierarchy level, members are sorted alphabetically by last name.
 */

require_api_key();

/**
 * Role hierarchy priority (lower number = higher priority)
 * If a member has multiple roles, they are sorted by their highest role.
 */
function get_role_priority($memberStatuses) {
    // Handle null, empty, or non-array values
    if (empty($memberStatuses)) {
        return 99;
    }
    if (!is_array($memberStatuses)) {
        $memberStatuses = [$memberStatuses];
    }

    // Priority levels (lower = higher rank)
    $priorities = [
        'chairman' => 1,
        'president' => 1,
        'executive' => 2,
        'vice_president' => 2,
        'board' => 3,      // Directors
        'director' => 3,   // Alternative name
        'advisor' => 4,
    ];

    $highestPriority = 99; // Default to lowest

    foreach ($memberStatuses as $status) {
        // Skip non-string values
        if (!is_string($status)) {
            continue;
        }
        $status = strtolower(trim($status));
        if (isset($priorities[$status]) && $priorities[$status] < $highestPriority) {
            $highestPriority = $priorities[$status];
        }
    }

    return $highestPriority;
}

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
        'image' => $photo,
        '_priority' => get_role_priority($formatted['member_status'] ?? []),
        '_last_name' => $lastName
    ];
}

$db = connexus_db();

// Filter for team/board members - anyone with a leadership status
// Excludes 'standard' members (they are just regular members)
$leadershipStatuses = ['board', 'advisor', 'chairman', 'president', 'vice_president', 'executive', 'director'];

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
        ]
    ]
);

$members = iterator_to_array($cursor, false);

// Format members with priority
$formattedMembers = array_map('format_team_member', $members);

// Sort by priority (role hierarchy), then by last name alphabetically
usort($formattedMembers, function($a, $b) {
    // First compare by priority (lower = higher rank)
    if ($a['_priority'] !== $b['_priority']) {
        return $a['_priority'] - $b['_priority'];
    }
    // Same priority, sort by last name
    return strcasecmp($a['_last_name'], $b['_last_name']);
});

// Remove internal sorting fields before returning
$cleanedMembers = array_map(function($member) {
    unset($member['_priority'], $member['_last_name']);
    return $member;
}, $formattedMembers);

$total = count($cleanedMembers);

json_response([
    'data' => $cleanedMembers,
    'meta' => [
        'total' => $total
    ]
]);
