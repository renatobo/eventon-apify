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
        if (!eventon_apify_is_eventon_rsvp_available()) {
            return new \WP_Error(
                'eventon_apify_rsvp_missing',
                __('The EventON RSVP addon is not active or the evo-rsvp post type is unavailable.', 'eventon-apify'),
                array('status' => 404)
            );
        }

        $query = new \WP_Query(
            array(
                'post_type' => 'evo-rsvp',
                'post_status' => array('publish', 'private', 'draft'),
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'DESC',
                'no_found_rows' => true,
                'update_post_term_cache' => false,
                'meta_query' => array(
                    array(
                        'key' => 'e_id',
                        'value' => (string) $event_id,
                        'compare' => '=',
                    ),
                ),
            )
        );

        return array_map(array($this->formatter, 'format'), $query->posts);
    }
}
