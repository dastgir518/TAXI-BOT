<?php
if (!defined('ABSPATH')) {
    exit;
}

function tbe_ai_get_secret() {
    if (defined('TBE_AI_SECRET') && TBE_AI_SECRET) {
        return TBE_AI_SECRET;
    }

    return get_option('tbe_ai_secret', '');
}

function tbe_ai_authorize_request(WP_REST_Request $request) {
    $expected = tbe_ai_get_secret();
    $provided = $request->get_header('x-tbe-ai-secret');

    if (!$expected || !$provided || !hash_equals($expected, $provided)) {
        return new WP_Error('tbe_ai_forbidden', 'Invalid AI booking secret.', array('status' => 403));
    }

    return true;
}

function tbe_ai_ensure_columns() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'tbe_bookings';
    $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name);

    if (!$table_exists) {
        return false;
    }

    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name", 0);
    $new_columns = array(
        'source_site' => "ALTER TABLE $table_name ADD source_site varchar(100) DEFAULT NULL",
        'created_via' => "ALTER TABLE $table_name ADD created_via varchar(50) DEFAULT NULL",
        'ai_session_id' => "ALTER TABLE $table_name ADD ai_session_id varchar(100) DEFAULT NULL",
        'supabase_booking_id' => "ALTER TABLE $table_name ADD supabase_booking_id varchar(100) DEFAULT NULL",
        'ai_summary' => "ALTER TABLE $table_name ADD ai_summary text",
        'telegram_sent' => "ALTER TABLE $table_name ADD telegram_sent tinyint(1) NOT NULL DEFAULT 0",
        'telegram_sent_at' => "ALTER TABLE $table_name ADD telegram_sent_at datetime DEFAULT NULL",
    );

    foreach ($new_columns as $column => $sql) {
        if (!in_array($column, $columns, true)) {
            $wpdb->query($sql);
        }
    }

    return true;
}

function tbe_ai_sanitize_booking_payload($payload) {
    $vehicle_id = isset($payload['vehicle_id']) ? intval($payload['vehicle_id']) : 0;
    $price = $vehicle_id ? get_post_meta($vehicle_id, '_price', true) : '';

    return array(
        'user_id' => 0,
        'source_site' => sanitize_text_field($payload['source_site'] ?? ''),
        'created_via' => sanitize_text_field($payload['created_via'] ?? 'ai_chat'),
        'ai_session_id' => sanitize_text_field($payload['ai_session_id'] ?? ''),
        'ai_summary' => sanitize_textarea_field($payload['ai_summary'] ?? ''),
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
    );
}

function tbe_ai_validate_booking_payload($booking_data) {
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
            return new WP_Error('tbe_ai_missing_field', $message, array('status' => 400));
        }
    }

    if (!is_email($booking_data['customer_email'])) {
        return new WP_Error('tbe_ai_invalid_email', 'Customer email is invalid.', array('status' => 400));
    }

    return true;
}

function tbe_ai_create_booking(WP_REST_Request $request) {
    global $wpdb;

    if (!tbe_ai_ensure_columns()) {
        return new WP_Error('tbe_ai_table_missing', 'Taxi booking table was not found.', array('status' => 500));
    }

    $payload = $request->get_json_params();
    $booking_data = tbe_ai_sanitize_booking_payload(is_array($payload) ? $payload : array());
    $valid = tbe_ai_validate_booking_payload($booking_data);

    if (is_wp_error($valid)) {
        return $valid;
    }

    $table_name = $wpdb->prefix . 'tbe_bookings';
    $result = $wpdb->insert($table_name, $booking_data);

    if ($result === false) {
        return new WP_Error('tbe_ai_insert_failed', 'Failed to create booking: ' . $wpdb->last_error, array('status' => 500));
    }

    $booking_id = intval($wpdb->insert_id);
    $email_sent = false;

    if (function_exists('tbe_send_quote_acknowledgement_once')) {
        $email_sent = tbe_send_quote_acknowledgement_once($booking_id);
    } elseif (function_exists('send_quote_acknowledgement')) {
        $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $booking_id));
        $email_sent = $booking ? send_quote_acknowledgement($booking) : false;
    }

    if (function_exists('qm_send_fcm_notification')) {
        qm_send_fcm_notification(array(
            'name' => $booking_data['customer_name'],
            'email' => $booking_data['customer_email'],
            'phone' => $booking_data['customer_phone'],
            'message' => $booking_data['pickup_location'] . ' to ' . $booking_data['dropoff_location'],
            'timestamp' => current_time('mysql'),
        ));
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

function tbe_ai_get_vehicles(WP_REST_Request $request) {
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

function tbe_ai_register_routes() {
    register_rest_route('tbe-ai/v1', '/bookings', array(
        'methods' => 'POST',
        'callback' => 'tbe_ai_create_booking',
        'permission_callback' => 'tbe_ai_authorize_request',
    ));

    register_rest_route('tbe-ai/v1', '/vehicles', array(
        'methods' => 'GET',
        'callback' => 'tbe_ai_get_vehicles',
        'permission_callback' => 'tbe_ai_authorize_request',
    ));
}
add_action('rest_api_init', 'tbe_ai_register_routes');

