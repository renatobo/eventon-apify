<?php
/**
 * Tests for the pure RSVP list helpers extracted from get_event_rsvps
 * (rest-rsvp.php): filtering and pagination.
 */

function eventon_test_attendees() {
    return array(
        array('id' => 1, 'rsvp' => 'yes', 'status' => 'confirmed', 'full_name' => 'Jane Doe', 'email' => 'jane@example.com'),
        array('id' => 2, 'rsvp' => 'no',  'status' => 'declined',  'full_name' => 'John Roe', 'email' => 'john@example.com'),
        array('id' => 3, 'rsvp' => 'yes', 'status' => 'pending',   'full_name' => 'Amy Poe',  'email' => 'amy@example.com'),
    );
}

test('rsvp filter "all" keeps everyone', function () {
    eq(count(eventon_apify_filter_rsvp_attendees(eventon_test_attendees(), 'all', '', '')), 3);
});

test('rsvp filter selects an exact response', function () {
    $result = eventon_apify_filter_rsvp_attendees(eventon_test_attendees(), 'yes', '', '');
    eq(count($result), 2);
    eq($result[0]['id'], 1);
    eq($result[1]['id'], 3);
});

test('status filter matches case-insensitively', function () {
    $result = eventon_apify_filter_rsvp_attendees(eventon_test_attendees(), 'all', 'pending', '');
    eq(count($result), 1);
    eq($result[0]['id'], 3);
});

test('search filter matches across attendee fields', function () {
    $result = eventon_apify_filter_rsvp_attendees(eventon_test_attendees(), 'all', '', 'jane');
    eq(count($result), 1);
    eq($result[0]['id'], 1);
});

test('filters compose (rsvp + search)', function () {
    $result = eventon_apify_filter_rsvp_attendees(eventon_test_attendees(), 'yes', '', 'amy');
    eq(count($result), 1);
    eq($result[0]['id'], 3);
});

test('filter reindexes results', function () {
    $result = eventon_apify_filter_rsvp_attendees(eventon_test_attendees(), 'no', '', '');
    eq(array_keys($result), array(0));
});

test('paginate returns the page slice and metadata', function () {
    $items = array('a', 'b', 'c', 'd', 'e');
    $p1 = eventon_apify_paginate_list($items, 1, 2);
    eq($p1['total'], 5);
    eq($p1['pages'], 3);
    eq($p1['page'], 1);
    eq($p1['per_page'], 2);
    eq($p1['items'], array('a', 'b'));

    $p3 = eventon_apify_paginate_list($items, 3, 2);
    eq($p3['items'], array('e'));
});

test('paginate reports zero pages for an empty list', function () {
    $p = eventon_apify_paginate_list(array(), 1, 20);
    eq($p['total'], 0);
    eq($p['pages'], 0);
    eq($p['items'], array());
});
