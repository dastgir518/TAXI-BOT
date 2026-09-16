import 'dotenv/config';
import express from 'express';
import cors from 'cors';

import { config, publicConfig } from './services/config.js';
import { errorHandler, notFoundHandler } from './services/errors.js';
import chatRouter from './routes/chat.js';
import bookingRouter from './routes/bookings.js';
import siteRouter from './routes/sites.js';

const app = express();

app.set('trust proxy', 1);

app.use(cors({ origin: config.corsOrigins, credentials: true }));
app.use(express.json({ limit: '1mb' }));

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    service: 'taxi-ai-booking-bot',
    config: publicConfig(),
  });
});

app.use('/api/chat', chatRouter);
app.use('/api/bookings', bookingRouter);
app.use('/api/sites', siteRouter);

app.use(notFoundHandler);
app.use(errorHandler);

app.listen(config.port, () => {
  console.log(`Taxi AI Booking Bot listening on port ${config.port}`);
});
