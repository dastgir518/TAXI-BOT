import { config, requireConfig } from './config.js';

export async function createWordPressBooking(site, bookingPayload) {
  requireConfig(config.wordpress.aiSecret, 'WordPress AI secret is not configured');

  const response = await fetch(`${site.wordpressUrl.replace(/\/$/, '')}/wp-json/tbe-ai/v1/bookings`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-TBE-AI-SECRET': config.wordpress.aiSecret,
    },
    body: JSON.stringify(bookingPayload),
  });

  const json = await response.json().catch(() => ({}));

  if (!response.ok || !json.ok) {
    const error = new Error(json?.error?.message || json?.message || 'WordPress booking creation failed');
    error.statusCode = response.status || 502;
    error.code = 'wordpress_booking_failed';
    throw error;
  }

  return json.booking;
}

export async function getWordPressVehicles(site) {
  requireConfig(config.wordpress.aiSecret, 'WordPress AI secret is not configured');

  const response = await fetch(`${site.wordpressUrl.replace(/\/$/, '')}/wp-json/tbe-ai/v1/vehicles`, {
    headers: {
      'X-TBE-AI-SECRET': config.wordpress.aiSecret,
    },
  });

  const json = await response.json().catch(() => ({}));

  if (!response.ok || !json.ok) {
    const error = new Error(json?.error?.message || json?.message || 'WordPress vehicle lookup failed');
    error.statusCode = response.status || 502;
    error.code = 'wordpress_vehicle_lookup_failed';
    throw error;
  }

  return json.vehicles;
}

