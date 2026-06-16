<?php
/**
 * Tests for the meta-writing helpers extracted from save_event_meta
 * (rest-event-meta.php): text-meta and flag-meta map loops, plus the
 * shared update_or_delete_meta / update_yes_no_meta primitives.
 */

test('text meta writes sanitized values and maps request keys to meta keys', function () {
    eventon_apify_save_event_text_meta(7, array(
        'event_subtitle' => '  High Altitude  ',
        'seo_offer_price' => '25',
    ));
    eq(get_post_meta(7, 'evcal_subtitle', true), 'High Altitude');
    eq(get_post_meta(7, '_seo_offer_price', true), '25');
});

test('text meta escapes URL-bearing keys with esc_url_raw', function () {
    eventon_apify_save_event_text_meta(7, array('learn_more_link' => '  https://example.com/x  '));
    eq(get_post_meta(7, 'evcal_lmlink', true), 'https://example.com/x');
});

test('text meta deletes the key when the value is empty', function () {
    update_post_meta(7, 'evcal_subtitle', 'existing');
    eventon_apify_save_event_text_meta(7, array('event_subtitle' => ''));
    eq(get_post_meta(7, 'evcal_subtitle', true), '');
});

test('text meta ignores absent request keys', function () {
    update_post_meta(7, 'evcal_subtitle', 'keep');
    eventon_apify_save_event_text_meta(7, array('event_excerpt' => 'new'));
    eq(get_post_meta(7, 'evcal_subtitle', true), 'keep');
    eq(get_post_meta(7, 'evo_excerpt', true), 'new');
});

test('flag meta writes yes/no for mapped keys', function () {
    eventon_apify_save_event_flag_meta(7, array(
        'featured' => true,
        'completed' => false,
        'generate_gmap' => 'yes',
    ));
    eq(get_post_meta(7, '_featured', true), 'yes');
    eq(get_post_meta(7, '_completed', true), 'no');
    eq(get_post_meta(7, 'evcal_gmap_gen', true), 'yes');
});

test('flag meta ignores absent keys', function () {
    eventon_apify_save_event_flag_meta(7, array('featured' => true));
    eq(get_post_meta(7, '_completed', true), '');
});
