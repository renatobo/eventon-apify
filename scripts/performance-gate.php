<?php
/**
 * Guard the in-memory RSVP filtering/pagination helpers against regressions.
 *
 * This is intentionally deterministic and dependency-free. Database-scale
 * performance remains the responsibility of the WordPress integration suite.
 */

require dirname(__DIR__) . '/tests/php/bootstrap.php';

$attendee_count = 10000;
$attendees = array();

for ($index = 1; $index <= $attendee_count; $index++) {
    $attendees[] = array(
        'id' => $index,
        'first_name' => 'Rider',
        'last_name' => (string) $index,
        'full_name' => 'Rider ' . $index,
        'email' => 'rider' . $index . '@example.com',
        'phone' => '555-' . str_pad((string) $index, 4, '0', STR_PAD_LEFT),
        'rsvp' => $index % 3 === 0 ? 'yes' : 'no',
        'status' => $index % 2 === 0 ? 'checked' : 'pending',
        'custom_fields' => array('chapter' => 'west'),
    );
}

$started_at = hrtime(true);
$filtered = eventon_apify_filter_rsvp_attendees($attendees, 'yes', 'checked', 'rider');
$page = eventon_apify_paginate_list($filtered, 1, 100);
$duration_ms = (hrtime(true) - $started_at) / 1000000;
$peak_mb = memory_get_peak_usage(true) / 1048576;
$maximum_ms = (float) (getenv('EVENTON_APIFY_MAX_HELPER_MS') ?: 750);
$maximum_mb = (float) (getenv('EVENTON_APIFY_MAX_HELPER_MB') ?: 96);

printf(
    "RSVP helper budget: %.2f ms, %.2f MiB peak, %d matches, %d page items\n",
    $duration_ms,
    $peak_mb,
    $page['total'],
    count($page['items'])
);

if ($page['total'] !== 1666 || count($page['items']) !== 100) {
    fwrite(STDERR, "Performance fixture produced an unexpected result.\n");
    exit(1);
}

if ($duration_ms > $maximum_ms || $peak_mb > $maximum_mb) {
    fwrite(STDERR, "RSVP helper performance budget exceeded.\n");
    exit(1);
}
