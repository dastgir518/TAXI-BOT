export function notFoundHandler(req, _res, next) {
  const error = new Error(`Route not found: ${req.method} ${req.originalUrl}`);
  error.statusCode = 404;
  next(error);
}

export function errorHandler(error, _req, res, _next) {
  if (error.name === 'ZodError') {
    return res.status(400).json({
      ok: false,
      error: {
        message: 'Invalid request data',
        code: 'validation_error',
        issues: error.issues,
      },
    });
  }

  const statusCode = error.statusCode || 500;

  if (statusCode >= 500) {
    console.error(error);
  }

  res.status(statusCode).json({
    ok: false,
    error: {
      message: error.message || 'Unexpected server error',
      code: error.code || 'server_error',
    },
  });
}
