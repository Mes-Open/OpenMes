import { test, expect } from '@playwright/test';
import { createServer, IncomingMessage, Server } from 'node:http';
import { AddressInfo } from 'node:net';
import { createHash, createSign, generateKeyPairSync, randomBytes } from 'node:crypto';

/**
 * #123 — sign in to OpenMES through a company identity provider (OpenID Connect).
 *
 * A tiny OpenID provider runs inside this spec on 127.0.0.1 — discovery, an
 * interactive login page, token (with PKCE check), JWKS and userinfo — so the
 * whole round trip is real: the browser leaves OpenMES, signs in at the
 * provider, comes back, and OpenMES exchanges the code server-side and
 * verifies the RS256-signed ID token.
 */

const ADMIN = process.env.ADMIN_USERNAME || 'admin';
const PASS = process.env.ADMIN_PASSWORD || 'Admin1234!';
const CLIENT_ID = 'openmes';
const CLIENT_SECRET = 'e2e-client-secret';

const b64url = (buf: Buffer | string) => Buffer.from(buf).toString('base64url');

function startMockIdp(): Promise<{ server: Server; issuer: string }> {
  const { privateKey, publicKey } = generateKeyPairSync('rsa', { modulusLength: 2048 });
  const jwk = { ...publicKey.export({ format: 'jwk' }), kid: 'e2e-key', use: 'sig', alg: 'RS256' };
  const codes = new Map<string, { nonce: string; challenge: string }>();
  let issuer = '';

  const body = (req: IncomingMessage) =>
    new Promise<URLSearchParams>((resolve) => {
      let data = '';
      req.on('data', (c) => (data += c));
      req.on('end', () => resolve(new URLSearchParams(data)));
    });

  const signIdToken = (nonce: string) => {
    const now = Math.floor(Date.now() / 1000);
    const input = `${b64url(JSON.stringify({ alg: 'RS256', typ: 'JWT', kid: 'e2e-key' }))}.${b64url(JSON.stringify({
      iss: issuer, aud: CLIENT_ID, sub: 'kc-7f3a-jane', iat: now, exp: now + 300, nonce,
      preferred_username: 'jane.doe', name: 'Jane Doe', email: 'jane.doe@plant.example', email_verified: true,
    }))}`;
    return `${input}.${b64url(createSign('RSA-SHA256').update(input).sign(privateKey))}`;
  };

  const server = createServer(async (req, res) => {
    const url = new URL(req.url || '/', issuer);
    const json = (status: number, payload: unknown) => {
      res.writeHead(status, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify(payload));
    };

    if (url.pathname === '/.well-known/openid-configuration') {
      return json(200, {
        issuer,
        authorization_endpoint: `${issuer}/auth`,
        token_endpoint: `${issuer}/token`,
        userinfo_endpoint: `${issuer}/userinfo`,
        jwks_uri: `${issuer}/certs`,
      });
    }
    if (url.pathname === '/certs') return json(200, { keys: [jwk] });
    if (url.pathname === '/userinfo') return json(200, { sub: 'kc-7f3a-jane', email: 'jane.doe@plant.example' });

    if (url.pathname === '/auth' && req.method === 'GET') {
      const p = url.searchParams;
      const hidden = ['redirect_uri', 'state', 'nonce', 'code_challenge']
        .map((k) => `<input type="hidden" name="${k}" value="${(p.get(k) || '').replace(/"/g, '&quot;')}">`).join('');
      res.writeHead(200, { 'Content-Type': 'text/html' });
      return res.end(`<!doctype html><html><head><title>Plant Identity Provider</title></head>
        <body style="font-family:sans-serif;background:#1f2937;display:flex;justify-content:center;padding-top:80px">
        <form method="post" action="/auth" style="background:#fff;padding:32px;border-radius:8px;width:340px">
          <h2 style="margin-top:0">Plant Identity Provider</h2>
          <p style="color:#555;font-size:13px">Client <b>${p.get('client_id')}</b> asks for: ${p.get('scope')}</p>
          <label>Username<br><input name="username" value="jane.doe" style="width:100%;padding:6px"></label><br><br>
          <label>Password<br><input name="password" type="password" value="corporate-pass" style="width:100%;padding:6px"></label><br><br>
          ${hidden}<button type="submit" id="idp-submit" style="padding:8px 16px">Sign in</button>
        </form></body></html>`);
    }
    if (url.pathname === '/auth' && req.method === 'POST') {
      const f = await body(req);
      const code = randomBytes(16).toString('hex');
      codes.set(code, { nonce: f.get('nonce') || '', challenge: f.get('code_challenge') || '' });
      const back = new URL(f.get('redirect_uri') || '');
      back.searchParams.set('code', code);
      back.searchParams.set('state', f.get('state') || '');
      res.writeHead(302, { Location: back.toString() });
      return res.end();
    }
    if (url.pathname === '/token' && req.method === 'POST') {
      const f = await body(req);
      const grant = codes.get(f.get('code') || '');
      codes.delete(f.get('code') || '');
      const verifier = f.get('code_verifier') || '';
      if (!grant || f.get('client_secret') !== CLIENT_SECRET
        || b64url(createHash('sha256').update(verifier).digest()) !== grant.challenge) {
        return json(400, { error: 'invalid_grant' });
      }
      return json(200, { access_token: 'e2e-access', token_type: 'Bearer', id_token: signIdToken(grant.nonce) });
    }
    json(404, { error: 'not_found' });
  });

  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => {
      issuer = `http://127.0.0.1:${(server.address() as AddressInfo).port}`;
      resolve({ server, issuer });
    });
  });
}

let idp: { server: Server; issuer: string };

test.beforeAll(async () => {
  idp = await startMockIdp();
});

test.afterAll(async () => {
  idp?.server.close();
});

test('issue #123: users sign in with the company identity provider (OpenID Connect SSO)', async ({ page, context }, testInfo) => {
  test.setTimeout(120_000);
  await page.setViewportSize({ width: 1400, height: 1000 });
  const shot = (name: string) => page.screenshot({ path: testInfo.outputPath(name), fullPage: true });

  // --- Admin signs in with a password and gets past the first-run screen.
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  const emptyStart = page.getByText('Start with an empty system');
  if (await emptyStart.isVisible().catch(() => false)) {
    await emptyStart.click();
    await page.waitForURL((url) => !url.pathname.startsWith('/onboarding'));
  }

  // --- Settings shows the new Single Sign-On card.
  await page.goto('/settings');
  await expect(page.getByText('Single Sign-On')).toBeVisible();
  await shot('01-settings-single-sign-on-card.png');

  // --- Configure the provider.
  await page.goto('/settings/sso');
  await expect(page.getByTestId('sso-callback-url')).toContainText('/auth/sso/callback');
  await shot('02-sso-settings-empty.png');

  await page.locator('#sso_enabled').click();
  await page.fill('#sso_provider_name', 'Plant Keycloak');
  await page.fill('#sso_issuer', idp.issuer);
  await page.fill('#sso_client_id', CLIENT_ID);
  await page.fill('#sso_client_secret', CLIENT_SECRET);
  await page.locator('#sso_auto_provision').click();
  await page.fill('#sso_allowed_domains', 'plant.example');
  await page.getByTestId('sso-save').click();
  await expect(page.getByText('Single sign-on settings saved.')).toBeVisible();
  await expect(page.getByText('A secret is stored. Leave empty to keep it.')).toBeVisible();
  await shot('03-sso-settings-saved.png');

  // --- Signed out: the login screen offers "Sign in with Plant Keycloak".
  await context.clearCookies();
  await page.goto('/login');
  const ssoButton = page.getByTestId('sso-login');
  await expect(ssoButton).toHaveText(/Sign in with Plant Keycloak/);
  await shot('04-login-with-sso-button.png');

  // --- The browser goes to the identity provider's own login page.
  await ssoButton.click();
  await page.waitForURL((url) => url.href.startsWith(idp.issuer));
  await expect(page.getByText('Plant Identity Provider')).toBeVisible();
  await shot('05-identity-provider-login.png');

  // --- Back in OpenMES, signed in as a newly provisioned account.
  await Promise.all([
    page.waitForURL((url) => url.pathname.startsWith('/operator')),
    page.click('#idp-submit'),
  ]);
  await page.waitForLoadState('networkidle');
  expect(page.url()).not.toContain('/login');
  expect(page.url()).toContain('/operator/select-line');
  await shot('06-signed-in-via-sso.png');

  // --- The admin sees the account the first SSO sign-in created.
  await context.clearCookies();
  await page.goto('/login');
  await page.fill('input[name="username"]', ADMIN);
  await page.fill('input[name="password"]', PASS);
  await Promise.all([
    page.waitForURL((url) => !url.pathname.startsWith('/login')),
    page.click('button[type="submit"]'),
  ]);
  await page.goto('/admin/users');
  await page.waitForLoadState('networkidle');
  await expect(page.getByText('jane.doe@plant.example').first()).toBeVisible({ timeout: 15_000 });
  await shot('07-provisioned-user-in-admin-list.png');
});
