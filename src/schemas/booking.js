import { z } from 'zod';

export const customerSchema = z.object({
  name: z.string().trim().min(2),
  email: z.string().trim().email(),
  phone: z.string().trim().min(5).optional().or(z.literal('')),
});

export const bookingDraftSchema = z.object({
  pickupLocation: z.string().trim().optional().default(''),
  dropoffLocation: z.string().trim().optional().default(''),
  viaLocation: z.string().trim().optional().default(''),
  pickupDate: z.string().trim().optional().default(''),
  pickupTime: z.string().trim().optional().default(''),
  passengers: z.coerce.number().int().min(1).max(99).optional().nullable().default(null),
  children: z.coerce.number().int().min(0).max(99).optional().nullable().default(null),
  largeSuitcases: z.coerce.number().int().min(0).max(99).optional().nullable().default(null),
  handLuggage: z.coerce.number().int().min(0).max(99).optional().nullable().default(null),
  childSeats: z.coerce.number().int().min(0).max(10).optional().nullable().default(null),
  boosterSeats: z.enum(['Yes', 'No']).optional().default('No'),
  specialRequirements: z.string().trim().optional().default(''),
  vehicleId: z.coerce.number().int().positive().optional().nullable(),
  vehicleName: z.string().trim().optional().default(''),
  isReturnJourney: z.boolean().optional().default(false),
  returnDate: z.string().trim().optional().default(''),
  returnTime: z.string().trim().optional().default(''),
  returnPickupLocation: z.string().trim().optional().default(''),
  returnDropoffLocation: z.string().trim().optional().default(''),
});

export const startChatSchema = z.object({
  source: z.string().trim().min(3),
  customer: customerSchema,
});

export const messageSchema = z.object({
  sessionId: z.string().trim().min(8),
  message: z.string().trim().min(1).max(2000),
});

export const confirmBookingSchema = z.object({
  sessionId: z.string().trim().min(8),
  booking: bookingDraftSchema.optional(),
});

export function normalizeDraft(input = {}) {
  return bookingDraftSchema.parse(input);
}

export function requiredMissingFields(session) {
  const booking = session.booking || {};
  const missing = [];

  if (!session.customer?.phone) missing.push('phone number');
  if (!booking.pickupLocation) missing.push('pickup location');
  if (!booking.dropoffLocation) missing.push('drop-off location');
  if (!booking.pickupDate) missing.push('pickup date');
  if (!booking.pickupTime) missing.push('pickup time');
  if (!booking.passengers) missing.push('number of passengers');
  if (booking.largeSuitcases === undefined || booking.largeSuitcases === null) missing.push('luggage');
  if (booking.handLuggage === undefined || booking.handLuggage === null) missing.push('hand luggage');

  return missing;
}

export function toWordPressBooking(session) {
  const booking = normalizeDraft(session.booking);

  return {
    source_site: session.site.host,
    created_via: 'ai_chat',
    ai_session_id: session.id,
    ai_summary: session.summary || '',
    customer_name: session.customer.name,
    customer_email: session.customer.email,
    customer_phone: session.customer.phone || '',
    booking_date: booking.pickupDate,
    booking_time: booking.pickupTime,
    pickup_location: booking.pickupLocation,
    via_location: booking.viaLocation || '',
    dropoff_location: booking.dropoffLocation,
    no_of_adults: booking.passengers || 1,
    no_of_children: booking.children || 0,
    large_suitcases: booking.largeSuitcases || 0,
    hand_luggage: booking.handLuggage || 0,
    child_seats: booking.childSeats || 0,
    booster_seats: booking.boosterSeats,
    special_requirements: booking.specialRequirements,
    vehicle_id: booking.vehicleId,
    is_return_journey: booking.isReturnJourney ? 1 : 0,
    return_date: booking.returnDate || '',
    return_time: booking.returnTime || '',
    return_pickup_location: booking.returnPickupLocation || '',
    return_dropoff_location: booking.returnDropoffLocation || '',
  };
}
