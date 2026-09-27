<?php

test('location images validate image attachments and a maximum of two', function () {
    $GLOBALS['__eventon_test_posts'][11] = new WP_Post(array('ID' => 11, 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg'));
    $GLOBALS['__eventon_test_posts'][12] = new WP_Post(array('ID' => 12, 'post_type' => 'attachment', 'post_mime_type' => 'image/png'));
    $GLOBALS['__eventon_test_posts'][13] = new WP_Post(array('ID' => 13, 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf'));

    eq(eventon_apify_validate_location_image_ids(11), array(11));
    eq(eventon_apify_validate_location_image_ids(array(11, 12)), array(11, 12));
    eq(eventon_apify_validate_location_image_ids(array()), array());
    foreach (array(array(11, 12, 11), array(13), array(99), array(0), array('11'), '11', array(11, 11)) as $bad) {
        ok(is_wp_error(eventon_apify_validate_location_image_ids($bad)));
        $params = eventon_apify_normalize_request_payload(array('location' => array('image_ids' => $bad)));
        eq(eventon_apify_validate_event_payload($params, false)->get_error_code(), 'eventon_apify_invalid_location_images');
    }
});

test('location images set replace clear and read back from EventON term meta', function () {
    eventon_test_set_current_user_can(true);
    $GLOBALS['__eventon_test_posts'][11] = new WP_Post(array('ID' => 11, 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg'));
    $GLOBALS['__eventon_test_posts'][12] = new WP_Post(array('ID' => 12, 'post_type' => 'attachment', 'post_mime_type' => 'image/png'));
    $GLOBALS['__eventon_test_attachment_urls'][11] = 'https://example.test/11.jpg';
    $GLOBALS['__eventon_test_attachment_urls'][12] = 'https://example.test/12.png';
    $term = new WP_Term(array('term_id' => 7, 'name' => 'Venue', 'slug' => 'venue', 'description' => ''));
    $GLOBALS['__eventon_test_term_objects']['event_location'][7] = $term;
    $GLOBALS['__eventon_test_terms'][100]['event_location'] = array($term);

    foreach (array(array(array(11, 12), array(11, 12)), array(12, array(12)), array(array(), array())) as $case) {
        list($input, $ids) = $case;
        $params = eventon_apify_normalize_request_payload(array('location' => array('image_ids' => $input)));
        eq($params['location_image_ids'], $input);
        eq(eventon_apify_sync_location_term(100, $params), true);
        $stored = eventon_apify_get_term_meta_payload('event_location', 7);
        eq($stored['evo_loc_img'] ?? '', implode(',', $ids));
        $read = eventon_apify_get_location_payload(100, array());
        eq($read['image_ids'], $ids);
        eq(array_column($read['images'], 'id'), $ids);
    }
});
