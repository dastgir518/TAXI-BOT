import { Router } from 'express';

import { asyncRoute } from '../services/async-route.js';
import { getSession, updateSession } from '../services/sessions.js';
import { getWordPressVehicles, createWordPressBooking } from '../services/wordpress.js';
import { saveBookingCopy, saveSessionSnapshot } from '../services/supabase.js';
import { confirmBookingSchema, requiredMissingFields, toWordPressBooking } from '../schemas/booking.js';

const router = Router();

router.get('/:sessionId/vehicles', asyncRoute(async (req, res) => {
  const session = getSession(req.params.sessionId);
  const vehicles = await getWordPressVehicles(session.site);

  res.json({
    ok: true,
    vehicles,
  });
}));

router.post('/confirm', asyncRoute(async (req, res) => {
  const input = confirmBookingSchema.parse(req.body);
  let session = getSession(input.sessionId);

  if (input.booking) {
    session = updateSession(session, { booking: input.booking });
  }

  const missingFields = requiredMissingFields(session);
  if (missingFields.length > 0) {
    const error = new Error(`Booking is missing: ${missingFields.join(', ')}`);
    error.statusCode = 400;
    error.code = 'booking_missing_fields';
    throw error;
  }

  const wordpressPayload = toWordPressBooking(session);
  const wordpressBooking = await createWordPressBooking(session.site, wordpressPayload);
  const supabaseBooking = await saveBookingCopy(session, wordpressBooking);

  session = updateSession(session, {
    status: 'booking_requested',
    wordpressBookingId: wordpressBooking.id,
    supabaseBookingId: supabaseBooking?.id || null,
  });
  await saveSessionSnapshot(session);

  res.json({
    ok: true,
    message: 'Booking request created. The team will confirm availability shortly.',
    booking: {
      wordpress: wordpressBooking,
      supabase: supabaseBooking,
    },
  });
}));

export default router;

