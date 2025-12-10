<?php
/**
 * Seed events collection with sample data from events.php
 *
 * This script:
 * 1. Removes all existing events
 * 2. Inserts the sample events with proper field mapping
 *
 * Run with: php scripts/seed_events.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// MongoDB connection (same as dashboard)
$client = new MongoDB\Client('mongodb://localhost:27017');
$db = $client->selectDatabase('ococ_portal');
$events = $db->selectCollection('events');

// Sample events from the original events.php
$sampleEvents = [
  [
    'title' => 'Viva El Network',
    'date' => '2024-11-30',
    'time' => '6:00 PM – 9:00 PM',
    'location' => 'Catrinas Mexican Fusion, 925 N Semoran Blvd',
    'description' => 'Authentic networking with Latin flair. First drink and appetizers on us. No hard sell. Just conversation with 100+ business owners.',
    'price' => 'Free',
    'image' => '/images/viva-el-network.jpg',
    'eventType' => 'Networking',
    'badges' => [],
    'repeat' => 'monthly_dom', // Monthly networking event
  ],
  [
    'title' => 'Mix & Mingle at TEAK',
    'date' => '2025-01-06',
    'time' => '6:00 PM – 9:00 PM',
    'location' => 'Teak Neighborhood Grill, 6400 Time Square Ave',
    'description' => 'Our most popular monthly mixer. Complimentary drink and appetizer. 80-100 attendees from diverse industries.',
    'price' => 'Free',
    'member_quote' => 'Member quote: "I landed a $50K contract from a conversation at TEAK." — Sarah M.',
    'image' => '/images/Teak Event Image.png',
    'eventType' => 'Networking',
    'badges' => [],
    'repeat' => 'monthly_dom', // Monthly on same date
  ],
  [
    'title' => 'Mastering Government Contracting Workshop',
    'date' => '2025-01-12',
    'time' => '8:30 AM – 11:00 AM',
    'location' => 'Chamber Conference Center',
    'description' => 'Government contracts = billions in opportunities. Learn how to register, find RFPs, write winning proposals, and avoid disqualifying mistakes.',
    'price' => '$25',
    'presenter' => 'James Rodriguez, Former State Procurement Director',
    'image' => '/images/events/government-contracting-thumb.jpg',
    'eventType' => 'Workshop',
    'badges' => [['label' => 'Members Only', 'variant' => 'primary']],
    'members_only' => true,
    'repeat' => 'none',
  ],
  [
    'title' => 'State of the Region Luncheon',
    'date' => '2025-01-21',
    'time' => '11:30 AM – 1:30 PM',
    'location' => 'Rosen Shingle Creek',
    'description' => 'Economic forecast for Orlando. Job trends, infrastructure investments, emerging industries.',
    'price' => '$65',
    'keynote' => 'Tim Giuliani, CEO, Orlando Economic Partnership',
    'image' => '/images/events/state-of-region-luncheon-thumb.jpg',
    'eventType' => 'Luncheon',
    'badges' => [['label' => 'Limited Seating', 'variant' => 'warning']],
    'repeat' => 'none',
  ],
  [
    'title' => 'Business Breakfast Roundtable',
    'date' => '2025-02-05',
    'time' => '7:30 AM – 9:00 AM',
    'location' => 'Chamber Conference Center',
    'description' => 'Start your day with meaningful connections. Join fellow business leaders for coffee, breakfast, and strategic discussions.',
    'price' => 'Free',
    'eventType' => 'Networking',
    'badges' => [],
    'repeat' => 'monthly_nth', // First Wednesday of each month
    'repeat_n' => 1,
    'repeat_weekday' => 3, // Wednesday
  ],
  [
    'title' => 'Digital Marketing Masterclass',
    'date' => '2025-02-10',
    'time' => '9:00 AM – 12:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Learn the latest strategies in social media, SEO, and content marketing to grow your business online.',
    'price' => '$45',
    'presenter' => 'Marketing Experts Panel',
    'eventType' => 'Workshop',
    'badges' => [['label' => 'Members Only', 'variant' => 'primary']],
    'members_only' => true,
    'repeat' => 'none',
  ],
  [
    'title' => 'Women in Business Luncheon',
    'date' => '2025-02-15',
    'time' => '11:30 AM – 1:30 PM',
    'location' => 'Rosen Shingle Creek',
    'description' => 'Celebrating women entrepreneurs and leaders. Networking, inspiration, and empowerment.',
    'price' => '$55',
    'keynote' => 'Dr. Maria Rodriguez, CEO',
    'eventType' => 'Luncheon',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'Tech Innovation Summit',
    'date' => '2025-02-20',
    'time' => '8:00 AM – 5:00 PM',
    'location' => 'Orange County Convention Center',
    'description' => 'Explore emerging technologies, AI, and digital transformation strategies for your business.',
    'price' => '$150',
    'eventType' => 'Conference',
    'badges' => [['label' => 'Early Bird', 'variant' => 'warning']],
    'repeat' => 'none',
  ],
  [
    'title' => 'After Hours Networking',
    'date' => '2025-02-25',
    'time' => '5:30 PM – 7:30 PM',
    'location' => 'Downtown Orlando Venue',
    'description' => 'Relaxed evening networking with drinks and appetizers. Perfect for building relationships after work.',
    'price' => 'Free',
    'eventType' => 'Networking',
    'badges' => [],
    'repeat' => 'monthly_nth', // Last Tuesday of each month
    'repeat_n' => -1,
    'repeat_weekday' => 2, // Tuesday
  ],
  [
    'title' => 'Financial Planning Workshop',
    'date' => '2025-03-03',
    'time' => '10:00 AM – 12:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Learn about business financial planning, tax strategies, and investment opportunities.',
    'price' => '$35',
    'presenter' => 'Certified Financial Planners',
    'eventType' => 'Workshop',
    'badges' => [['label' => 'Members Only', 'variant' => 'primary']],
    'members_only' => true,
    'repeat' => 'none',
  ],
  [
    'title' => 'Small Business Expo',
    'date' => '2025-03-08',
    'time' => '10:00 AM – 4:00 PM',
    'location' => 'Orange County Convention Center',
    'description' => 'Showcase your business, meet potential clients, and discover new opportunities at our annual expo.',
    'price' => '$75',
    'eventType' => 'Expo',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'Leadership Development Series',
    'date' => '2025-03-12',
    'time' => '9:00 AM – 11:00 AM',
    'location' => 'Chamber Conference Center',
    'description' => 'Build your leadership skills with expert training on team management, communication, and strategic thinking.',
    'price' => '$50',
    'eventType' => 'Workshop',
    'badges' => [['label' => 'Members Only', 'variant' => 'primary']],
    'members_only' => true,
    'repeat' => 'monthly_nth', // Second Wednesday of each month
    'repeat_n' => 2,
    'repeat_weekday' => 3, // Wednesday
  ],
  [
    'title' => 'Chamber Golf Tournament',
    'date' => '2025-03-18',
    'time' => '8:00 AM – 2:00 PM',
    'location' => 'Grand Cypress Golf Club',
    'description' => 'Join us for a day of golf, networking, and fun. All skill levels welcome.',
    'price' => '$200',
    'eventType' => 'Social',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'HR & Employment Law Update',
    'date' => '2025-03-22',
    'time' => '1:00 PM – 4:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Stay current with the latest employment law changes and HR best practices.',
    'price' => '$60',
    'presenter' => 'Employment Law Attorneys',
    'eventType' => 'Workshop',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'Young Professionals Mixer',
    'date' => '2025-03-27',
    'time' => '6:00 PM – 8:00 PM',
    'location' => 'Downtown Orlando Venue',
    'description' => 'Connect with other young professionals building their careers in Orlando.',
    'price' => 'Free',
    'eventType' => 'Networking',
    'badges' => [],
    'repeat' => 'monthly_nth', // Fourth Thursday of each month
    'repeat_n' => 4,
    'repeat_weekday' => 4, // Thursday
  ],
  [
    'title' => 'Customer Service Excellence',
    'date' => '2025-04-02',
    'time' => '9:00 AM – 12:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Learn how to deliver exceptional customer service that builds loyalty and drives growth.',
    'price' => '$40',
    'eventType' => 'Workshop',
    'badges' => [['label' => 'Members Only', 'variant' => 'primary']],
    'members_only' => true,
    'repeat' => 'none',
  ],
  [
    'title' => 'Chamber Annual Meeting',
    'date' => '2025-04-10',
    'time' => '11:00 AM – 2:00 PM',
    'location' => 'Rosen Shingle Creek',
    'description' => 'Join us for our annual meeting featuring board elections, year in review, and member recognition.',
    'price' => 'Free',
    'eventType' => 'Meeting',
    'badges' => [['label' => 'Members Only', 'variant' => 'primary']],
    'members_only' => true,
    'repeat' => 'none',
  ],
  [
    'title' => 'Real Estate Investment Forum',
    'date' => '2025-04-15',
    'time' => '6:00 PM – 8:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Expert insights on commercial and residential real estate investment opportunities in Central Florida.',
    'price' => '$30',
    'eventType' => 'Forum',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'Health & Wellness Expo',
    'date' => '2025-04-20',
    'time' => '10:00 AM – 3:00 PM',
    'location' => 'Orange County Convention Center',
    'description' => 'Discover health and wellness solutions for your employees and business.',
    'price' => '$45',
    'eventType' => 'Expo',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'International Business Forum',
    'date' => '2025-04-25',
    'time' => '9:00 AM – 12:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Explore global business opportunities, export strategies, and international partnerships.',
    'price' => '$55',
    'eventType' => 'Forum',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'Chamber Night at the Ballpark',
    'date' => '2025-05-01',
    'time' => '6:30 PM – 10:00 PM',
    'location' => 'Tropicana Field',
    'description' => 'Join fellow members for a night of baseball, networking, and community spirit.',
    'price' => '$35',
    'eventType' => 'Social',
    'badges' => [],
    'repeat' => 'none',
  ],
  [
    'title' => 'E-Commerce Strategies Workshop',
    'date' => '2025-05-08',
    'time' => '1:00 PM – 4:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Learn how to build and grow your online store with proven e-commerce strategies.',
    'price' => '$50',
    'eventType' => 'Workshop',
    'badges' => [['label' => 'Members Only', 'variant' => 'primary']],
    'members_only' => true,
    'repeat' => 'none',
  ],
  [
    'title' => 'Chamber Awards Gala',
    'date' => '2025-05-15',
    'time' => '6:00 PM – 11:00 PM',
    'location' => 'Rosen Shingle Creek',
    'description' => 'Celebrate outstanding businesses and leaders in our community at this prestigious awards ceremony.',
    'price' => '$150',
    'eventType' => 'Gala',
    'badges' => [['label' => 'Black Tie', 'variant' => 'warning']],
    'repeat' => 'none',
  ],
  [
    'title' => 'Startup Pitch Competition',
    'date' => '2025-05-22',
    'time' => '5:00 PM – 8:00 PM',
    'location' => 'Chamber Conference Center',
    'description' => 'Watch local startups pitch their ideas to a panel of investors and business leaders.',
    'price' => 'Free',
    'eventType' => 'Competition',
    'badges' => [],
    'repeat' => 'none',
  ],
];

/**
 * Parse time string like "6:00 PM – 9:00 PM" to extract start time in 24h format
 * and calculate hours duration
 */
function parseTimeRange($timeStr) {
  // Try to match "HH:MM AM/PM – HH:MM AM/PM" or "HH:MM AM/PM"
  // Note: handles both en-dash (–), em-dash (—), and regular hyphen (-)
  if (preg_match('/(\d{1,2}):(\d{2})\s*(AM|PM)(?:\s*[–—-]\s*(\d{1,2}):(\d{2})\s*(AM|PM))?/iu', $timeStr, $m)) {
    $startHour = (int)$m[1];
    $startMin = (int)$m[2];
    $startAmPm = strtoupper($m[3]);

    // Convert to 24h
    if ($startAmPm === 'PM' && $startHour !== 12) $startHour += 12;
    if ($startAmPm === 'AM' && $startHour === 12) $startHour = 0;

    $time24 = sprintf('%02d:%02d', $startHour, $startMin);

    // Calculate hours if end time provided
    $hours = 2; // default
    if (isset($m[4])) {
      $endHour = (int)$m[4];
      $endMin = (int)$m[5];
      $endAmPm = strtoupper($m[6]);

      if ($endAmPm === 'PM' && $endHour !== 12) $endHour += 12;
      if ($endAmPm === 'AM' && $endHour === 12) $endHour = 0;

      $startMins = $startHour * 60 + $startMin;
      $endMins = $endHour * 60 + $endMin;
      $hours = ($endMins - $startMins) / 60;
      if ($hours < 0) $hours += 24; // handle overnight
    }

    return ['time' => $time24, 'hours' => $hours];
  }

  return ['time' => '09:00', 'hours' => 2]; // fallback
}

// Confirm before running
echo "This will DELETE all existing events and insert " . count($sampleEvents) . " sample events.\n";
echo "Continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) !== 'yes') {
  echo "Aborted.\n";
  exit(1);
}
fclose($handle);

// Delete existing events
$deleteResult = $events->deleteMany([]);
echo "Deleted {$deleteResult->getDeletedCount()} existing events.\n";

// Insert new events
$inserted = 0;
foreach ($sampleEvents as $event) {
  // Parse time
  $timeInfo = parseTimeRange($event['time']);

  // Build document matching MongoDB/admin schema
  $doc = [
    'title' => $event['title'],
    'host' => 'Orlando Chamber of Commerce', // Default host
    'location' => $event['location'],
    'date' => new MongoDB\BSON\UTCDateTime(strtotime($event['date']) * 1000),
    'time' => $timeInfo['time'],
    'hours' => $timeInfo['hours'],
    'description' => $event['description'],
    'status' => 'approved',
    'repeat' => $event['repeat'] ?? 'none',
    'members_only' => $event['members_only'] ?? false,
    'notice_enabled' => false,
    'notice_lead' => 0,
    'created_at' => new MongoDB\BSON\UTCDateTime(),
    // New fields from sample data
    'price' => $event['price'] ?? null,
    'eventType' => $event['eventType'] ?? null,
    'badges' => $event['badges'] ?? [],
  ];

  // Add optional fields only if they exist
  if (!empty($event['image'])) $doc['image'] = $event['image'];
  if (isset($event['presenter'])) $doc['presenter'] = $event['presenter'];
  if (isset($event['keynote'])) $doc['keynote'] = $event['keynote'];
  if (isset($event['member_quote'])) $doc['member_quote'] = $event['member_quote'];
  if (isset($event['repeat_n'])) $doc['repeat_n'] = $event['repeat_n'];
  if (isset($event['repeat_weekday'])) $doc['repeat_weekday'] = $event['repeat_weekday'];

  $events->insertOne($doc);
  $inserted++;
  echo "  Inserted: {$event['title']}\n";
}

echo "\nDone! Inserted $inserted events.\n";

// Verify
$count = $events->countDocuments([]);
echo "Total events in collection: $count\n";
