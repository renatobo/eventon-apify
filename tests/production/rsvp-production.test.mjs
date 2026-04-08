import test from 'node:test';
import assert from 'node:assert/strict';

const requiredEnvKeys = [
  'EVENTON_APIFY_BASE_URL',
  'EVENTON_APIFY_USERNAME',
  'EVENTON_APIFY_EVENT_ID',
];

for (const key of requiredEnvKeys) {
  assert.ok(process.env[key], `Missing required environment variable: ${key}`);
}

const baseUrl = process.env.EVENTON_APIFY_BASE_URL.replace(/\/+$/, '');
const eventId = process.env.EVENTON_APIFY_EVENT_ID;
const authMode = process.env.EVENTON_APIFY_APP_PASSWORD ? 'basic' : 'cookie';

if (authMode === 'cookie') {
  assert.ok(process.env.EVENTON_APIFY_PASSWORD, 'Missing required environment variable: EVENTON_APIFY_PASSWORD');
}

const authHeader = process.env.EVENTON_APIFY_APP_PASSWORD
  ? `Basic ${Buffer.from(
      `${process.env.EVENTON_APIFY_USERNAME}:${process.env.EVENTON_APIFY_APP_PASSWORD}`
    ).toString('base64')}`
  : null;

function buildUrl(pathname, params = {}) {
  const url = new URL(`${baseUrl}${pathname}`);
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, String(value));
    }
  }
  return url;
}

async function apiGet(pathname, params = {}) {
  const headers = {
    Accept: 'application/json',
  };

  if (authMode === 'basic') {
    headers.Authorization = authHeader;
  } else {
    const session = await getCookieAuthSession();
    headers.Cookie = session.cookieHeader;
    headers['X-WP-Nonce'] = session.restNonce;
  }

  const response = await fetch(buildUrl(pathname, params), { headers });

  const text = await response.text();
  let body;

  try {
    body = text === '' ? null : JSON.parse(text);
  } catch (error) {
    throw new Error(`Expected JSON response from ${pathname}, got: ${text}`, { cause: error });
  }

  return { response, body };
}

let cookieAuthSessionPromise;

async function getCookieAuthSession() {
  if (!cookieAuthSessionPromise) {
    cookieAuthSessionPromise = createCookieAuthSession();
  }

  return cookieAuthSessionPromise;
}

async function createCookieAuthSession() {
  const loginUrl = new URL('/wp-login.php', `${baseUrl}/`);
  const redirectTo = `${baseUrl}/wp-admin/index.php`;

  const loginPage = await fetch(loginUrl, {
    headers: { Accept: 'text/html' },
  });

  const initialCookies = collectCookies(loginPage);
  assert.equal(loginPage.status, 200, 'Failed to load wp-login.php');

  const form = new URLSearchParams({
    log: process.env.EVENTON_APIFY_USERNAME,
    pwd: process.env.EVENTON_APIFY_PASSWORD,
    'wp-submit': 'Log In',
    redirect_to: redirectTo,
    testcookie: '1',
  });

  const loginResponse = await fetch(loginUrl, {
    method: 'POST',
    headers: {
      Accept: 'text/html',
      'Content-Type': 'application/x-www-form-urlencoded',
      Cookie: formatCookieHeader(initialCookies),
    },
    body: form.toString(),
    redirect: 'manual',
  });

  const authCookies = mergeCookies(initialCookies, collectCookies(loginResponse));
  assert.ok([302, 303].includes(loginResponse.status), `Unexpected login status: ${loginResponse.status}`);

  const adminResponse = await fetch(new URL('/wp-admin/index.php', `${baseUrl}/`), {
    headers: {
      Accept: 'text/html',
      Cookie: formatCookieHeader(authCookies),
    },
  });

  const adminHtml = await adminResponse.text();
  assert.equal(adminResponse.status, 200, 'Failed to load wp-admin dashboard after login');

  const restNonceMatch = adminHtml.match(/wpApiSettings\s*=\s*\{"root":"[^"]+","nonce":"([^"]+)"/);
  assert.ok(restNonceMatch, 'Could not find wpApiSettings nonce on wp-admin dashboard');

  return {
    cookieHeader: formatCookieHeader(authCookies),
    restNonce: restNonceMatch[1],
  };
}

function collectCookies(response) {
  const setCookie = response.headers.getSetCookie?.() ?? [];
  const cookies = new Map();

  for (const header of setCookie) {
    const [pair] = header.split(';', 1);
    const separatorIndex = pair.indexOf('=');
    if (separatorIndex === -1) {
      continue;
    }

    const name = pair.slice(0, separatorIndex);
    const value = pair.slice(separatorIndex + 1);
    cookies.set(name, value);
  }

  return cookies;
}

function mergeCookies(...maps) {
  const merged = new Map();

  for (const map of maps) {
    for (const [key, value] of map.entries()) {
      merged.set(key, value);
    }
  }

  return merged;
}

function formatCookieHeader(cookies) {
  return Array.from(cookies.entries())
    .map(([key, value]) => `${key}=${value}`)
    .join('; ');
}

test('RSVP endpoint returns a paginated attendee collection', async () => {
  const { response, body } = await apiGet(`/wp-json/eventonapify/v1/events/${eventId}/rsvps`, {
    per_page: 5,
    page: 1,
  });

  assert.equal(response.status, 200, JSON.stringify(body));
  assert.equal(typeof body.total, 'number');
  assert.equal(typeof body.pages, 'number');
  assert.equal(body.page, 1);
  assert.equal(body.per_page, 5);
  assert.ok(Array.isArray(body.attendees), 'attendees should be an array');

  for (const attendee of body.attendees) {
    assert.equal(typeof attendee.id, 'number');
    assert.equal(typeof attendee.created_at, 'string');
    assert.equal(typeof attendee.updated_at, 'string');
  }
});

test('RSVP delta endpoint returns checkpoint metadata', async () => {
  const updatedAfter = process.env.EVENTON_APIFY_DELTA_UPDATED_AFTER ?? '1970-01-01T00:00:00Z';
  const updatedAfterId = process.env.EVENTON_APIFY_DELTA_UPDATED_AFTER_ID ?? '0';

  const { response, body } = await apiGet(`/wp-json/eventonapify/v1/events/${eventId}/rsvps`, {
    per_page: 5,
    page: 1,
    updated_after: updatedAfter,
    updated_after_id: updatedAfterId,
  });

  assert.equal(response.status, 200, JSON.stringify(body));
  assert.equal(typeof body.has_more, 'boolean');
  assert.ok(body.sync_checkpoint, 'sync_checkpoint should be present');
  assert.equal(typeof body.sync_checkpoint.updated_at, 'string');
  assert.equal(typeof body.sync_checkpoint.id, 'number');

  let previousUpdatedAt = '';
  let previousId = Number(updatedAfterId);

  for (const attendee of body.attendees) {
    assert.equal(typeof attendee.updated_at, 'string');
    const currentUpdatedAt = attendee.updated_at;
    const currentId = attendee.id;

    assert.ok(
      currentUpdatedAt > previousUpdatedAt || (currentUpdatedAt === previousUpdatedAt && currentId > previousId),
      `Attendees are not sorted by updated_at ASC, id ASC: ${currentUpdatedAt}#${currentId}`
    );

    previousUpdatedAt = currentUpdatedAt;
    previousId = currentId;
  }
});

test('RSVP delta endpoint rejects invalid updated_after values', async () => {
  const { response, body } = await apiGet(`/wp-json/eventonapify/v1/events/${eventId}/rsvps`, {
    updated_after: 'not-a-date',
  });

  assert.equal(response.status, 400, JSON.stringify(body));
  assert.equal(body.code, 'eventon_apify_invalid_updated_after');
});

test('RSVP delta endpoint rejects stateful attendee filters', async () => {
  const { response, body } = await apiGet(`/wp-json/eventonapify/v1/events/${eventId}/rsvps`, {
    updated_after: '1970-01-01T00:00:00Z',
    rsvp: 'yes',
  });

  assert.equal(response.status, 400, JSON.stringify(body));
  assert.equal(body.code, 'eventon_apify_invalid_rsvp_delta_filters');
});
