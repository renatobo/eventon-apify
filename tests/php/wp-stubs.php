<?php
/**
 * Minimal WordPress function/class doubles for unit testing plugin logic
 * without a database or a WordPress install.
 *
 * Only the surface the plugin actually calls is implemented, with simple,
 * predictable behavior. In-memory state (options, post meta, current-user
 * capability) is resettable via eventon_test_reset_wp_state().
 */

$GLOBALS['__eventon_test_options'] = array();
$GLOBALS['__eventon_test_post_meta'] = array();
$GLOBALS['__eventon_test_post_types'] = array();
$GLOBALS['__eventon_test_can'] = false;

/**
 * Reset all in-memory WordPress state between tests.
 */
function eventon_test_reset_wp_state() {
    $GLOBALS['__eventon_test_options'] = array();
    $GLOBALS['__eventon_test_post_meta'] = array();
    $GLOBALS['__eventon_test_post_types'] = array('ajde_events' => true);
    $GLOBALS['__eventon_test_can'] = false;
}

/**
 * Toggle what current_user_can() returns for the next assertions.
 */
function eventon_test_set_current_user_can($can) {
    $GLOBALS['__eventon_test_can'] = (bool) $can;
}

eventon_test_reset_wp_state();

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $code;
        public $message;
        public $data;
        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }
        public function get_error_code() {
            return $this->code;
        }
        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return $text;
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return $text;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return $url;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return trim((string) $url);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(preg_replace('/[\r\n\t ]+/', ' ', (string) $value));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return trim((string) $value);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($value) {
        $value = strtolower((string) $value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim($value, '-');
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($value) {
        return (string) $value;
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!function_exists('rest_authorization_required_code')) {
    function rest_authorization_required_code() {
        return 401;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return (bool) $GLOBALS['__eventon_test_can'];
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return array_key_exists($name, $GLOBALS['__eventon_test_options'])
            ? $GLOBALS['__eventon_test_options'][$name]
            : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null) {
        $GLOBALS['__eventon_test_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($name, $value = '', $deprecated = '', $autoload = 'yes') {
        $GLOBALS['__eventon_test_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        $store = $GLOBALS['__eventon_test_post_meta'][$post_id] ?? array();
        if ($key === '') {
            return $store;
        }
        if (!array_key_exists($key, $store)) {
            return $single ? '' : array();
        }
        return $single ? $store[$key] : array($store[$key]);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        $GLOBALS['__eventon_test_post_meta'][$post_id][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($post_id, $key) {
        unset($GLOBALS['__eventon_test_post_meta'][$post_id][$key]);
        return true;
    }
}

if (!function_exists('post_type_exists')) {
    function post_type_exists($post_type) {
        return !empty($GLOBALS['__eventon_test_post_types'][$post_type]);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string() {
        return 'UTC';
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}
