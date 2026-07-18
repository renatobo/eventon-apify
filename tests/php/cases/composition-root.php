<?php

test('composition root registers runtime integrations once', function () {
    \EventON_APIfy\Plugin::boot();
    \EventON_APIfy\Plugin::boot();

    eq($GLOBALS['__eventon_test_actions']['rest_api_init'], array(
        'eventon_apify_register_routes',
        'eventon_apify_register_wp_v2_compatibility_fields',
    ));
    eq(count($GLOBALS['__eventon_test_filters']['rest_pre_dispatch']), 1);
});
