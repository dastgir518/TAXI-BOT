import { createClient } from '@supabase/supabase-js';

import { config } from './config.js';

let supabase;

export function getSupabase() {
  if (!config.supabase.url || !config.supabase.serviceRoleKey) {
    return null;
  }

  if (!supabase) {
    supabase = createClient(config.supabase.url, config.supabase.serviceRoleKey, {
      auth: { persistSession: false },
    });
  }

  return supabase;
}

export async function saveSessionSnapshot(session) {
  const client = getSupabase();
  if (!client) return null;

  const { error } = await client.from('chat_sessions').upsert({
    id: session.id,
    source_site: session.site.host,
    customer_name: session.customer.name,
    customer_email: session.customer.email,
    customer_phone: session.customer.phone || null,
    status: session.status,
    booking_draft: session.booking,
    summary: session.summary || null,
    updated_at: new Date().toISOString(),
  });

  if (error) console.error('Supabase session save failed:', error.message);
  return !error;
}

export async function saveMessage(session, role, content) {
  const client = getSupabase();
  if (!client) return null;

  const { error } = await client.from('chat_messages').insert({
    session_id: session.id,
    role,
    content,
    created_at: new Date().toISOString(),
  });

  if (error) console.error('Supabase message save failed:', error.message);
  return !error;
}

export async function saveBookingCopy(session, wordpressBooking) {
  const client = getSupabase();
  if (!client) return null;

  const { data, error } = await client
    .from('bookings')
    .insert({
      session_id: session.id,
      source_site: session.site.host,
      wordpress_booking_id: wordpressBooking.id,
      customer_name: session.customer.name,
      customer_email: session.customer.email,
      customer_phone: session.customer.phone || null,
      status: wordpressBooking.status || 'pending',
      booking: session.booking,
      created_at: new Date().toISOString(),
    })
    .select()
    .single();

  if (error) {
    console.error('Supabase booking save failed:', error.message);
    return null;
  }

  return data;
}

