import 'dotenv/config';
import express from 'express';
import cors from 'cors';

const app = express();
const port = process.env.PORT || 3000;

app.use(cors());
app.use(express.json({ limit: '1mb' }));

app.get('/health', (_req, res) => {
  res.json({
    ok: true,
    service: 'taxi-ai-booking-bot',
  });
});

app.listen(port, () => {
  console.log(`Taxi AI Booking Bot listening on port ${port}`);
});

