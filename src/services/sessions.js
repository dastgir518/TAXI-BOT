import { randomUUID } from 'node:crypto';

import { normalizeDraft } from '../schemas/booking.js';

const sessions = new Map();

export function createSession({ site, customer }) {
  const now = new Date().toISOString();
  const session = {
    id: randomUUID(),
    site,
    customer,
    booking: normalizeDraft({}),
    messages: [],
    status: 'collecting',
    summary: '',
    createdAt: now,
    updatedAt: now,
  };

  sessions.set(session.id, session);
  return session;
}

export function getSession(sessionId) {
  const session = sessions.get(sessionId);

  if (!session) {
    const error = new Error('Chat session not found');
    error.statusCode = 404;
    error.code = 'session_not_found';
    throw error;
  }

  return session;
}

export function findSession(sessionId) {
  return sessions.get(sessionId) || null;
}

export function putSession(session) {
  sessions.set(session.id, session);
  return session;
}

export function appendMessage(session, role, content) {
  session.messages.push({
    role,
    content,
    createdAt: new Date().toISOString(),
  });
  session.updatedAt = new Date().toISOString();
  sessions.set(session.id, session);
  return session;
}

export function updateSession(session, patch) {
  const updated = {
    ...session,
    ...patch,
    booking: normalizeDraft({
      ...(session.booking || {}),
      ...(patch.booking || {}),
    }),
    updatedAt: new Date().toISOString(),
  };

  sessions.set(updated.id, updated);
  return updated;
}
