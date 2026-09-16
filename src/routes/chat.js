import { Router } from 'express';

import { asyncRoute } from '../services/async-route.js';
import { resolveSite } from '../services/site-registry.js';
import { createSession, getSession, appendMessage, updateSession } from '../services/sessions.js';
import { createAssistantReply, extractBookingFields, streamAssistantReply } from '../services/deepseek.js';
import { saveMessage, saveSessionSnapshot } from '../services/supabase.js';
import { messageSchema, requiredMissingFields, startChatSchema } from '../schemas/booking.js';

const router = Router();

function initialAssistantMessage(session) {
  return `Hi ${session.customer.name}, I can help with your taxi booking. What is your pickup location and drop-off location?`;
}

function applyExtraction(session, extraction) {
  const customer = {
    ...session.customer,
    ...(extraction.customer || {}),
  };

  return updateSession(session, {
    customer,
    booking: extraction.booking || {},
  });
}

router.post('/start', asyncRoute(async (req, res) => {
  const input = startChatSchema.parse(req.body);
  const site = resolveSite(input.source);
  const session = createSession({ site, customer: input.customer });
  const assistantMessage = initialAssistantMessage(session);

  appendMessage(session, 'assistant', assistantMessage);
  await saveSessionSnapshot(session);
  await saveMessage(session, 'assistant', assistantMessage);

  res.json({
    ok: true,
    sessionId: session.id,
    site: session.site,
    customer: session.customer,
    booking: session.booking,
    missingFields: requiredMissingFields(session),
    message: assistantMessage,
  });
}));

router.post('/message', asyncRoute(async (req, res) => {
  const input = messageSchema.parse(req.body);
  let session = getSession(input.sessionId);

  appendMessage(session, 'user', input.message);
  await saveMessage(session, 'user', input.message);

  const extraction = await extractBookingFields(session, input.message);
  session = applyExtraction(session, extraction);

  const assistantMessage = await createAssistantReply(session);
  appendMessage(session, 'assistant', assistantMessage);
  await saveMessage(session, 'assistant', assistantMessage);
  await saveSessionSnapshot(session);

  res.json({
    ok: true,
    sessionId: session.id,
    message: assistantMessage,
    booking: session.booking,
    customer: session.customer,
    missingFields: requiredMissingFields(session),
  });
}));

router.post('/stream', asyncRoute(async (req, res) => {
  const input = messageSchema.parse(req.body);
  let session = getSession(input.sessionId);

  appendMessage(session, 'user', input.message);
  await saveMessage(session, 'user', input.message);

  const extraction = await extractBookingFields(session, input.message);
  session = applyExtraction(session, extraction);

  res.writeHead(200, {
    'Content-Type': 'text/event-stream',
    'Cache-Control': 'no-cache, no-transform',
    Connection: 'keep-alive',
    'X-Accel-Buffering': 'no',
  });

  const send = (event, data) => {
    res.write(`event: ${event}\n`);
    res.write(`data: ${JSON.stringify(data)}\n\n`);
  };

  send('state', {
    sessionId: session.id,
    booking: session.booking,
    customer: session.customer,
    missingFields: requiredMissingFields(session),
  });

  const assistantMessage = await streamAssistantReply(session, (token) => {
    send('token', { token });
  });

  appendMessage(session, 'assistant', assistantMessage);
  await saveMessage(session, 'assistant', assistantMessage);
  await saveSessionSnapshot(session);

  send('done', {
    message: assistantMessage,
    booking: session.booking,
    customer: session.customer,
    missingFields: requiredMissingFields(session),
  });
  res.end();
}));

export default router;

