import assert from 'node:assert/strict';
import test from 'node:test';
import { alignLocationExtraction } from '../src/routes/chat.js';

const initial = { booking: {}, messages: [{ role: 'assistant', content: 'What is your pickup location and drop-off location?' }] };

test('first short location answer fills pickup', () => {
  const result = alignLocationExtraction(initial, 'London Bridge', { booking: { dropoffLocation: 'London Bridge' } });
  assert.deepEqual(result.booking, { pickupLocation: 'London Bridge' });
});

test('both locations in one message are retained', () => {
  const extraction = { booking: { pickupLocation: 'Heathrow', dropoffLocation: 'London Bridge' } };
  assert.deepEqual(alignLocationExtraction(initial, 'Heathrow and London Bridge', extraction), extraction);
});

test('explicit drop-off takes precedence over question order', () => {
  const extraction = { booking: { dropoffLocation: 'London Bridge' } };
  assert.deepEqual(alignLocationExtraction(initial, 'My drop-off is London Bridge', extraction), extraction);
});

test('second short address fills drop-off', () => {
  const session = { booking: { pickupLocation: 'Heathrow' }, messages: [{ role: 'assistant', content: 'What is your drop-off address?' }] };
  assert.deepEqual(alignLocationExtraction(session, 'London Bridge', { booking: { pickupLocation: 'London Bridge' } }).booking, { dropoffLocation: 'London Bridge' });
});

test('pickup clarification stays with pickup', () => {
  const session = { booking: { pickupLocation: 'London' }, messages: [{ role: 'assistant', content: 'Which pickup address in London do you mean?' }] };
  assert.deepEqual(alignLocationExtraction(session, 'London Bridge', { booking: { dropoffLocation: 'London Bridge' } }).booking, { pickupLocation: 'London Bridge' });
});

test('questions and skipped answers do not become addresses', () => {
  for (const message of ['can you trigger search', 'not sure', 'skip']) {
    assert.deepEqual(alignLocationExtraction(initial, message, { booking: {} }).booking, {});
  }
});

test('structured selection is preserved', () => {
  const extraction = { booking: { dropoffLocation: 'London Bridge' } };
  assert.deepEqual(alignLocationExtraction(initial, 'London Bridge', extraction, { type: 'location' }), extraction);
});
