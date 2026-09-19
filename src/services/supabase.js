import { createClient } from '@supabase/supabase-js';

import { config } from './config.js';
import { resolveSite } from './site-registry.js';
import { normalizeDraft } from '../schemas/booking.js';

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

export async function loadSessionSnapshot(sessionId) {
  const client = getSupabase();
  if (!client) return null;

  const { data: sessionRow, error: sessionError } = await client
    .from('chat_sessions')
    .select('*')
    .eq('id', sessionId)
    .single();

  if (sessionError || !sessionRow) {
    if (sessionError && sessionError.code !== 'PGRST116') {
      console.error('Supabase session load failed:', sessionError.message);
    }
    return null;
  }

  const { data: messageRows, error: messagesError } = await client
    .from('chat_messages')
    .select('role, content, created_at')
    .eq('session_id', sessionId)
    .order('created_at', { ascending: false })
    .limit(40);

  if (messagesError) {
    console.error('Supabase messages load failed:', messagesError.message);
  }

  const site = resolveSite(sessionRow.source_site);
  const messages = (messageRows || [])
    .reverse()
    .map((message) => ({
      role: message.role,
      content: message.content,
      createdAt: message.created_at,
    }));

  return {
    id: sessionRow.id,
    site,
    customer: {
      name: sessionRow.customer_name,
      email: sessionRow.customer_email,
      phone: sessionRow.customer_phone || '',
    },
    booking: normalizeDraft(sessionRow.booking_draft || {}),
    messages,
    status: sessionRow.status || 'collecting',
    summary: sessionRow.summary || '',
    createdAt: sessionRow.created_at,
    updatedAt: sessionRow.updated_at,
  };
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
