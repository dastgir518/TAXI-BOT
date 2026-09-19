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
define('TAXI_AI_DEFAULT_BACKEND_URL', 'https://bot.taxiweybridge.co.uk');

function taxi_ai_bridge_get_secret() {
    if (defined('TAXI_AI_BOOKING_SECRET') && TAXI_AI_BOOKING_SECRET) {
        return TAXI_AI_BOOKING_SECRET;
    }

    if (defined('TBE_AI_SECRET') && TBE_AI_SECRET) {
        return TBE_AI_SECRET;
    }

    return get_option('taxi_ai_booking_secret', '');
}

function taxi_ai_bridge_generate_secret() {
    return wp_generate_password(48, true, true);
}

function taxi_ai_bridge_get_widget_position() {
    $position = get_option('taxi_ai_widget_position', 'bottom-right');
    return in_array($position, array('bottom-right', 'bottom-left'), true) ? $position : 'bottom-right';
}

function taxi_ai_bridge_get_backend_url() {
    $backend_url = get_option('taxi_ai_backend_url', TAXI_AI_DEFAULT_BACKEND_URL);
    $backend_url = $backend_url ? untrailingslashit($backend_url) : TAXI_AI_DEFAULT_BACKEND_URL;
    return esc_url_raw($backend_url);
}

function taxi_ai_bridge_admin_menu() {
    add_options_page(
        'Taxi AI Booking Bridge',
        'Taxi AI Booking Bridge',
        'manage_options',
        'taxi-ai-booking-bridge',
        'taxi_ai_bridge_settings_page'
    );
}
add_action('admin_menu', 'taxi_ai_bridge_admin_menu');

function taxi_ai_bridge_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['taxi_ai_bridge_save']) && check_admin_referer('taxi_ai_bridge_settings')) {
        $secret = sanitize_text_field($_POST['taxi_ai_booking_secret'] ?? '');
        update_option('taxi_ai_booking_secret', $secret);

        $widget_enabled = isset($_POST['taxi_ai_widget_enabled']) ? '1' : '0';
        update_option('taxi_ai_widget_enabled', $widget_enabled);

        $widget_position = sanitize_text_field($_POST['taxi_ai_widget_position'] ?? 'bottom-right');
        if (!in_array($widget_position, array('bottom-right', 'bottom-left'), true)) {
            $widget_position = 'bottom-right';
        }
        update_option('taxi_ai_widget_position', $widget_position);

        $backend_url = esc_url_raw($_POST['taxi_ai_backend_url'] ?? TAXI_AI_DEFAULT_BACKEND_URL);
        update_option('taxi_ai_backend_url', untrailingslashit($backend_url));

        $google_places_api_key = sanitize_text_field($_POST['taxi_ai_google_places_api_key'] ?? '');
        update_option('taxi_ai_google_places_api_key', $google_places_api_key);

        echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
    }

    if (isset($_POST['taxi_ai_bridge_generate']) && check_admin_referer('taxi_ai_bridge_settings')) {
        $secret = taxi_ai_bridge_generate_secret();
        update_option('taxi_ai_booking_secret', $secret);
        echo '<div class="notice notice-success"><p>New secret generated.</p></div>';
    }

    $secret = taxi_ai_bridge_get_secret();
    $widget_enabled = get_option('taxi_ai_widget_enabled', '1');
    $widget_position = taxi_ai_bridge_get_widget_position();
    $backend_url = taxi_ai_bridge_get_backend_url();
    $google_places_api_key = get_option('taxi_ai_google_places_api_key', '');
    ?>
    <div class="wrap">
        <h1>Taxi AI Booking Bridge</h1>
        <p>This companion plugin adds secure AI booking endpoints without modifying Taxi Booking Engine.</p>

        <form method="post">
            <?php wp_nonce_field('taxi_ai_bridge_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="taxi_ai_booking_secret">AI Booking Secret</label></th>
                    <td>
                        <input
                            type="text"
                            id="taxi_ai_booking_secret"
                            name="taxi_ai_booking_secret"
                            value="<?php echo esc_attr($secret); ?>"
                            class="regular-text"
                            autocomplete="off"
                        >
                        <p class="description">Use the same value as <code>WORDPRESS_AI_SECRET</code> in the Node.js backend.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">REST Endpoints</th>
                    <td>
                        <code><?php echo esc_html(rest_url('tbe-ai/v1/bookings')); ?></code><br>
                        <code><?php echo esc_html(rest_url('tbe-ai/v1/vehicles')); ?></code>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Frontend Widget</th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="taxi_ai_widget_enabled"
                                value="1"
                                <?php checked($widget_enabled, '1'); ?>
                            >
                            Enable AI booking chat on the website
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="taxi_ai_widget_position">Widget Position</label></th>
                    <td>
                        <select id="taxi_ai_widget_position" name="taxi_ai_widget_position">
                            <option value="bottom-right" <?php selected($widget_position, 'bottom-right'); ?>>Bottom right</option>
                            <option value="bottom-left" <?php selected($widget_position, 'bottom-left'); ?>>Bottom left</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="taxi_ai_backend_url">Bot Backend URL</label></th>
                    <td>
                        <input
                            type="url"
                            id="taxi_ai_backend_url"
                            name="taxi_ai_backend_url"
                            value="<?php echo esc_attr($backend_url); ?>"
                            class="regular-text"
                            placeholder="<?php echo esc_attr(TAXI_AI_DEFAULT_BACKEND_URL); ?>"
                        >
                        <p class="description">The public Node.js booking bot URL used by the chat widget.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="taxi_ai_google_places_api_key">Google Places API Key</label></th>
                    <td>
                        <input
                            type="text"
                            id="taxi_ai_google_places_api_key"
                            name="taxi_ai_google_places_api_key"
                            value="<?php echo esc_attr($google_places_api_key); ?>"
                            class="regular-text"
                            autocomplete="off"
                        >
                        <p class="description">Optional. Leave blank if the existing booking system already loads Google Places on the page.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings', 'primary', 'taxi_ai_bridge_save', false); ?>
            <?php submit_button('Generate New Secret', 'secondary', 'taxi_ai_bridge_generate', false); ?>
        </form>
    </div>
    <?php
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

    if (!get_option('taxi_ai_booking_secret')) {
        add_option('taxi_ai_booking_secret', taxi_ai_bridge_generate_secret());
    }

    if (get_option('taxi_ai_widget_enabled', null) === null) {
        add_option('taxi_ai_widget_enabled', '1');
    }

    if (!get_option('taxi_ai_widget_position')) {
        add_option('taxi_ai_widget_position', 'bottom-right');
    }

    if (!get_option('taxi_ai_backend_url')) {
        add_option('taxi_ai_backend_url', TAXI_AI_DEFAULT_BACKEND_URL);
    }

    if (get_option('taxi_ai_google_places_api_key', null) === null) {
        add_option('taxi_ai_google_places_api_key', '');
    }
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

function taxi_ai_bridge_render_widget() {
    if (is_admin() || get_option('taxi_ai_widget_enabled', '1') !== '1') {
        return;
    }

    $position = taxi_ai_bridge_get_widget_position();
    $backend_url = taxi_ai_bridge_get_backend_url();
    $google_places_api_key = get_option('taxi_ai_google_places_api_key', '');
    $source = wp_parse_url(home_url(), PHP_URL_HOST);
    ?>
    <div
        id="taxi-ai-widget-root"
        class="taxi-ai-widget <?php echo esc_attr($position); ?>"
        data-backend-url="<?php echo esc_attr($backend_url); ?>"
        data-google-places-api-key="<?php echo esc_attr($google_places_api_key); ?>"
        data-source="<?php echo esc_attr($source); ?>"
    >
        <section class="taxi-ai-panel" aria-label="AI booking chat" aria-hidden="true">
            <div class="taxi-ai-header">
                <div>
                    <div class="taxi-ai-kicker">Taxi booking</div>
                    <div class="taxi-ai-title">AI assistant</div>
                </div>
                <div class="taxi-ai-header-actions">
                    <button type="button" class="taxi-ai-icon-button taxi-ai-minimize" title="Minimize booking chat" aria-label="Minimize booking chat">-</button>
                    <button type="button" class="taxi-ai-icon-button taxi-ai-close" title="Close and start a new booking chat" aria-label="Close and start a new booking chat">x</button>
                </div>
            </div>

            <div class="taxi-ai-start">
                <div class="taxi-ai-start-copy">
                    <strong>Start your booking</strong>
                    <span>Enter your details and the assistant will collect the journey information.</span>
                </div>
                <div class="taxi-ai-start-error" hidden></div>
                <form class="taxi-ai-start-form">
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" autocomplete="name" required minlength="2">
                    </label>
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" autocomplete="email" required>
                    </label>
                    <button type="submit">Start chat</button>
                </form>
            </div>

            <div class="taxi-ai-chat" hidden>
                <div class="taxi-ai-messages" aria-live="polite"></div>
                <div class="taxi-ai-address-card" hidden>
                    <label>
                        <span class="taxi-ai-address-label">Search address</span>
                        <input type="text" class="taxi-ai-address-input" autocomplete="off">
                    </label>
                    <button type="button" class="taxi-ai-address-cancel">Use chat instead</button>
                </div>
                <form class="taxi-ai-message-form">
                    <textarea name="message" rows="1" maxlength="2000" placeholder="Type your booking details..." required></textarea>
                    <button type="submit">Send</button>
                </form>
            </div>
        </section>

        <button type="button" class="taxi-ai-launcher" title="Open booking chat" aria-label="Open booking chat">
            <span>AI</span>
        </button>
    </div>

    <style>
        #taxi-ai-widget-root {
            --taxi-ai-bg: rgba(18, 22, 30, 0.72);
            --taxi-ai-bg-strong: rgba(18, 22, 30, 0.9);
            --taxi-ai-surface: rgba(255, 255, 255, 0.11);
            --taxi-ai-surface-strong: rgba(255, 255, 255, 0.18);
            --taxi-ai-border: rgba(255, 255, 255, 0.2);
            --taxi-ai-text: #ffffff;
            --taxi-ai-muted: rgba(255, 255, 255, 0.72);
            --taxi-ai-accent: #f4c84a;
            --taxi-ai-accent-text: #1a1a1a;
            --taxi-ai-shadow: 0 22px 70px rgba(0, 0, 0, 0.36);
            position: fixed;
            bottom: 22px;
            z-index: 999999;
            color: var(--taxi-ai-text);
            font-family: inherit;
            letter-spacing: 0;
        }

        #taxi-ai-widget-root.bottom-right {
            right: 22px;
        }

        #taxi-ai-widget-root.bottom-left {
            left: 22px;
        }

        #taxi-ai-widget-root * {
            box-sizing: border-box;
        }

        .taxi-ai-panel {
            width: min(380px, calc(100vw - 32px));
            max-height: min(680px, calc(100vh - 98px));
            display: none;
            flex-direction: column;
            overflow: hidden;
            margin-bottom: 14px;
            border: 1px solid var(--taxi-ai-border);
            border-radius: 22px;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.16), rgba(255, 255, 255, 0.05)),
                var(--taxi-ai-bg);
            box-shadow: var(--taxi-ai-shadow);
            backdrop-filter: blur(22px) saturate(140%);
            -webkit-backdrop-filter: blur(22px) saturate(140%);
        }

        #taxi-ai-widget-root.is-open .taxi-ai-panel {
            display: flex;
        }

        .taxi-ai-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 18px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
        }

        .taxi-ai-kicker {
            margin-bottom: 4px;
            color: var(--taxi-ai-muted);
            font-size: 12px;
            line-height: 1.2;
            text-transform: uppercase;
        }

        .taxi-ai-title {
            font-size: 18px;
            line-height: 1.2;
            font-weight: 700;
        }

        .taxi-ai-header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
        }

        .taxi-ai-icon-button,
        .taxi-ai-launcher,
        .taxi-ai-start-form button,
        .taxi-ai-message-form button {
            border: 0;
            cursor: pointer;
            font-family: inherit;
            letter-spacing: 0;
        }

        .taxi-ai-icon-button {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 50%;
            color: var(--taxi-ai-text);
            background: var(--taxi-ai-surface);
            font-size: 18px;
            line-height: 1;
        }

        .taxi-ai-start,
        .taxi-ai-chat {
            min-height: 0;
            padding: 18px;
        }

        .taxi-ai-start[hidden],
        .taxi-ai-chat[hidden],
        .taxi-ai-start-error[hidden] {
            display: none;
        }

        .taxi-ai-start-copy {
            display: grid;
            gap: 6px;
            margin-bottom: 16px;
        }

        .taxi-ai-start-copy strong {
            font-size: 17px;
            line-height: 1.25;
        }

        .taxi-ai-start-copy span {
            color: var(--taxi-ai-muted);
            font-size: 14px;
            line-height: 1.45;
        }

        .taxi-ai-start-error {
            margin-bottom: 14px;
            border: 1px solid rgba(255, 120, 120, 0.42);
            border-radius: 14px;
            padding: 10px 12px;
            color: var(--taxi-ai-text);
            background: rgba(130, 30, 30, 0.34);
            font-size: 14px;
            line-height: 1.4;
        }

        .taxi-ai-start-form {
            display: grid;
            gap: 12px;
        }

        .taxi-ai-start-form label {
            display: grid;
            gap: 6px;
            margin: 0;
            color: var(--taxi-ai-muted);
            font-size: 13px;
            line-height: 1.2;
        }

        .taxi-ai-start-form input,
        .taxi-ai-message-form textarea {
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 14px;
            outline: none;
            color: var(--taxi-ai-text);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            font-family: inherit;
            font-size: 15px;
        }

        .taxi-ai-start-form input {
            height: 46px;
            padding: 0 14px;
        }

        .taxi-ai-start-form input:focus,
        .taxi-ai-message-form textarea:focus {
            border-color: rgba(244, 200, 74, 0.85);
            box-shadow: 0 0 0 3px rgba(244, 200, 74, 0.18);
        }

        .taxi-ai-start-form button,
        .taxi-ai-message-form button {
            min-height: 44px;
            border-radius: 14px;
            padding: 0 18px;
            color: var(--taxi-ai-accent-text);
            background: var(--taxi-ai-accent);
            font-size: 15px;
            font-weight: 700;
        }

        .taxi-ai-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .taxi-ai-messages {
            min-height: 250px;
            max-height: min(470px, calc(100vh - 260px));
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-right: 4px;
        }

        .taxi-ai-address-card {
            display: grid;
            gap: 10px;
            border: 1px solid rgba(244, 200, 74, 0.38);
            border-radius: 16px;
            padding: 12px;
            background: rgba(244, 200, 74, 0.12);
        }

        .taxi-ai-address-card[hidden] {
            display: none;
        }

        .taxi-ai-address-card label {
            display: grid;
            gap: 7px;
            margin: 0;
        }

        .taxi-ai-address-label {
            color: var(--taxi-ai-text);
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .taxi-ai-address-input {
            width: 100%;
            height: 44px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            outline: none;
            color: var(--taxi-ai-text);
            background: rgba(255, 255, 255, 0.13);
            padding: 0 12px;
            font-family: inherit;
            font-size: 15px;
        }

        .taxi-ai-address-input:focus {
            border-color: rgba(244, 200, 74, 0.85);
            box-shadow: 0 0 0 3px rgba(244, 200, 74, 0.18);
        }

        .taxi-ai-address-cancel {
            width: fit-content;
            border: 0;
            border-radius: 999px;
            padding: 7px 11px;
            color: var(--taxi-ai-text);
            background: rgba(255, 255, 255, 0.12);
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            line-height: 1.2;
        }

        .taxi-ai-message {
            width: fit-content;
            max-width: 86%;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 10px 12px;
            color: var(--taxi-ai-text);
            background: var(--taxi-ai-surface);
            font-size: 14px;
            line-height: 1.45;
            overflow-wrap: anywhere;
            white-space: pre-wrap;
        }

        .taxi-ai-message.user {
            align-self: flex-end;
            color: var(--taxi-ai-accent-text);
            background: rgba(244, 200, 74, 0.92);
            border-color: rgba(244, 200, 74, 0.95);
        }

        .taxi-ai-message.assistant,
        .taxi-ai-message.system {
            align-self: flex-start;
        }

        .taxi-ai-message.error {
            border-color: rgba(255, 120, 120, 0.45);
            background: rgba(130, 30, 30, 0.36);
        }

        .taxi-ai-thinking {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .taxi-ai-thinking-dots {
            display: inline-flex;
            gap: 4px;
            align-items: center;
        }

        .taxi-ai-thinking-dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.45;
            animation: taxi-ai-thinking-pulse 1s infinite ease-in-out;
        }

        .taxi-ai-thinking-dots span:nth-child(2) {
            animation-delay: 0.16s;
        }

        .taxi-ai-thinking-dots span:nth-child(3) {
            animation-delay: 0.32s;
        }

        @keyframes taxi-ai-thinking-pulse {
            0%,
            80%,
            100% {
                transform: translateY(0);
                opacity: 0.35;
            }

            40% {
                transform: translateY(-3px);
                opacity: 0.95;
            }
        }

        .taxi-ai-message-form {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 10px;
            align-items: end;
        }

        .taxi-ai-message-form textarea {
            min-height: 44px;
            max-height: 120px;
            resize: none;
            padding: 11px 12px;
            line-height: 1.35;
        }

        .taxi-ai-launcher {
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            margin-left: auto;
            border-radius: 50%;
            color: var(--taxi-ai-accent-text);
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.52), rgba(255, 255, 255, 0.08)),
                var(--taxi-ai-accent);
            box-shadow: 0 16px 44px rgba(0, 0, 0, 0.3);
            font-size: 18px;
            font-weight: 800;
        }

        #taxi-ai-widget-root.bottom-left .taxi-ai-launcher {
            margin-right: auto;
            margin-left: 0;
        }

        .taxi-ai-start-form button:disabled,
        .taxi-ai-message-form button:disabled {
            cursor: wait;
            opacity: 0.72;
        }

        @media (max-width: 520px) {
            #taxi-ai-widget-root {
                right: 12px;
                bottom: 12px;
                left: 12px;
            }

            #taxi-ai-widget-root.bottom-right,
            #taxi-ai-widget-root.bottom-left {
                right: 12px;
                left: 12px;
            }

            .taxi-ai-panel {
                width: 100%;
                max-height: calc(100vh - 88px);
                border-radius: 18px;
            }

            .taxi-ai-messages {
                max-height: calc(100vh - 290px);
            }

            .taxi-ai-message-form {
                grid-template-columns: 1fr;
            }

            .taxi-ai-message-form button {
                width: 100%;
            }
        }
    </style>

    <script>
        (function () {
            var root = document.getElementById('taxi-ai-widget-root');
            if (!root) {
                return;
            }

            var backendUrl = (root.dataset.backendUrl || '').replace(/\/+$/, '');
            var googlePlacesApiKey = root.dataset.googlePlacesApiKey || '';
            var source = root.dataset.source || window.location.hostname;
            var panel = root.querySelector('.taxi-ai-panel');
            var launcher = root.querySelector('.taxi-ai-launcher');
            var minimizeButton = root.querySelector('.taxi-ai-minimize');
            var closeButton = root.querySelector('.taxi-ai-close');
            var startPane = root.querySelector('.taxi-ai-start');
            var startError = root.querySelector('.taxi-ai-start-error');
            var startForm = root.querySelector('.taxi-ai-start-form');
            var chatPane = root.querySelector('.taxi-ai-chat');
            var messages = root.querySelector('.taxi-ai-messages');
            var addressCard = root.querySelector('.taxi-ai-address-card');
            var addressLabel = root.querySelector('.taxi-ai-address-label');
            var addressInput = root.querySelector('.taxi-ai-address-input');
            var addressCancel = root.querySelector('.taxi-ai-address-cancel');
            var messageForm = root.querySelector('.taxi-ai-message-form');
            var messageInput = messageForm.querySelector('textarea');
            var sessionId = '';
            var thinkingMessage = null;
            var currentAddressField = '';
            var placesReadyPromise = null;
            var autocomplete = null;
            var chatVersion = 0;

            function setOpen(isOpen) {
                root.classList.toggle('is-open', isOpen);
                panel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
                if (isOpen) {
                    setTimeout(function () {
                        var focusTarget = sessionId ? messageInput : startForm.querySelector('input[name="name"]');
                        if (focusTarget) {
                            focusTarget.focus();
                        }
                    }, 60);
                }
            }

            function resetChat() {
                chatVersion += 1;
                hideThinking();
                sessionId = '';
                currentAddressField = '';
                messages.innerHTML = '';
                addressCard.hidden = true;
                chatPane.hidden = true;
                startPane.hidden = false;
                setStartError('');
                startForm.reset();
                messageForm.reset();
                messageInput.style.height = '';
                addressInput.value = '';
                setBusy(startForm, false);
                setBusy(messageForm, false);
            }

            function addMessage(role, text) {
                var item = document.createElement('div');
                item.className = 'taxi-ai-message ' + role;
                item.textContent = text;
                messages.appendChild(item);
                messages.scrollTop = messages.scrollHeight;
                return item;
            }

            function showThinking() {
                hideThinking();
                thinkingMessage = document.createElement('div');
                thinkingMessage.className = 'taxi-ai-message assistant';
                thinkingMessage.innerHTML = '<span class="taxi-ai-thinking">Thinking <span class="taxi-ai-thinking-dots" aria-hidden="true"><span></span><span></span><span></span></span></span>';
                messages.appendChild(thinkingMessage);
                messages.scrollTop = messages.scrollHeight;
            }

            function hideThinking() {
                if (thinkingMessage && thinkingMessage.parentNode) {
                    thinkingMessage.parentNode.removeChild(thinkingMessage);
                }
                thinkingMessage = null;
            }

            function setStartError(text) {
                startError.textContent = text || '';
                startError.hidden = !text;
            }

            function nextAddressField(missingFields) {
                missingFields = missingFields || [];
                if (missingFields.indexOf('pickup location') !== -1) {
                    return 'pickupLocation';
                }
                if (missingFields.indexOf('drop-off location') !== -1) {
                    return 'dropoffLocation';
                }
                return '';
            }

            function addressFieldLabel(field) {
                if (field === 'pickupLocation') {
                    return 'Search pickup address';
                }
                if (field === 'dropoffLocation') {
                    return 'Search drop-off address';
                }
                return 'Search address';
            }

            function updateAddressPrompt(missingFields) {
                currentAddressField = nextAddressField(missingFields);
                if (!currentAddressField) {
                    addressCard.hidden = true;
                    return;
                }

                if (!hasGooglePlaces() && !googlePlacesApiKey) {
                    addressCard.hidden = true;
                    return;
                }

                addressLabel.textContent = addressFieldLabel(currentAddressField);
                addressInput.placeholder = currentAddressField === 'pickupLocation'
                    ? 'Postcode, airport, station or full pickup address'
                    : 'Postcode, airport, station or full drop-off address';
                addressInput.value = '';
                addressCard.hidden = false;
                initPlacesAutocomplete();
            }

            function hasGooglePlaces() {
                return Boolean(window.google && window.google.maps && window.google.maps.places);
            }

            function loadGooglePlaces() {
                if (hasGooglePlaces()) {
                    return Promise.resolve(true);
                }

                if (!googlePlacesApiKey) {
                    return Promise.resolve(false);
                }

                if (placesReadyPromise) {
                    return placesReadyPromise;
                }

                placesReadyPromise = new Promise(function (resolve) {
                    var callbackName = 'taxiAiGooglePlacesReady_' + Date.now();
                    window[callbackName] = function () {
                        delete window[callbackName];
                        resolve(hasGooglePlaces());
                    };

                    var script = document.createElement('script');
                    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(googlePlacesApiKey) + '&libraries=places&callback=' + callbackName;
                    script.async = true;
                    script.defer = true;
                    script.onerror = function () {
                        delete window[callbackName];
                        resolve(false);
                    };
                    document.head.appendChild(script);
                });

                return placesReadyPromise;
            }

            async function initPlacesAutocomplete() {
                var ready = await loadGooglePlaces();
                if (!ready || autocomplete) {
                    if (!ready) {
                        addressCard.hidden = true;
                    }
                    return;
                }

                autocomplete = new window.google.maps.places.Autocomplete(addressInput, {
                    componentRestrictions: { country: 'gb' },
                    fields: ['address_components', 'formatted_address', 'geometry', 'name', 'place_id'],
                });

                autocomplete.addListener('place_changed', function () {
                    var place = autocomplete.getPlace();
                    if (!place || !currentAddressField) {
                        return;
                    }

                    sendAddressSelection(place);
                });
            }

            function postcodeFromPlace(place) {
                var components = place.address_components || [];
                for (var index = 0; index < components.length; index += 1) {
                    if ((components[index].types || []).indexOf('postal_code') !== -1) {
                        return components[index].long_name || components[index].short_name || '';
                    }
                }
                return '';
            }

            function placeAddress(place) {
                return place.formatted_address || place.name || addressInput.value.trim();
            }

            async function sendAddressSelection(place) {
                var field = currentAddressField;
                var address = placeAddress(place);
                if (!field || !address || !sessionId) {
                    return;
                }

                var label = field === 'pickupLocation' ? 'Pickup' : 'Drop-off';
                var location = place.geometry && place.geometry.location;
                var structured = {
                    type: 'location',
                    field: field,
                    address: address,
                    placeId: place.place_id || '',
                    postcode: postcodeFromPlace(place),
                    lat: location ? location.lat() : null,
                    lng: location ? location.lng() : null
                };

                addressCard.hidden = true;
                addMessage('user', label + ' selected: ' + address);
                setBusy(messageForm, true);
                showThinking();
                var requestVersion = chatVersion;

                try {
                    var data = await postJson('/api/chat/message', {
                        sessionId: sessionId,
                        message: label + ' selected: ' + address,
                        structured: structured
                    });
                    if (requestVersion !== chatVersion) {
                        return;
                    }
                    hideThinking();
                    addMessage('assistant', data.message || 'Thanks, I have updated your booking details.');
                    updateAddressPrompt(data.missingFields);
                } catch (error) {
                    if (requestVersion !== chatVersion) {
                        return;
                    }
                    hideThinking();
                    addMessage('error system', 'I could not save that address. Please type it in the chat instead.');
                    addressCard.hidden = false;
                } finally {
                    if (requestVersion === chatVersion) {
                        setBusy(messageForm, false);
                        messageInput.focus();
                    }
                }
            }

            function setBusy(form, busy) {
                Array.prototype.forEach.call(form.querySelectorAll('button, input, textarea'), function (field) {
                    field.disabled = busy;
                });
            }

            async function postJson(path, body) {
                var response = await fetch(backendUrl + path, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(body)
                });
                var data = await response.json().catch(function () {
                    return {};
                });
                if (!response.ok || data.ok === false) {
                    throw new Error(data.message || data.error || 'Request failed');
                }
                return data;
            }

            launcher.addEventListener('click', function () {
                setOpen(!root.classList.contains('is-open'));
            });

            minimizeButton.addEventListener('click', function () {
                setOpen(false);
            });

            closeButton.addEventListener('click', function () {
                resetChat();
                setOpen(false);
            });

            addressCancel.addEventListener('click', function () {
                addressCard.hidden = true;
                messageInput.focus();
            });

            startForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (!backendUrl) {
                    addMessage('error system', 'The booking assistant is not configured yet.');
                    return;
                }

                var formData = new FormData(startForm);
                var name = String(formData.get('name') || '').trim();
                var email = String(formData.get('email') || '').trim();

                setStartError('');
                setBusy(startForm, true);
                var requestVersion = chatVersion;
                try {
                    startPane.hidden = true;
                    chatPane.hidden = false;
                    showThinking();
                    var data = await postJson('/api/chat/start', {
                        source: source,
                        customer: {
                            name: name,
                            email: email
                        }
                    });
                    if (requestVersion !== chatVersion) {
                        return;
                    }
                    sessionId = data.sessionId || '';
                    hideThinking();
                    addMessage('assistant', data.message || 'Thanks. What is your pickup location and drop-off location?');
                    updateAddressPrompt(data.missingFields);
                    messageInput.focus();
                } catch (error) {
                    if (requestVersion !== chatVersion) {
                        return;
                    }
                    hideThinking();
                    chatPane.hidden = true;
                    startPane.hidden = false;
                    setStartError('I could not start the booking chat. Please try again in a moment.');
                } finally {
                    if (requestVersion === chatVersion) {
                        setBusy(startForm, false);
                    }
                }
            });

            messageForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                var text = messageInput.value.trim();
                if (!text || !sessionId) {
                    return;
                }

                messageInput.value = '';
                addMessage('user', text);
                setBusy(messageForm, true);
                showThinking();
                var requestVersion = chatVersion;

                try {
                    var data = await postJson('/api/chat/message', {
                        sessionId: sessionId,
                        message: text
                    });
                    if (requestVersion !== chatVersion) {
                        return;
                    }
                    hideThinking();
                    addMessage('assistant', data.message || 'Thanks, I have updated your booking details.');
                    updateAddressPrompt(data.missingFields);
                } catch (error) {
                    if (requestVersion !== chatVersion) {
                        return;
                    }
                    hideThinking();
                    addMessage('error system', 'I could not reach the booking assistant. Please try again.');
                } finally {
                    if (requestVersion === chatVersion) {
                        setBusy(messageForm, false);
                        messageInput.focus();
                    }
                }
            });

            messageInput.addEventListener('input', function () {
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
            });
        }());
    </script>
    <?php
}
add_action('wp_footer', 'taxi_ai_bridge_render_widget');
