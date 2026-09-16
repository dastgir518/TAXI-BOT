# WordPress Integration

This is a standalone companion plugin. It must be uploaded separately and does not require editing the existing Taxi Booking Engine plugin.

```text
wp-content/plugins/taxi-ai-booking-bridge/taxi-ai-booking-bridge.php
```

Set the shared secret in WordPress using one of these approaches:

```php
define('TAXI_AI_BOOKING_SECRET', 'same-value-as-WORDPRESS_AI_SECRET');
```

or store the `taxi_ai_booking_secret` option in WordPress.

The plugin inserts confirmed AI bookings into the existing `wp_tbe_bookings` table and stores AI-only metadata in its own `wp_taxi_ai_booking_meta` table.
