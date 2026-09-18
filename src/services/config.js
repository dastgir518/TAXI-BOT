const truthy = new Set(['1', 'true', 'yes', 'on']);

function readSites() {
  return {
    'taxiwaltononthames.co.uk': {
      key: 'walton',
      name: 'Taxi Walton-on-Thames',
      wordpressUrl: process.env.WORDPRESS_WALTON_URL || 'https://taxiwaltononthames.co.uk',
    },
    'taxiweybridge.co.uk': {
      key: 'weybridge',
      name: 'Taxi Weybridge',
      wordpressUrl: process.env.WORDPRESS_WEYBRIDGE_URL || 'https://taxiweybridge.co.uk',
    },
  };
}

function readCorsOrigins() {
  if (process.env.CORS_ORIGINS) {
    return process.env.CORS_ORIGINS.split(',').map((origin) => origin.trim()).filter(Boolean);
  }

  return [
    'https://taxiwaltononthames.co.uk',
    'https://www.taxiwaltononthames.co.uk',
    'https://taxiweybridge.co.uk',
    'https://www.taxiweybridge.co.uk',
    'http://localhost:3000',
    'http://localhost:5173',
  ];
}

export const config = {
  port: Number(process.env.PORT || 3000),
  nodeEnv: process.env.NODE_ENV || 'development',
  corsOrigins: readCorsOrigins(),
  deepseek: {
    apiKey: process.env.DEEPSEEK_API_KEY || '',
    baseURL: process.env.DEEPSEEK_BASE_URL || 'https://api.deepseek.com',
    model: process.env.DEEPSEEK_MODEL || 'deepseek-flash',
  },
  supabase: {
    url: process.env.SUPABASE_URL || '',
    serviceRoleKey: process.env.SUPABASE_SECRET_KEY || process.env.SUPABASE_SERVICE_ROLE_KEY || '',
    anonKey: process.env.SUPABASE_PUBLISHABLE_KEY || process.env.SUPABASE_ANON_KEY || '',
  },
  wordpress: {
    aiSecret: process.env.WORDPRESS_AI_SECRET || '',
    sites: readSites(),
  },
  telegram: {
    enabled: truthy.has(String(process.env.TELEGRAM_ENABLED || '').toLowerCase()),
    botToken: process.env.TELEGRAM_BOT_TOKEN || '',
    adminChatId: process.env.TELEGRAM_ADMIN_CHAT_ID || '',
  },
};

export function publicConfig() {
  return {
    nodeEnv: config.nodeEnv,
    deepseekConfigured: Boolean(config.deepseek.apiKey),
    supabaseConfigured: Boolean(config.supabase.url && config.supabase.serviceRoleKey),
    supabaseEnv: {
      SUPABASE_URL: envPresence('SUPABASE_URL'),
      SUPABASE_SECRET_KEY: envPresence('SUPABASE_SECRET_KEY'),
      SUPABASE_SERVICE_ROLE_KEY: envPresence('SUPABASE_SERVICE_ROLE_KEY'),
      SUPABASE_PUBLISHABLE_KEY: envPresence('SUPABASE_PUBLISHABLE_KEY'),
      SUPABASE_ANON_KEY: envPresence('SUPABASE_ANON_KEY'),
    },
    wordpressConfigured: Boolean(config.wordpress.aiSecret),
    telegramEnabled: config.telegram.enabled,
    sites: Object.values(config.wordpress.sites).map(({ key, name, wordpressUrl }) => ({
      key,
      name,
      wordpressUrl,
    })),
  };
}

function envPresence(name) {
  const value = process.env[name] || '';
  return {
    present: value.length > 0,
    length: value.length,
  };
}

export function requireConfig(condition, message) {
  if (!condition) {
    const error = new Error(message);
    error.statusCode = 503;
    throw error;
  }
}
