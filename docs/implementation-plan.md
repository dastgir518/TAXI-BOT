# Implementation Plan

## Backend

- Node.js + Express on Hostinger.
- DeepSeek through the OpenAI-compatible JS SDK.
- Supabase stores chat sessions, messages, booking copies, and future vector memory.
- WordPress remains the booking/admin/email system.

## WordPress

- Add the standalone `wordpress-plugin/taxi-ai-booking-bridge.php` companion plugin.
- Do not edit the existing Taxi Booking Engine plugin.
- Configure the same secret in WordPress and `WORDPRESS_AI_SECRET` in Hostinger.
- WordPress stores bookings in `wp_tbe_bookings` and sends email through existing WP Mail SMTP setup.
- AI-only metadata is stored by the companion plugin in `wp_taxi_ai_booking_meta`.

## Booking Flow

1. Customer starts chat with name and email.
2. Bot collects phone, pickup, drop-off, date, time, passenger count, luggage, and notes.
3. Bot summarizes the booking request.
4. Customer confirms.
5. Backend posts booking to WordPress.
6. WordPress stores booking and sends customer/admin emails.
7. Backend stores booking/chat copy in Supabase.

## Speed Choices

- Streaming endpoint: `POST /api/chat/stream`.
- Short model replies.
- Session state kept in memory and mirrored to Supabase when configured.
- Vector search only for returning-customer lookup, not every message.
