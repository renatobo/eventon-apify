# Production Test Scaffold

This repo includes a lightweight production smoke suite for the protected RSVP API.

## Setup

1. Copy [.env.production.example](/Users/renatobo/development/eventon-apify/.env.production.example) to `.env.production.local`.
2. Fill in:
   - `EVENTON_APIFY_BASE_URL`
   - `EVENTON_APIFY_USERNAME`
   - `EVENTON_APIFY_APP_PASSWORD` for Basic Auth environments
   - or `EVENTON_APIFY_PASSWORD` for wp-login cookie auth environments
   - `EVENTON_APIFY_EVENT_ID`
3. Optionally set:
   - `EVENTON_APIFY_DELTA_UPDATED_AFTER`
   - `EVENTON_APIFY_DELTA_UPDATED_AFTER_ID`

The local env file is ignored by git.

## Run

```bash
npm run test:prod
```

To use a different env file:

```bash
EVENTON_APIFY_ENV_FILE=/absolute/path/to/file npm run test:prod
```

For the local WordPress container on `http://localhost:8089`:

```bash
npm run test:dev
```

## Coverage

- full RSVP list smoke test
- delta RSVP smoke test
- invalid checkpoint validation smoke test

These tests target a real environment and are intentionally read-only.

## WordPress 7 integration

CI installs WordPress 7.0.2 with MySQL and runs
`tests/integration/wp-rest-smoke.php`. That smoke test verifies plugin startup,
REST route registration, administrator authorization, protected MCP discovery,
write schemas, and compensating rollback after a partial write failure. The
fixture supplies the EventON post type and taxonomies; production smoke testing
remains responsible for compatibility with the proprietary EventON runtime.
