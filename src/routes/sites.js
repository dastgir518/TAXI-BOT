import { Router } from 'express';

import { listSites } from '../services/site-registry.js';

const router = Router();

router.get('/', (_req, res) => {
  res.json({ ok: true, sites: listSites() });
});

export default router;

