# WordPress Integration

Upload `ai-booking-api.php` into the existing Taxi Booking Engine plugin:

```text
wp-content/plugins/booking-engine/includes/ai-booking-api.php
```

Then include it from `booking-engine.php` after the current booking/email files:

```php
require_once plugin_dir_path(__FILE__) . 'includes/ai-booking-api.php';
```

Set the shared secret in WordPress using one of these approaches:

```php
define('TBE_AI_SECRET', 'same-value-as-WORDPRESS_AI_SECRET');
```

or store the `tbe_ai_secret` option in WordPress.

