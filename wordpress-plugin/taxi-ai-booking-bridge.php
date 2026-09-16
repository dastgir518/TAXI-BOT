<?php
/*
Plugin Name: Taxi AI Booking Bridge
Description: Adds secure AI booking REST endpoints that integrate with the existing Taxi Booking Engine without modifying it.
Version: 0.1.0
Author: Taxi AI Booking Bot
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit;
}

define('TAXI_AI_BOOKING_BRIDGE_VERSION', '0.1.0');

function taxi_ai_bridge_get_secret() {
    if (defined('TAXI_AI_BOOKING_SECRET') && TAXI_AI_BOOKING_SECRET) {
        return TAXI_AI_BOOKING_SECRET;
    }

    if (defined('TBE_AI_SECRET') && TBE_AI_SECRET) {
        return TBE_AI_SECRET;
    }

    return get_option('taxi_ai_booking_secret', '');
}

function taxi_ai_bridge_authorize_request(WP_REST_Request $request) {
    $expected = taxi_ai_bridge_get_secret();
    $provided = $request->get_header('x-tbe-ai-secret');

    if (!$expected || !$provided || !hash_equals($expected, $provided)) {
        return new WP_Error('taxi_ai_forbidden', 'Invalid AI booking secret.', array('status' => 403));
    }

    return true;
}

function taxi_ai_bridge_meta_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'taxi_ai_booking_meta';
}

function taxi_ai_bridge_activate() {
    global $wpdb;

    $table_name = taxi_ai_bridge_meta_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        tbe_booking_id bigint(20) unsigned NOT NULL,
        source_site varchar(100) DEFAULT NULL,
        ai_session_id varchar(100) DEFAULT NULL,
        supabase_booking_id varchar(100) DEFAULT NULL,
        ai_summary text,
        telegram_sent tinyint(1) NOT NULL DEFAULT 0,
        telegram_sent_at datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tbe_booking_id (tbe_booking_id),
        KEY ai_session_id (ai_session_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'taxi_ai_bridge_activate');

function taxi_ai_bridge_tbe_table_exists() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'tbe_bookings';
    return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
}

function taxi_ai_bridge_sanitize_booking_payload($payload) {
    $vehicle_id = isset($payload['vehicle_id']) ? intval($payload['vehicle_id']) : 0;
    $price = $vehicle_id ? get_post_meta($vehicle_id, '_price', true) : '';

    return array(
        'booking' => array(
            'user_id' => 0,
            'booking_date' => sanitize_text_field($payload['booking_date'] ?? ''),
            'booking_time' => sanitize_text_field($payload['booking_time'] ?? ''),
            'pickup_location' => sanitize_text_field($payload['pickup_location'] ?? ''),
            'via_location' => sanitize_text_field($payload['via_location'] ?? ''),
            'dropoff_location' => sanitize_text_field($payload['dropoff_location'] ?? ''),
            'is_return_journey' => !empty($payload['is_return_journey']) ? 1 : 0,
            'return_date' => sanitize_text_field($payload['return_date'] ?? ''),
            'return_time' => sanitize_text_field($payload['return_time'] ?? ''),
            'return_pickup_location' => sanitize_text_field($payload['return_pickup_location'] ?? ''),
            'return_dropoff_location' => sanitize_text_field($payload['return_dropoff_location'] ?? ''),
            'no_of_adults' => isset($payload['no_of_adults']) ? intval($payload['no_of_adults']) : 1,
            'no_of_children' => isset($payload['no_of_children']) ? intval($payload['no_of_children']) : 0,
            'large_suitcases' => isset($payload['large_suitcases']) ? intval($payload['large_suitcases']) : 0,
            'hand_luggage' => isset($payload['hand_luggage']) ? intval($payload['hand_luggage']) : 0,
            'child_seats' => isset($payload['child_seats']) ? intval($payload['child_seats']) : 0,
            'booster_seats' => sanitize_text_field($payload['booster_seats'] ?? 'No'),
            'special_requirements' => sanitize_textarea_field($payload['special_requirements'] ?? ''),
            'customer_name' => sanitize_text_field($payload['customer_name'] ?? ''),
            'customer_email' => sanitize_email($payload['customer_email'] ?? ''),
            'customer_phone' => sanitize_text_field($payload['customer_phone'] ?? ''),
            'vehicle_id' => $vehicle_id,
            'booking_status' => 'pending',
            'payment_status' => 'pending',
            'payment_type' => null,
            'total_amount' => $price !== '' ? floatval($price) : 0,
            'created_at' => current_time('mysql'),
            'email_sent' => 0,
        ),
        'meta' => array(
            'source_site' => sanitize_text_field($payload['source_site'] ?? ''),
            'ai_session_id' => sanitize_text_field($payload['ai_session_id'] ?? ''),
            'supabase_booking_id' => sanitize_text_field($payload['supabase_booking_id'] ?? ''),
            'ai_summary' => sanitize_textarea_field($payload['ai_summary'] ?? ''),
        ),
    );
}

function taxi_ai_bridge_validate_booking_payload($booking_data) {
    $required = array(
        'booking_date' => 'Pickup date is required.',
        'booking_time' => 'Pickup time is required.',
        'pickup_location' => 'Pickup location is required.',
        'dropoff_location' => 'Drop-off location is required.',
        'customer_name' => 'Customer name is required.',
        'customer_email' => 'Customer email is required.',
        'customer_phone' => 'Customer phone is required.',
    );

    foreach ($required as $field => $message) {
        if (empty($booking_data[$field])) {
            return new WP_Error('taxi_ai_missing_field', $message, array('status' => 400));
        }
    }

    if (!is_email($booking_data['customer_email'])) {
        return new WP_Error('taxi_ai_invalid_email', 'Customer email is invalid.', array('status' => 400));
    }

    return true;
}

function taxi_ai_bridge_create_booking(WP_REST_Request $request) {
    global $wpdb;

    if (!taxi_ai_bridge_tbe_table_exists()) {
        return new WP_Error('taxi_ai_tbe_missing', 'Taxi Booking Engine bookings table was not found.', array('status' => 500));
    }

    taxi_ai_bridge_activate();

    $payload = $request->get_json_params();
    $sanitized = taxi_ai_bridge_sanitize_booking_payload(is_array($payload) ? $payload : array());
    $booking_data = $sanitized['booking'];
    $meta_data = $sanitized['meta'];
    $valid = taxi_ai_bridge_validate_booking_payload($booking_data);

    if (is_wp_error($valid)) {
        return $valid;
    }

    $booking_table = $wpdb->prefix . 'tbe_bookings';
    $result = $wpdb->insert($booking_table, $booking_data);

    if ($result === false) {
        return new WP_Error('taxi_ai_insert_failed', 'Failed to create booking: ' . $wpdb->last_error, array('status' => 500));
    }

    $booking_id = intval($wpdb->insert_id);

    $wpdb->insert(taxi_ai_bridge_meta_table_name(), array(
        'tbe_booking_id' => $booking_id,
        'source_site' => $meta_data['source_site'],
        'ai_session_id' => $meta_data['ai_session_id'],
        'supabase_booking_id' => $meta_data['supabase_booking_id'],
        'ai_summary' => $meta_data['ai_summary'],
        'created_at' => current_time('mysql'),
    ));

    $email_sent = false;

    if (function_exists('tbe_send_quote_acknowledgement_once')) {
        $email_sent = tbe_send_quote_acknowledgement_once($booking_id);
    } elseif (function_exists('send_quote_acknowledgement')) {
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $booking_table WHERE id = %d", $booking_id));
        $email_sent = $booking ? send_quote_acknowledgement($booking) : false;
    }

    return rest_ensure_response(array(
        'ok' => true,
        'booking' => array(
            'id' => $booking_id,
            'status' => 'pending',
            'email_sent' => (bool) $email_sent,
            'admin_url' => admin_url('admin.php?page=tbe-bookings&action=view&id=' . $booking_id),
        ),
    ));
}

function taxi_ai_bridge_get_vehicles(WP_REST_Request $request) {
    $vehicles = get_posts(array(
        'post_type' => 'tbe_vehicle',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
    ));

    $data = array();

    foreach ($vehicles as $vehicle) {
        $data[] = array(
            'id' => intval($vehicle->ID),
            'name' => get_the_title($vehicle),
            'passenger_capacity' => intval(get_post_meta($vehicle->ID, '_passenger_capacity', true)),
            'luggage_capacity' => intval(get_post_meta($vehicle->ID, '_luggage_capacity', true)),
            'hand_luggage' => intval(get_post_meta($vehicle->ID, '_hand_luggage', true)),
            'price' => get_post_meta($vehicle->ID, '_price', true),
        );
    }

    return rest_ensure_response(array(
        'ok' => true,
        'vehicles' => $data,
    ));
}

function taxi_ai_bridge_register_routes() {
    register_rest_route('tbe-ai/v1', '/bookings', array(
        'methods' => 'POST',
        'callback' => 'taxi_ai_bridge_create_booking',
        'permission_callback' => 'taxi_ai_bridge_authorize_request',
    ));

    register_rest_route('tbe-ai/v1', '/vehicles', array(
        'methods' => 'GET',
        'callback' => 'taxi_ai_bridge_get_vehicles',
        'permission_callback' => 'taxi_ai_bridge_authorize_request',
    ));
}
add_action('rest_api_init', 'taxi_ai_bridge_register_routes');

