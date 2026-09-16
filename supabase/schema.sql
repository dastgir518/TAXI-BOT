create extension if not exists vector;

create table if not exists public.customers (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  email text not null,
  phone text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now(),
  unique (email)
);

create table if not exists public.chat_sessions (
  id uuid primary key,
  source_site text not null,
  customer_name text not null,
  customer_email text not null,
  customer_phone text,
  status text not null default 'collecting',
  booking_draft jsonb not null default '{}'::jsonb,
  summary text,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.chat_messages (
  id bigint generated always as identity primary key,
  session_id uuid not null references public.chat_sessions(id) on delete cascade,
  role text not null check (role in ('system', 'user', 'assistant', 'tool')),
  content text not null,
  created_at timestamptz not null default now()
);

create table if not exists public.bookings (
  id uuid primary key default gen_random_uuid(),
  session_id uuid references public.chat_sessions(id) on delete set null,
  source_site text not null,
  wordpress_booking_id bigint,
  customer_name text not null,
  customer_email text not null,
  customer_phone text,
  status text not null default 'pending',
  booking jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table if not exists public.booking_events (
  id bigint generated always as identity primary key,
  booking_id uuid references public.bookings(id) on delete cascade,
  event_type text not null,
  payload jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create table if not exists public.chat_embeddings (
  id bigint generated always as identity primary key,
  session_id uuid references public.chat_sessions(id) on delete cascade,
  message_id bigint references public.chat_messages(id) on delete cascade,
  content text not null,
  embedding vector(1536),
  metadata jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create index if not exists chat_sessions_customer_email_idx on public.chat_sessions (customer_email);
create index if not exists chat_messages_session_id_idx on public.chat_messages (session_id);
create index if not exists bookings_customer_email_idx on public.bookings (customer_email);
create index if not exists bookings_wordpress_booking_id_idx on public.bookings (wordpress_booking_id);

