import assert from 'node:assert/strict';
import test from 'node:test';
import { londonDateTime, validDate, validTime } from '../src/services/booking-time.js';
import { requiredMissingFields } from '../src/schemas/booking.js';

const session = { customer: { phone: '07700 900123' }, booking: { pickupLocation: 'Station', dropoffLocation: 'Museum', pickupDate: '2099-09-25', pickupTime: '10:00' } };
test('London date handles the summer midnight boundary', () => {
  assert.equal(londonDateTime(new Date('2026-09-20T23:30:00Z')), '2026-09-21T00:30');
  assert.equal(londonDateTime(new Date('2026-12-20T23:30:00Z')), '2026-12-20T23:30');
});
test('reject invalid calendar dates and times', () => {
  assert.equal(validDate('2026-02-30'), false);
  assert.equal(validDate('2028-02-29'), true);
  assert.equal(validTime('25:00'), false);
  assert.equal(validTime('07:30'), true);
});
test('optional fields do not block a complete outward trip', () => {
  assert.deepEqual(requiredMissingFields(session), []);
});
test('missing phone and identical addresses block confirmation', () => {
  assert.ok(requiredMissingFields({ ...session, customer: { phone: 'hello' } }).includes('phone number'));
  assert.ok(requiredMissingFields({ ...session, booking: { ...session.booking, dropoffLocation: ' Station. ' } }).includes('different drop-off location'));
});
test('past pickups and incomplete returns are blocked', () => {
  assert.ok(requiredMissingFields({ ...session, booking: { ...session.booking, pickupDate: '2020-01-01' } }).includes('future pickup date and time'));
  assert.deepEqual(requiredMissingFields({ ...session, booking: { ...session.booking, isReturnJourney: true } }), ['return date', 'return time']);
  assert.ok(requiredMissingFields({ ...session, booking: { ...session.booking, isReturnJourney: true, returnDate: '2099-09-24', returnTime: '10:00' } }).includes('return after pickup'));
});
