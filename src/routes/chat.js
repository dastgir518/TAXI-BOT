import { Router } from 'express';

import { asyncRoute } from '../services/async-route.js';
import { londonDateTime } from '../services/booking-time.js';
import { resolveSite } from '../services/site-registry.js';
import { createSession, getSession, appendMessage, updateSession, putSession } from '../services/sessions.js';
import { createAssistantReply, extractBookingFields, streamAssistantReply } from '../services/deepseek.js';
import { createWordPressBooking } from '../services/wordpress.js';
import { loadSessionSnapshot, saveBookingCopy, saveMessage, saveSessionSnapshot } from '../services/supabase.js';
import { messageSchema, requiredMissingFields, startChatSchema, toWordPressBooking } from '../schemas/booking.js';

const router = Router();

function initialAssistantMessage(session) {
  return `Hi ${session.customer.name}, I can help with your taxi booking. What is your pickup location and drop-off location?`;
}

function latestAssistantMessage(session) {
  return [...(session.messages || [])].reverse().find((message) => message.role === 'assistant')?.content || '';
}

function normalizeLocationText(value = '') {
  return String(value)
    .trim()
    .replace(/\s+/g, ' ')
    .replace(/[.,]+$/g, '');
}

function looksLikeLocationAnswer(message, assistantMessage = '') {
  const text = normalizeLocationText(message);
  if (!text || text.length < 3) return false;
  if (/^(yes|no|ok|okay|confirm|confirmed|thanks|thank you|skip|none|nothing)$/i.test(text)) return false;
  if (/^\+?[\d\s().-]{5,}$/.test(text)) return false;
  if (/\b(today|tomorrow|tonight|morning|afternoon|evening|am|pm|o'clock|confirm|book)\b/i.test(text)) return false;

  const locationWords = /\b(airport|station|bridge|street|st|road|rd|lane|ln|avenue|ave|drive|dr|way|close|crescent|place|plaza|hotel|terminal|postcode|tw\d|kt\d|sw\d|se\d|e\d|w\d|n\d)\b/i;
  if (locationWords.test(text)) return true;

  const assistantAskedForLocation = /\b(pickup|pick-up|drop-?off|address|location|where from|where to)\b/i.test(assistantMessage);
  return assistantAskedForLocation && /^[a-z][a-z\s,'-]{2,}$/i.test(text);
}

function asksForPickupThenDropoff(assistantMessage = '') {
  return /\bpickup\b/i.test(assistantMessage)
    && /\bdrop-?off\b/i.test(assistantMessage)
    && assistantMessage.toLowerCase().indexOf('pickup') < assistantMessage.toLowerCase().search(/drop-?off/i);
}

function isClarifyingExistingPickup(assistantMessage = '') {
  const text = assistantMessage.toLowerCase();
  const asksForClarification = /\b(which|what|clarify|mean|exact|specific)\b/.test(text);
  const referencesPickup = /\bpickup\b/.test(text) || /\bfrom\b/.test(text);
  const asksForNewDropoff = /\b(drop-?off|destination|other address|other end|where to)\b/.test(text);

  return asksForClarification && referencesPickup && !asksForNewDropoff;
}

export function alignLocationExtraction(session, userMessage, extraction, structured) {
  if (structured) return extraction;

  const current = session.booking || {};
  const assistantMessage = latestAssistantMessage(session);
  const booking = { ...(extraction.booking || {}) };

  if (!booking.pickupLocation && !booking.dropoffLocation) return extraction;

  // Explicit directions and complete routes take precedence over answer order.
  if (/\b(from|to|pickup|pick-up|dropoff|drop-off|destination|change|instead)\b/i.test(userMessage)
    || (booking.pickupLocation && booking.dropoffLocation)) {
    return extraction;
  }

  const locationText = normalizeLocationText(userMessage);
  if (!looksLikeLocationAnswer(locationText, assistantMessage)) {
    return extraction;
  }

  if (current.pickupLocation && !current.dropoffLocation && isClarifyingExistingPickup(assistantMessage)) {
    booking.pickupLocation = booking.pickupLocation || booking.dropoffLocation || locationText;
    delete booking.dropoffLocation;
    return { ...extraction, booking };
  }

  if (!current.pickupLocation && !current.dropoffLocation && asksForPickupThenDropoff(assistantMessage)) {
    const firstLocation = booking.pickupLocation || booking.dropoffLocation || locationText;
    booking.pickupLocation = firstLocation;
    delete booking.dropoffLocation;
    return { ...extraction, booking };
  }

  if (current.pickupLocation && !current.dropoffLocation) {
    if (booking.pickupLocation && !booking.dropoffLocation && booking.pickupLocation !== current.pickupLocation) {
      booking.dropoffLocation = booking.pickupLocation;
      delete booking.pickupLocation;
    }
  }

  return { ...extraction, booking };
}

function applyExtraction(session, extraction) {
  const customer = {
    ...session.customer,
    ...(extraction.customer || {}),
  };

  const booking = { ...(extraction.booking || {}) };
  const today = londonDateTime().slice(0, 10);

  if (booking.pickupDate && booking.pickupDate < today) {
    delete booking.pickupDate;
  }

  if (booking.returnDate && booking.returnDate < today) {
    delete booking.returnDate;
  }

  if (booking.pickupLocation && booking.pickupLocation !== session.booking?.pickupLocation) {
    booking.pickupPlace = null;
  }

  if (booking.dropoffLocation && booking.dropoffLocation !== session.booking?.dropoffLocation) {
    booking.dropoffPlace = null;
  }

  if (booking.viaLocation && booking.viaLocation !== session.booking?.viaLocation) {
    booking.viaPlace = null;
  }

  return updateSession(session, {
    customer,
    booking,
  });
}

function applyStructuredInput(session, structured) {
  if (!structured || structured.type !== 'location') {
    return session;
  }

  const placeFields = {
    pickupLocation: 'pickupPlace',
    dropoffLocation: 'dropoffPlace',
    viaLocation: 'viaPlace',
  };

  const placeField = placeFields[structured.field];
  if (!placeField) {
    return session;
  }

  return updateSession(session, {
    booking: {
      [structured.field]: structured.address,
      [placeField]: {
        address: structured.address,
        placeId: structured.placeId || '',
        postcode: structured.postcode || '',
        lat: structured.lat ?? null,
        lng: structured.lng ?? null,
        source: 'google_places',
      },
    },
  });
}

function isConfirmationMessage(message) {
  const normalized = message.trim().toLowerCase();
  return /^(confirm|confirmed|yes confirm|yes book|book it|please book|go ahead|submit|send it|okay book|ok book)\b/i.test(normalized)
    || /\b(just book|book it|please book|go ahead and book|submit booking|send booking)\b/i.test(normalized);
}

function missingFieldQuestion(missingFields) {
  const questions = {
    'phone number': 'I can book it after I have your phone number. What is the best number to reach you on?',
    'pickup location': 'I can book it after I have the pickup location. What is your pickup address?',
    'drop-off location': 'I can book it after I have the drop-off location. What is your drop-off address?',
    'pickup date': 'I can book it after I have the pickup date. What date do you need the taxi?',
    'pickup time': 'I can book it after I have the pickup time. What time do you need the taxi?',
    'different drop-off location': 'Pickup and drop-off are currently the same. What is the correct drop-off address?',
    'future pickup date and time': 'That pickup time is in the past. What future date and time would you like?',
    'return date': 'What date would you like the return journey?',
    'return time': 'What time would you like the return journey?',
    'return after pickup': 'The return needs to be after your outward pickup. What return date and time would you like?',
  };

  return questions[missingFields[0]] || `I can book it after I have your ${missingFields[0]}.`;
}

function bookingCreatedMessage(wordpressBooking) {
  const emailText = wordpressBooking.email_sent
    ? 'I have also sent the booking email to you and the office.'
    : 'The booking was created, but the email send did not confirm. The office can still see the booking.';

  return `Your booking request has been created. Reference #${wordpressBooking.id}. ${emailText} The team will confirm availability and price shortly.`;
}

async function confirmSessionBooking(session) {
  const missingFields = requiredMissingFields(session);
  if (missingFields.length > 0) {
    return {
      session,
      confirmed: false,
      message: missingFieldQuestion(missingFields),
    };
  }

  if (session.status === 'booking_requested' && session.wordpressBookingId) {
    return {
      session,
      confirmed: true,
      message: `Your booking request is already created. Reference #${session.wordpressBookingId}.`,
    };
  }

  const wordpressPayload = toWordPressBooking(session);
  const wordpressBooking = await createWordPressBooking(session.site, wordpressPayload);
  const supabaseBooking = await saveBookingCopy(session, wordpressBooking);

  const updated = updateSession(session, {
    status: 'booking_requested',
    wordpressBookingId: wordpressBooking.id,
    supabaseBookingId: supabaseBooking?.id || null,
  });
  await saveSessionSnapshot(updated);

  return {
    session: updated,
    confirmed: true,
    message: bookingCreatedMessage(wordpressBooking),
  };
}

async function getPersistentSession(sessionId) {
  try {
    return getSession(sessionId);
  } catch (error) {
    if (error.code !== 'session_not_found') {
      throw error;
    }

    const restored = await loadSessionSnapshot(sessionId);
    if (restored) {
      return putSession(restored);
    }

    throw error;
  }
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
  let session = await getPersistentSession(input.sessionId);

  appendMessage(session, 'user', input.message);
  await saveMessage(session, 'user', input.message);

  session = applyStructuredInput(session, input.structured);

  const extraction = input.structured || /^(confirm|confirmed|yes confirm|book it|go ahead)[.!\s]*$/i.test(input.message)
    ? { customer: {}, booking: {} }
    : alignLocationExtraction(session, input.message, await extractBookingFields(session, input.message));
  session = applyExtraction(session, extraction);

  if (isConfirmationMessage(input.message)) {
    const result = await confirmSessionBooking(session);
    session = result.session;
    appendMessage(session, 'assistant', result.message);
    await saveMessage(session, 'assistant', result.message);
    await saveSessionSnapshot(session);

    return res.json({
      ok: true,
      sessionId: session.id,
      message: result.message,
      booking: session.booking,
      customer: session.customer,
      missingFields: requiredMissingFields(session),
      confirmed: result.confirmed,
    });
  }

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
  let session = await getPersistentSession(input.sessionId);

  appendMessage(session, 'user', input.message);
  await saveMessage(session, 'user', input.message);

  session = applyStructuredInput(session, input.structured);

  const extraction = input.structured
    ? { customer: {}, booking: {} }
    : alignLocationExtraction(session, input.message, await extractBookingFields(session, input.message));
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
