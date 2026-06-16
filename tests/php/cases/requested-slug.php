<?php
/**
 * Tests for eventon_apify_apply_requested_slug (rest-events-write.php), which
 * maps a top-level REST `slug` to the WordPress `post_name` on create/update.
 * Addresses issue #4.
 */

test('a sanitized slug is applied as post_name', function () {
    $result = eventon_apify_apply_requested_slug(
        array('post_type' => 'ajde_events'),
        array('slug' => 'my-custom-event-slug')
    );
    eq($result['post_name'], 'my-custom-event-slug');
});

test('slug values are slugified before use', function () {
    $result = eventon_apify_apply_requested_slug(array(), array('slug' => 'My Event Title'));
    eq($result['post_name'], 'my-event-title');
});

test('an absent slug leaves the post array untouched', function () {
    $postarr = array('ID' => 12, 'post_title' => 'X');
    eq(eventon_apify_apply_requested_slug($postarr, array('title' => 'X')), $postarr);
});

test('a blank slug does not set post_name (WordPress keeps its own)', function () {
    $result = eventon_apify_apply_requested_slug(array('ID' => 12), array('slug' => '   '));
    ok(!array_key_exists('post_name', $result), 'blank slug must not set post_name');
});
