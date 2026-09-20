import { z } from 'zod';
import { londonDateTime, validDate, validTime } from '../services/booking-time.js';

export const customerSchema = z.object({
  name: z.string().trim().min(2),
  email: z.string().trim().email(),
  phone: z.string().trim().min(5).optional().or(z.literal('')),
});

export const locationPlaceSchema = z.object({
  address: z.string().trim().min(1),
  placeId: z.string().trim().optional().default(''),
  postcode: z.string().trim().optional().default(''),
  lat: z.number().optional().nullable().default(null),
  lng: z.number().optional().nullable().default(null),
  source: z.string().trim().optional().default('google_places'),
});

export const bookingDraftSchema = z.object({
  pickupLocation: z.string().trim().optional().default(''),
  pickupPlace: locationPlaceSchema.optional().nullable().default(null),
  dropoffLocation: z.string().trim().optional().default(''),
  dropoffPlace: locationPlaceSchema.optional().nullable().default(null),
  viaLocation: z.string().trim().optional().default(''),
  viaPlace: locationPlaceSchema.optional().nullable().default(null),
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

export const structuredLocationSchema = z.object({
  type: z.literal('location'),
  field: z.enum(['pickupLocation', 'dropoffLocation', 'viaLocation']),
  address: z.string().trim().min(1),
  placeId: z.string().trim().optional().default(''),
  postcode: z.string().trim().optional().default(''),
  lat: z.number().optional().nullable().default(null),
  lng: z.number().optional().nullable().default(null),
});

export const messageSchema = z.object({
  sessionId: z.string().trim().min(8),
  message: z.string().trim().min(1).max(2000),
  structured: structuredLocationSchema.optional(),
});

export const confirmBookingSchema = z.object({
  sessionId: z.string().trim().min(8),
  booking: bookingDraftSchema.optional(),
});

export function normalizeDraft(input = {}) {
  return bookingDraftSchema.parse(input);
}

function normalizeLocationForCompare(value = '') {
  return String(value)
    .trim()
    .replace(/\s+/g, ' ')
    .replace(/[.,]+$/g, '')
    .toLowerCase();
}

export function requiredMissingFields(session) {
  const booking = session.booking || {};
  const missing = [];

  const phone = String(session.customer?.phone || '');
  if (!/^[+\d\s().-]+$/.test(phone) || !/^\d{7,15}$/.test(phone.replace(/\D/g, ''))) missing.push('phone number');
  if (!booking.pickupLocation) missing.push('pickup location');
  if (!booking.dropoffLocation) missing.push('drop-off location');
  if (!validDate(booking.pickupDate)) missing.push('pickup date');
  if (!validTime(booking.pickupTime)) missing.push('pickup time');
  if (validDate(booking.pickupDate) && validTime(booking.pickupTime)
    && `${booking.pickupDate}T${booking.pickupTime}` <= londonDateTime()) {
    missing.push('future pickup date and time');
  }
  if (booking.isReturnJourney) {
    if (!validDate(booking.returnDate)) missing.push('return date');
    if (!validTime(booking.returnTime)) missing.push('return time');
    if (validDate(booking.returnDate) && validTime(booking.returnTime)
      && `${booking.returnDate}T${booking.returnTime}` <= `${booking.pickupDate}T${booking.pickupTime}`) {
      missing.push('return after pickup');
    }
  }
  if (
    booking.pickupLocation
    && booking.dropoffLocation
    && normalizeLocationForCompare(booking.pickupLocation) === normalizeLocationForCompare(booking.dropoffLocation)
  ) {
    missing.push('different drop-off location');
  }

  return missing;
}

export function optionalMissingFields(session) {
  const booking = session.booking || {};
  const missing = [];

  if (!booking.passengers) missing.push('number of passengers');
  if (booking.largeSuitcases === undefined || booking.largeSuitcases === null) missing.push('large suitcases');
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
