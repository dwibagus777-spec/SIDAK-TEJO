# SIDAK TEJO - API Guide (OpenAPI 3 / REST API)

## Overview
SIDAK TEJO Enterprise Integration Platform (EIP) provides versioned REST API (`v1`, `v2`, `v3`) with OpenAPI 3 specification and Swagger UI documentation.

- **Swagger UI Interactive Docs**: `/api/docs/ui`
- **OpenAPI 3 JSON Spec**: `/api/docs/json`
- **Health Check Endpoint**: `/api/health`

## Authentication
Supports 2 primary authentication schemes:
1. **JWT (Bearer Token)**:
   - Request token: `POST /api/v1/auth/login`
   - Send header: `Authorization: Bearer <JWT_TOKEN>`
2. **X-API-Key Header**:
   - Generate key via Integration Center (`/integration`)
   - Send header: `X-API-Key: stj_...`

## Rate Limiting & Audit Logging
All requests are rate-limited per user / API key / IP (default 1000 requests/hour) and audited in `api_logs`.
