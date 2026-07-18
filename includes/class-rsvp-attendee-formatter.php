<?php

namespace EventON_APIfy;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Maps EventON RSVP posts and metadata to the stable public API shape.
 */
final class RSVP_Attendee_Formatter {
    /**
     * Format an RSVP attendee record into a stable API payload.
     *
     * @return array<string, mixed>
     */
    public function format(\WP_Post $post) {
        $meta = get_post_meta($post->ID);
        $rsvp_object = class_exists('EVO_RSVP_CPT') ? new \EVO_RSVP_CPT($post->ID) : null;
        $first_name = trim((string) eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('first_name'), array('first_name')));
        $last_name = trim((string) eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('last_name'), array('last_name')));
        $email = trim((string) eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('email'), array('email')));
        $phone = trim((string) eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array(), array('phone')));
        $count = absint(eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('count'), array('count')));
        $event_id = absint(eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('event_id'), array('e_id')));
        $repeat_interval = absint(eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('repeat_interval'), array('repeat_interval')));

        if ($count < 1) {
            $count = 1;
        }

        $rsvp_value = eventon_apify_normalize_rsvp_response(
            eventon_apify_get_rsvp_field_value(
                $rsvp_object,
                $meta,
                array('get_rsvp_status'),
                array('rsvp')
            )
        );
        $status = strtolower(trim((string) eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('checkin_status'), array('status'))));
        $rsvp_type = strtolower(trim((string) eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('get_rsvp_type'), array('rsvp_type'))));
        $other_attendees = eventon_apify_normalize_rsvp_other_attendees(
            eventon_apify_get_rsvp_field_value(
                $rsvp_object,
                $meta,
                array('get_names'),
                array('names')
            )
        );
        $email_updates_value = eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('get_updates'), array('updates'));
        $full_name = trim((string) eventon_apify_get_rsvp_field_value($rsvp_object, $meta, array('full_name'), array()));

        if ($full_name === '') {
            $full_name = trim((string) $post->post_title);
        }

        return array(
            'id' => $post->ID,
            'created_at' => eventon_apify_get_post_created_at_iso8601($post),
            'updated_at' => eventon_apify_get_rsvp_updated_at_iso8601($post),
            'first_name' => $first_name,
            'last_name' => $last_name,
            'full_name' => $full_name,
            'email' => $email,
            'phone' => $phone,
            'email_updates' => eventon_apify_is_yes($email_updates_value),
            'rsvp' => $rsvp_value,
            'status' => $status,
            'rsvp_type' => $rsvp_type,
            'count' => $count,
            'event_time' => eventon_apify_get_rsvp_event_time($event_id, $repeat_interval),
            'other_attendees' => $other_attendees,
            'custom_fields' => eventon_apify_get_rsvp_custom_fields($meta),
        );
    }
}
