import { config } from './config.js';

export function normalizeHost(value = '') {
  return String(value)
    .replace(/^https?:\/\//i, '')
    .replace(/^www\./i, '')
    .split('/')[0]
    .split(':')[0]
    .toLowerCase();
}

export function resolveSite(source) {
  const host = normalizeHost(source);
  const site = config.wordpress.sites[host];

  if (!site) {
    const error = new Error(`Unsupported website source: ${source}`);
    error.statusCode = 400;
    error.code = 'unsupported_site';
    throw error;
  }

  return { ...site, host };
}

export function listSites() {
  return Object.entries(config.wordpress.sites).map(([host, site]) => ({
    host,
    key: site.key,
    name: site.name,
    wordpressUrl: site.wordpressUrl,
  }));
}

