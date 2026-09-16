# Taxi AI Booking Bot

AI-powered taxi booking assistant for the WordPress taxi websites.

## Planned Stack

- Node.js backend for Hostinger
- DeepSeek API using OpenAI-compatible chat completions
- Supabase PostgreSQL + pgvector for bookings, chat history, and memory
- WordPress Taxi Booking Engine integration for booking storage and email templates
- WP Mail SMTP for customer/admin email delivery
- Telegram notification support prepared for a later phase

## Local Setup

```bash
npm install
cp .env.example .env
npm run dev
```

## Deployment

This repository is intended to deploy from GitHub to Hostinger.

Secrets must be configured in Hostinger or the deployment environment, never committed to GitHub.

