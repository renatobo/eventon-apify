<?php

namespace EventON_APIfy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads EventON RSVP posts and delegates public-shape mapping to a formatter.
 *
 * Query optimization can now evolve behind this boundary without changing the
 * published procedural callback API.
 */
final class RSVP_Attendee_Repository {
    /** @var RSVP_Attendee_Formatter */
    private $formatter;

    public function __construct(RSVP_Attendee_Formatter $formatter) {
        $this->formatter = $formatter;
    }

    /**
     * Return all normalized attendee records for an event.
     *
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public function find_by_event($event_id) {
        $unavailable = $this->assert_available();
        if ($unavailable) {
            return $unavailable;
        }

        $query = new \WP_Query($this->event_query_args($event_id, array('posts_per_page' => -1, 'no_found_rows' => true)));

        return array_map(array($this->formatter, 'format'), $query->posts);
    }

    /**
     * Return one page of normalized attendee records, paged in SQL.
     *
     * Same order and statuses as find_by_event(), so a page here equals the
     * same slice of the full list.
     *
     * @return array{items: array<int, array<string, mixed>>, total: int}|\WP_Error
     */
    public function find_page_by_event($event_id, $page, $per_page) {
        $unavailable = $this->assert_available();
        if ($unavailable) {
            return $unavailable;
        }

        $query = new \WP_Query($this->event_query_args($event_id, array('posts_per_page' => (int) $per_page, 'paged' => (int) $page)));
        $total = (int) $query->found_posts;

        // Core skips counting when a page comes back empty, so past the last
        // page found_posts is 0. Count separately to keep the real total.
        if (empty($query->posts) && (int) $page > 1) {
            $count = new \WP_Query($this->event_query_args($event_id, array('posts_per_page' => 1, 'paged' => 1, 'fields' => 'ids')));
            $total = (int) $count->found_posts;
        }

        return array(
            'items' => array_map(array($this->formatter, 'format'), $query->posts),
            'total' => $total,
        );
    }

    /**
     * Return the summary fields (rsvp, status, headcount, repeat_interval)
     * of every attendee, without building full attendee payloads.
     *
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public function find_summary_rows_by_event($event_id) {
        $unavailable = $this->assert_available();
        if ($unavailable) {
            return $unavailable;
        }

        $query = new \WP_Query($this->event_query_args($event_id, array('posts_per_page' => -1, 'no_found_rows' => true)));

        return array_map(array($this->formatter, 'summary_fields'), $query->posts);
    }

    /**
     * @return \WP_Error|null
     */
    private function assert_available() {
        if (eventon_apify_is_eventon_rsvp_available()) {
            return null;
        }

        return new \WP_Error(
            'eventon_apify_rsvp_missing',
            __('The EventON RSVP addon is not active or the evo-rsvp post type is unavailable.', 'eventon-apify'),
            array('status' => 404)
        );
    }

    /**
     * @param array<string, mixed> $overrides Paging arguments.
     * @return array<string, mixed>
     */
    private function event_query_args($event_id, array $overrides) {
        return array_merge(
            array(
                'post_type' => 'evo-rsvp',
                // EventON's own count sync only considers published RSVP posts.
                'post_status' => 'publish',
                'orderby' => 'ID',
                'order' => 'DESC',
                'update_post_term_cache' => false,
                'meta_query' => array(
                    array(
                        'key' => 'e_id',
                        'value' => (string) $event_id,
                        'compare' => '=',
                    ),
                ),
            ),
            $overrides
        );
    }
}
