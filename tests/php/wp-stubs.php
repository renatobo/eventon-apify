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
$GLOBALS['__eventon_test_actions'] = array();
$GLOBALS['__eventon_test_filters'] = array();
$GLOBALS['__eventon_test_object_taxonomies'] = array();
$GLOBALS['__eventon_test_post_type_by_id'] = array();
$GLOBALS['__eventon_test_get_posts_args'] = array();
$GLOBALS['__eventon_test_get_posts_result'] = array();
$GLOBALS['__eventon_test_deleted_posts'] = array();
$GLOBALS['__eventon_test_routes'] = array();

/**
 * Reset all in-memory WordPress state between tests.
 */
function eventon_test_reset_wp_state() {
    $GLOBALS['__eventon_test_options'] = array();
    $GLOBALS['__eventon_test_post_meta'] = array();
    $GLOBALS['__eventon_test_post_types'] = array('ajde_events' => true);
    $GLOBALS['__eventon_test_can'] = false;
    $GLOBALS['__eventon_test_actions'] = array();
    $GLOBALS['__eventon_test_filters'] = array();
    $GLOBALS['__eventon_test_object_taxonomies'] = array();
    $GLOBALS['__eventon_test_post_type_by_id'] = array();
    $GLOBALS['__eventon_test_get_posts_args'] = array();
    $GLOBALS['__eventon_test_get_posts_result'] = array();
    $GLOBALS['__eventon_test_deleted_posts'] = array();
    $GLOBALS['__eventon_test_routes'] = array();
    $GLOBALS['eventon_apify_wp_v2_exposed'] = array();
    $GLOBALS['__eventon_test_posts'] = array();
    $GLOBALS['__eventon_test_wp_update_post_result'] = null;
    $GLOBALS['__eventon_test_multisite'] = false;
    $GLOBALS['__eventon_test_sites'] = array(1);
    $GLOBALS['__eventon_test_current_blog'] = 1;
    $GLOBALS['__eventon_test_blog_stack'] = array();
    $GLOBALS['__eventon_test_cleanup_log'] = array();
    $GLOBALS['__eventon_test_wp_update_post_calls'] = array();
    $GLOBALS['__eventon_test_fired_actions'] = array();
    $GLOBALS['__eventon_test_filter_callbacks'] = array();
    $GLOBALS['__eventon_test_trashed_posts'] = array();
    $GLOBALS['__eventon_test_wp_trash_post_result'] = null;
    $GLOBALS['__eventon_test_wp_query_args'] = array();
    $GLOBALS['__eventon_test_wp_query_posts'] = array();
    $GLOBALS['__eventon_test_option_reads'] = array();
    $GLOBALS['__eventon_test_option_autoload'] = array();
    $GLOBALS['eventon_apify_pending_rsvp_touches'] = array();
}

/**
 * Attach a callback that the apply_filters() stub will actually run.
 *
 * Kept apart from add_filter(), which only records registrations for the
 * composition-root tests and must not start executing plugin callbacks.
 */
function eventon_test_add_filter_callback($tag, callable $callback) {
    $GLOBALS['__eventon_test_filter_callbacks'][$tag][] = $callback;
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
        public function get_error_data() {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        /** @var array<string, mixed> */
        private $params;

        /** @var string */
        private $route;

        /**
         * @param array<string, mixed> $params Request parameters.
         * @param string               $route  Raw client route, as core reports it.
         */
        public function __construct(array $params = array(), $route = '') {
            $this->params = $params;
            $this->route = (string) $route;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }

        public function has_param($key) {
            return array_key_exists($key, $this->params);
        }

        public function get_route() {
            return $this->route;
        }
    }
}

if (!class_exists('WP_HTTP_Response')) {
    class WP_HTTP_Response {
        /** @var mixed */
        private $data;

        /**
         * @param mixed $data Response payload.
         */
        public function __construct($data = null) {
            $this->data = $data;
        }

        public function get_data() {
            return $this->data;
        }

        public function set_data($data) {
            $this->data = $data;
        }
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
        $GLOBALS['__eventon_test_option_reads'][] = $name;
        return array_key_exists($name, $GLOBALS['__eventon_test_options'])
            ? $GLOBALS['__eventon_test_options'][$name]
            : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null) {
        if ($autoload !== null) {
            $GLOBALS['__eventon_test_option_autoload'][$name] = (bool) $autoload;
        }
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
    function apply_filters($tag, $value, ...$args) {
        foreach ($GLOBALS['__eventon_test_filter_callbacks'][$tag] ?? array() as $callback) {
            $value = $callback($value, ...$args);
        }
        return $value;
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['__eventon_test_actions'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        $GLOBALS['__eventon_test_filters'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
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

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($value) {
        if (is_string($value)) {
            $trimmed = trim($value);
            // Mirrors WordPress: only serialized strings are unserialized.
            if (preg_match('/^[aOsbdi]:|^N;$/', $trimmed)) {
                $result = @unserialize($trimmed);
                return $result === false && $trimmed !== 'b:0;' ? $value : $result;
            }
        }

        return $value;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value) {
        return json_encode($value);
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var((string) $email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
}

if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null, $timezone = null) {
        $datetime = new DateTimeImmutable('@' . (int) $timestamp);

        if ($timezone instanceof DateTimeZone) {
            $datetime = $datetime->setTimezone($timezone);
        }

        return $datetime->format($format);
    }
}

if (!function_exists('get_object_taxonomies')) {
    function get_object_taxonomies($object_type, $output = 'names') {
        return $GLOBALS['__eventon_test_object_taxonomies'];
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post_id) {
        return $GLOBALS['__eventon_test_post_type_by_id'][$post_id] ?? false;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = array()) {
        $GLOBALS['__eventon_test_get_posts_args'][] = $args;
        return $GLOBALS['__eventon_test_get_posts_result'];
    }
}

if (!function_exists('wp_delete_post')) {
    function wp_delete_post($post_id, $force_delete = false) {
        $GLOBALS['__eventon_test_deleted_posts'][] = array($post_id, $force_delete);
        return true;
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
        const EDITABLE = 'POST, PUT, PATCH';
        const DELETABLE = 'DELETE';
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($route_namespace, $route, $args = array(), $override = false) {
        $GLOBALS['__eventon_test_routes'][$route_namespace . '/' . ltrim($route, '/')][] = $args;
        return true;
    }
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!function_exists('get_post')) {
    function get_post($post_id, $output = 'OBJECT') {
        $post = $GLOBALS['__eventon_test_posts'][(int) $post_id] ?? null;
        return ($post !== null && $output === ARRAY_A) ? (array) $post : $post;
    }
}

if (!function_exists('wp_update_post')) {
    function wp_update_post($postarr, $wp_error = false) {
        $GLOBALS['__eventon_test_wp_update_post_calls'][] = $postarr;
        return $GLOBALS['__eventon_test_wp_update_post_result'] ?? (int) ($postarr['ID'] ?? 0);
    }
}

if (!function_exists('delete_option')) {
    function delete_option($name) {
        $GLOBALS['__eventon_test_cleanup_log'][] = array($GLOBALS['__eventon_test_current_blog'], 'option:' . $name);
        unset($GLOBALS['__eventon_test_options'][$name]);
        return true;
    }
}

if (!function_exists('delete_post_meta_by_key')) {
    function delete_post_meta_by_key($key) {
        $GLOBALS['__eventon_test_cleanup_log'][] = array($GLOBALS['__eventon_test_current_blog'], 'meta:' . $key);
        return true;
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite() {
        return (bool) $GLOBALS['__eventon_test_multisite'];
    }
}

if (!function_exists('get_sites')) {
    function get_sites($args = array()) {
        return $GLOBALS['__eventon_test_sites'];
    }
}

if (!function_exists('switch_to_blog')) {
    function switch_to_blog($blog_id) {
        $GLOBALS['__eventon_test_blog_stack'][] = $GLOBALS['__eventon_test_current_blog'];
        $GLOBALS['__eventon_test_current_blog'] = (int) $blog_id;
        return true;
    }
}

if (!function_exists('restore_current_blog')) {
    function restore_current_blog() {
        $GLOBALS['__eventon_test_current_blog'] = array_pop($GLOBALS['__eventon_test_blog_stack']) ?? 1;
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($tag, ...$args) {
        $GLOBALS['__eventon_test_fired_actions'][] = array($tag, $args);
    }
}

if (!function_exists('wp_trash_post')) {
    function wp_trash_post($post_id) {
        if ($GLOBALS['__eventon_test_wp_trash_post_result'] === false) {
            return false;
        }
        $GLOBALS['__eventon_test_trashed_posts'][] = (int) $post_id;
        return $GLOBALS['__eventon_test_posts'][(int) $post_id] ?? true;
    }
}

if (!function_exists('rest_ensure_response')) {
    function rest_ensure_response($response) {
        return $response;
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID = 0;
        public $post_type = 'post';
        public $post_title = '';
        public $post_status = 'publish';
        public $post_date_gmt = '';
        public $post_modified_gmt = '';

        public function __construct(array $fields = array()) {
            foreach ($fields as $name => $value) {
                $this->$name = $value;
            }
        }
    }
}

if (!class_exists('WP_Query')) {
    /**
     * Pages $GLOBALS['__eventon_test_wp_query_posts'] the way core does for
     * the args the plugin uses (ID order, posts_per_page, paged, fields ids),
     * including core's found_posts of 0 when a page past the end is empty.
     */
    class WP_Query {
        public $posts = array();
        public $found_posts = 0;
        public $max_num_pages = 0;

        public function __construct(array $args = array()) {
            $GLOBALS['__eventon_test_wp_query_args'][] = $args;

            $posts = array_values($GLOBALS['__eventon_test_wp_query_posts']);
            usort($posts, static function ($left, $right) use ($args) {
                $order = strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 1 : -1;
                return $order * ($left->ID <=> $right->ID);
            });

            $per_page = (int) ($args['posts_per_page'] ?? 10);
            $page = max(1, (int) ($args['paged'] ?? 1));
            $slice = $per_page < 0 ? $posts : array_slice($posts, ($page - 1) * $per_page, $per_page);

            $this->posts = ($args['fields'] ?? '') === 'ids'
                ? array_map(static function ($post) { return $post->ID; }, $slice)
                : $slice;

            if (empty($args['no_found_rows']) && !empty($this->posts)) {
                $this->found_posts = count($posts);
                $this->max_num_pages = $per_page > 0 ? (int) ceil($this->found_posts / $per_page) : 1;
            }
        }
    }
}

if (!function_exists('get_post_time')) {
    function get_post_time($format = 'U', $gmt = false, $post = null) {
        return $post instanceof WP_Post && $post->post_date_gmt !== '' ? strtotime($post->post_date_gmt . ' UTC') : false;
    }
}

if (!function_exists('get_post_modified_time')) {
    function get_post_modified_time($format = 'U', $gmt = false, $post = null) {
        return $post instanceof WP_Post && $post->post_modified_gmt !== '' ? strtotime($post->post_modified_gmt . ' UTC') : false;
    }
}

if (!function_exists('wp_is_post_revision')) {
    function wp_is_post_revision($post) {
        return false;
    }
}
