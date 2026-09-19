import OpenAI from 'openai';

import { config, requireConfig } from './config.js';
import { requiredMissingFields } from '../schemas/booking.js';

let client;

function getClient() {
  requireConfig(config.deepseek.apiKey, 'DeepSeek API key is not configured');

  if (!client) {
    client = new OpenAI({
      apiKey: config.deepseek.apiKey,
      baseURL: config.deepseek.baseURL,
    });
  }

  return client;
}

function systemPrompt(session) {
  return [
    `You are the booking assistant for ${session.site.name}.`,
    'You collect taxi booking requests in natural language.',
    'Keep replies short and friendly.',
    'Ask only for missing details. Prefer one or two questions at a time.',
    'Never promise final driver availability or final price.',
    'When all details are present, summarize and ask the customer to confirm.',
    'Required details: phone, pickup, drop-off, pickup date, pickup time, passengers, luggage, special notes if any.',
    'If the trip mentions an airport, ask for flight number, terminal if known, and whether meet-and-greet is needed.',
  ].join('\n');
}

function conversationMessages(session) {
  const recent = session.messages.slice(-12).map(({ role, content }) => ({ role, content }));
  const missing = requiredMissingFields(session);

  return [
    { role: 'system', content: systemPrompt(session) },
    {
      role: 'system',
      content: `Known customer: ${JSON.stringify(session.customer)}\nKnown booking draft: ${JSON.stringify(session.booking)}\nMissing fields: ${missing.join(', ') || 'none'}`,
    },
    ...recent,
  ];
}

function questionForMissingField(missingFields) {
  const field = missingFields[0];
  const questions = {
    'phone number': 'Thanks, what phone number should we use for the booking?',
    'pickup location': 'Thanks, what is your pickup location?',
    'drop-off location': 'Thanks, what is your drop-off address?',
    'pickup date': 'Thanks, what date do you need the taxi?',
    'pickup time': 'Thanks, what pickup time do you need?',
    'number of passengers': 'Thanks, how many passengers are travelling?',
    luggage: 'Thanks, how many large suitcases will you have?',
    'hand luggage': 'Thanks, how many pieces of hand luggage will you have?',
  };

  return questions[field] || `Thanks, could you share your ${field}?`;
}

function keepConversationMoving(reply, session) {
  const missing = requiredMissingFields(session);

  if (missing.length === 0) {
    return reply;
  }

  const text = (reply || '').trim();
  const asksQuestion = text.includes('?');
  const soundsComplete = /\b(updated|complete|confirmed|ready|all set)\b/i.test(text);

  if (!text || !asksQuestion || soundsComplete) {
    return questionForMissingField(missing);
  }

  return text;
}

export async function streamAssistantReply(session, onToken) {
  const stream = await getClient().chat.completions.create({
    model: config.deepseek.model,
    messages: conversationMessages(session),
    stream: true,
    temperature: 0.2,
  });

  let fullText = '';
  for await (const chunk of stream) {
    const token = chunk.choices?.[0]?.delta?.content || '';
    if (!token) continue;
    fullText += token;
    onToken(token);
  }

  return fullText;
}

export async function createAssistantReply(session) {
  const completion = await getClient().chat.completions.create({
    model: config.deepseek.model,
    messages: conversationMessages(session),
    stream: false,
    temperature: 0.2,
  });

  const reply = completion.choices?.[0]?.message?.content?.trim() || '';
  return keepConversationMoving(reply, session);
}

export async function extractBookingFields(session, userMessage) {
  const recent = session.messages.slice(-6).map(({ role, content }) => ({ role, content }));

  const completion = await getClient().chat.completions.create({
    model: config.deepseek.model,
    messages: [
      {
        role: 'system',
        content: [
          'Extract taxi booking fields from the user message.',
          'Return only JSON. Do not add markdown.',
          'Use these keys when known: phone, pickupLocation, dropoffLocation, viaLocation, pickupDate, pickupTime, passengers, children, largeSuitcases, handLuggage, childSeats, boosterSeats, specialRequirements, vehicleName, isReturnJourney, returnDate, returnTime, returnPickupLocation, returnDropoffLocation.',
          'Dates should be YYYY-MM-DD when the user gives a clear date. Times should be HH:mm when clear. Leave unknown values out.',
          'Use the recent conversation to resolve short answers.',
          'If the assistant just asked which London airport and the user names an airport, update the airport-related field that was being clarified. Do not treat that airport name as the drop-off address unless the user explicitly says it is the drop-off.',
          'Do not infer missing pickup or drop-off locations from a partial answer.',
        ].join('\n'),
      },
      {
        role: 'user',
        content: `Current draft: ${JSON.stringify(session.booking)}\nCustomer: ${JSON.stringify(session.customer)}\nRecent conversation: ${JSON.stringify(recent)}\nUser message: ${userMessage}`,
      },
    ],
    response_format: { type: 'json_object' },
    stream: false,
    temperature: 0,
  });

  const raw = completion.choices?.[0]?.message?.content || '{}';

  try {
    const parsed = JSON.parse(raw);
    const { phone, ...booking } = parsed;
    return { customer: phone ? { phone: String(phone) } : {}, booking };
  } catch {
    return { customer: {}, booking: {} };
  }
}
