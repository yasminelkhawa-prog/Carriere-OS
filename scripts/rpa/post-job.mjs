import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

function parseArgs(argv) {
  const args = { platform: '', selectors: '' };

  for (let i = 0; i < argv.length; i += 1) {
    const token = argv[i];
    if (token === '--platform') {
      args.platform = String(argv[i + 1] ?? '').trim();
      i += 1;
      continue;
    }
    if (token === '--selectors') {
      args.selectors = String(argv[i + 1] ?? '').trim();
      i += 1;
    }
  }

  return args;
}

function envBoolean(name, fallbackValue) {
  const raw = String(process.env[name] ?? '').trim().toLowerCase();
  if (raw === 'true' || raw === '1' || raw === 'yes') return true;
  if (raw === 'false' || raw === '0' || raw === 'no') return false;
  return fallbackValue;
}

function envNumber(name, fallbackValue) {
  const raw = Number(process.env[name]);
  if (!Number.isFinite(raw) || raw <= 0) {
    return fallbackValue;
  }
  return raw;
}

function platformEnvKey(platform, suffix) {
  const normalized = platform.toUpperCase().replace(/[^A-Z0-9]/g, '_');
  return `RPA_${normalized}_${suffix}`;
}

function getByPath(source, dotPath) {
  if (!dotPath) return undefined;
  const parts = String(dotPath).split('.');
  let value = source;
  for (const part of parts) {
    if (value === null || value === undefined || typeof value !== 'object' || !(part in value)) {
      return undefined;
    }
    value = value[part];
  }
  return value;
}

async function readStdin() {
  const chunks = [];
  for await (const chunk of process.stdin) {
    chunks.push(Buffer.from(chunk));
  }
  return Buffer.concat(chunks).toString('utf8');
}

async function loadJsonFile(filePath) {
  const raw = await fs.readFile(filePath, 'utf8');
  return JSON.parse(raw);
}

async function fileExists(filePath) {
  try {
    await fs.access(filePath);
    return true;
  } catch {
    return false;
  }
}

function resolveCookies(platform) {
  const platformKey = platformEnvKey(platform, 'COOKIES_JSON');
  const raw = String(process.env[platformKey] ?? process.env.RPA_SESSION_COOKIES_JSON ?? '').trim();
  if (!raw) {
    return null;
  }

  const parsed = JSON.parse(raw);
  if (!Array.isArray(parsed)) {
    throw new Error(`Invalid cookie JSON in ${platformKey}: expected array.`);
  }

  return parsed;
}

function resolveSessionStatePath(platform) {
  const configured = String(
    process.env[platformEnvKey(platform, 'SESSION_STATE_PATH')]
      ?? process.env.RPA_SESSION_STATE_PATH
      ?? ''
  ).trim() || path.join(process.cwd(), 'storage', 'app', 'private', 'rpa_sessions', `${platform}.json`);

  return path.isAbsolute(configured)
    ? configured
    : path.join(process.cwd(), configured);
}

async function resolveExistingSessionState(platform) {
  const sessionStatePath = resolveSessionStatePath(platform);
  if (!await fileExists(sessionStatePath)) {
    return null;
  }
  return sessionStatePath;
}

function resolveCredentials(platform) {
  const username = String(
    process.env[platformEnvKey(platform, 'EMAIL')]
      ?? process.env[platformEnvKey(platform, 'USERNAME')]
      ?? ''
  ).trim();
  const password = String(process.env[platformEnvKey(platform, 'PASSWORD')] ?? '').trim();

  return {
    username,
    password,
    configured: username !== '' && password !== '',
  };
}

function parseRegex(raw, fallback) {
  const source = String(raw ?? '').trim();
  if (!source) {
    return fallback;
  }

  try {
    return new RegExp(source, 'i');
  } catch {
    return fallback;
  }
}

function normalizeAuthConfig(platform, selectorsConfig) {
  const auth = selectorsConfig.auth && typeof selectorsConfig.auth === 'object'
    ? selectorsConfig.auth
    : {};

  if (platform === 'linkedin') {
    return {
      loginUrl: String(auth.loginUrl ?? process.env.RPA_LINKEDIN_LOGIN_URL ?? 'https://www.linkedin.com/login'),
      usernameSelector: String(auth.usernameSelector ?? '#username'),
      passwordSelector: String(auth.passwordSelector ?? '#password'),
      submitSelector: String(auth.submitSelector ?? 'button[type="submit"]'),
      postLoginReadySelector: String(
        auth.postLoginReadySelector
          ?? 'a[href*="/feed"], a[href*="/talent"], img.global-nav__me-photo, nav'
      ),
      loginGateSelector: String(
        auth.loginGateSelector
          ?? 'a[href*="/login"], a[href*="/uas/login"], button:has-text("Sign in"), button:has-text("Sign in with email")'
      ),
      loginUrlPattern: String(
        auth.loginUrlPattern
          ?? 'linkedin\\.com\\/(login|checkpoint|challenge|uas\\/login)'
      ),
    };
  }

  return {
    loginUrl: String(auth.loginUrl ?? selectorsConfig.postJobUrl),
    usernameSelector: String(auth.usernameSelector ?? 'input[type="email"], input[type="text"]'),
    passwordSelector: String(auth.passwordSelector ?? 'input[type="password"]'),
    submitSelector: String(auth.submitSelector ?? 'button[type="submit"], button:has-text("Sign in")'),
    postLoginReadySelector: String(auth.postLoginReadySelector ?? ''),
    loginGateSelector: String(auth.loginGateSelector ?? 'a[href*="login"], button:has-text("Sign in")'),
    loginUrlPattern: String(auth.loginUrlPattern ?? '(login|signin|auth|checkpoint|challenge)'),
  };
}

function isOnLoginPage(pageUrl, authConfig) {
  const fallback = /(login|signin|auth|checkpoint|challenge)/i;
  const matcher = parseRegex(authConfig.loginUrlPattern, fallback);
  return matcher.test(String(pageUrl ?? ''));
}

async function selectorVisible(page, selector, timeout = 1200) {
  const raw = String(selector ?? '').trim();
  if (!raw) {
    return false;
  }

  try {
    return await page.locator(raw).first().isVisible({ timeout });
  } catch {
    return false;
  }
}

async function requiresAuthentication(page, authConfig) {
  if (isOnLoginPage(page.url(), authConfig)) {
    return true;
  }

  if (await selectorVisible(page, authConfig.usernameSelector)) {
    return true;
  }

  if (await selectorVisible(page, authConfig.passwordSelector)) {
    return true;
  }

  if (await selectorVisible(page, authConfig.loginGateSelector)) {
    return true;
  }

  return false;
}

async function saveSessionState(context, sessionStatePath) {
  await fs.mkdir(path.dirname(sessionStatePath), { recursive: true });
  await context.storageState({ path: sessionStatePath });
}

async function waitForAuthenticationResolution(page, authConfig, timeoutMs) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    if (!await requiresAuthentication(page, authConfig)) {
      return true;
    }

    await page.waitForTimeout(1000);
  }

  return false;
}

async function bootstrapInteractiveLogin(page, authConfig, navigationTimeoutMs) {
  if (envBoolean('RPA_HEADLESS', true)) {
    throw new Error('Interactive login requires RPA_HEADLESS=false.');
  }

  const waitMs = envNumber('RPA_INTERACTIVE_LOGIN_WAIT_SECONDS', 180) * 1000;

  if (!await requiresAuthentication(page, authConfig)) {
    await page.goto(authConfig.loginUrl, {
      waitUntil: 'domcontentloaded',
      timeout: navigationTimeoutMs,
    });
  }

  const resolved = await waitForAuthenticationResolution(page, authConfig, waitMs);
  if (!resolved) {
    throw new Error(
      'Interactive Indeed login was not completed in time. '
      + 'Finish sign-in/verification in the opened browser window, then retry publish.'
    );
  }
}

function resolveFirstRequiredSelector(steps) {
  for (const step of Array.isArray(steps) ? steps : []) {
    if (step?.required === false) {
      continue;
    }

    const selector = String(step?.selector ?? '').trim();
    if (selector !== '') {
      return selector;
    }
  }

  return '';
}

async function waitForPostFormReady(page, requiredSelector, authConfig, timeoutMs) {
  const startedAt = Date.now();

  while (Date.now() - startedAt < timeoutMs) {
    if (requiredSelector && await selectorVisible(page, requiredSelector, 900)) {
      return true;
    }

    if (await requiresAuthentication(page, authConfig)) {
      await page.waitForTimeout(1200);
      continue;
    }

    await page.waitForTimeout(700);
  }

  return false;
}

async function loginWithCredentials(
  page,
  authConfig,
  credentials,
  navigationTimeoutMs,
  defaultTimeoutMs
) {
  await page.goto(authConfig.loginUrl, {
    waitUntil: 'domcontentloaded',
    timeout: navigationTimeoutMs,
  });

  await page.fill(authConfig.usernameSelector, credentials.username, {
    timeout: defaultTimeoutMs,
  });

  await Promise.all([
    page.waitForNavigation({
      waitUntil: 'domcontentloaded',
      timeout: navigationTimeoutMs,
    }).catch(() => null),
    page.click(authConfig.submitSelector, {
      timeout: defaultTimeoutMs,
    }),
  ]);

  await page.waitForTimeout(1000);

  // Some platforms (Indeed) split email and password steps.
  if (await selectorVisible(page, authConfig.passwordSelector, 2500)) {
    await page.fill(authConfig.passwordSelector, credentials.password, {
      timeout: defaultTimeoutMs,
    });

    await Promise.all([
      page.waitForNavigation({
        waitUntil: 'domcontentloaded',
        timeout: navigationTimeoutMs,
      }).catch(() => null),
      page.click(authConfig.submitSelector, {
        timeout: defaultTimeoutMs,
      }),
    ]);

    await page.waitForTimeout(1000);
  }

  const authenticated = await waitForAuthenticationResolution(
    page,
    authConfig,
    navigationTimeoutMs
  );

  if (!authenticated) {
    throw new Error(
      'Automatic credential login did not complete. Account may require additional verification.'
    );
  }

  if (isOnLoginPage(page.url(), authConfig)) {
    throw new Error(
      'Automatic credential login did not complete. Account may require 2FA/challenge. '
      + 'Run once with RPA_HEADLESS=false and RPA_ALLOW_INTERACTIVE_LOGIN=true to bootstrap a saved session.'
    );
  }

  if (authConfig.postLoginReadySelector) {
    await page.waitForSelector(authConfig.postLoginReadySelector, {
      timeout: defaultTimeoutMs,
    });
  }
}

async function resolveSelectorsConfig(platform, explicitPath) {
  const platformKey = platformEnvKey(platform, 'SELECTORS_PATH');
  const configuredPath = explicitPath
    || String(process.env[platformKey] ?? '')
    || path.join(process.cwd(), 'scripts', 'rpa', 'selectors', `${platform}.json`);

  const resolvedPath = path.isAbsolute(configuredPath)
    ? configuredPath
    : path.join(process.cwd(), configuredPath);

  const config = await loadJsonFile(resolvedPath);

  if (!config || typeof config !== 'object') {
    throw new Error(`Invalid selectors config for ${platform}.`);
  }

  if (!config.postJobUrl || !Array.isArray(config.steps)) {
    throw new Error(`Selectors config for ${platform} must define "postJobUrl" and "steps".`);
  }

  return config;
}

function resolvePayloadValue(stepValuePath, payload) {
  const value = getByPath(payload, stepValuePath);
  if (value === null || value === undefined) return '';
  if (typeof value === 'string') return value;
  return JSON.stringify(value);
}

async function executeStep(page, step, payload, defaultTimeoutMs) {
  const action = String(step.action ?? 'fill').trim().toLowerCase();
  const selector = String(step.selector ?? '').trim();
  const required = step.required !== false;
  const timeout = Number(step.timeoutMs ?? defaultTimeoutMs);

  if (!selector) {
    if (required) {
      throw new Error(`Step missing selector: ${JSON.stringify(step)}`);
    }
    return;
  }

  try {
    if (action === 'waitfor') {
      await page.waitForSelector(selector, { timeout });
      return;
    }

    await page.waitForSelector(selector, { timeout });
    const value = resolvePayloadValue(String(step.value ?? ''), payload);

    if (action === 'fill') {
      const useContentEditable = Boolean(step.useContentEditable);
      if (useContentEditable) {
        await page.click(selector, { timeout });
        await page.keyboard.press('Control+A');
        await page.keyboard.type(String(value), { delay: 1 });
      } else {
        await page.fill(selector, String(value), { timeout });
      }
      return;
    }

    if (action === 'select') {
      await page.selectOption(selector, { value: String(value) });
      return;
    }

    if (action === 'click') {
      await page.click(selector, { timeout });
      return;
    }

    if (action === 'press') {
      const key = String(step.key ?? 'Enter');
      await page.press(selector, key, { timeout });
      return;
    }

    if (required) {
      throw new Error(`Unsupported step action "${action}" for selector "${selector}".`);
    }
  } catch (error) {
    if (!required) {
      return;
    }
    throw error;
  }
}

async function takeFailureScreenshot(page, platform, postingId) {
  const configured = String(process.env.RPA_SCREENSHOT_DIR ?? '').trim()
    || path.join(process.cwd(), 'storage', 'app', 'private', 'rpa_failures');
  const screenshotDir = path.isAbsolute(configured)
    ? configured
    : path.join(process.cwd(), configured);

  await fs.mkdir(screenshotDir, { recursive: true });

  const fileName = `${platform}-${postingId || 'posting'}-${Date.now()}.png`;
  const fullPath = path.join(screenshotDir, fileName);

  if (page) {
    await page.screenshot({ path: fullPath, fullPage: true });
  }

  return fullPath;
}

async function main() {
  const args = parseArgs(process.argv.slice(2));
  if (!args.platform) {
    throw new Error('Missing required --platform argument.');
  }

  const payloadRaw = await readStdin();
  if (!payloadRaw.trim()) {
    throw new Error('Missing JSON payload on stdin.');
  }

  const payload = JSON.parse(payloadRaw);
  const selectorsConfig = await resolveSelectorsConfig(args.platform, args.selectors);
  const cookies = resolveCookies(args.platform);
  const existingSessionStatePath = await resolveExistingSessionState(args.platform);
  const targetSessionStatePath = resolveSessionStatePath(args.platform);
  const credentials = resolveCredentials(args.platform);
  const authConfig = normalizeAuthConfig(args.platform, selectorsConfig);
  const allowInteractiveBootstrap = envBoolean('RPA_ALLOW_INTERACTIVE_LOGIN', false);

  if (!existingSessionStatePath && !cookies && !credentials.configured && !allowInteractiveBootstrap) {
    const emailKey = platformEnvKey(args.platform, 'EMAIL');
    const passwordKey = platformEnvKey(args.platform, 'PASSWORD');
    const stateKey = platformEnvKey(args.platform, 'SESSION_STATE_PATH');

    throw new Error(
      `No reusable ${args.platform} session found. Configure ${emailKey} and ${passwordKey} for automatic login, `
      + `or set RPA_ALLOW_INTERACTIVE_LOGIN=true once (with RPA_HEADLESS=false) to bootstrap ${stateKey}.`
    );
  }

  let playwright;
  try {
    playwright = await import('playwright');
  } catch {
    throw new Error('Playwright package is missing. Install it in scripts/rpa first.');
  }

  const browser = await playwright.chromium.launch({
    headless: envBoolean('RPA_HEADLESS', true),
  });

  let context;
  let page;
  let authSource = existingSessionStatePath ? 'storage_state' : (cookies ? 'cookies' : 'none');
  try {
    context = await browser.newContext({
      ignoreHTTPSErrors: true,
      viewport: { width: 1440, height: 900 },
      ...(existingSessionStatePath ? { storageState: existingSessionStatePath } : {}),
    });
    if (!existingSessionStatePath && cookies) {
      await context.addCookies(cookies);
    }

    page = await context.newPage();
    const defaultTimeoutMs = Number(selectorsConfig.defaultTimeoutMs ?? 15000);
    const navigationTimeoutMs = Number(selectorsConfig.navigationTimeoutMs ?? 45000);

    await page.goto(String(selectorsConfig.postJobUrl), {
      waitUntil: 'domcontentloaded',
      timeout: navigationTimeoutMs,
    });

    if (await requiresAuthentication(page, authConfig)) {
      if (credentials.configured) {
        await loginWithCredentials(
          page,
          authConfig,
          credentials,
          navigationTimeoutMs,
          defaultTimeoutMs
        );

        await page.goto(String(selectorsConfig.postJobUrl), {
          waitUntil: 'domcontentloaded',
          timeout: navigationTimeoutMs,
        });

        if (await requiresAuthentication(page, authConfig)) {
          throw new Error(
            'Credential login completed but posting page still requires authentication.'
          );
        }

        await saveSessionState(context, targetSessionStatePath);
        authSource = 'credentials_bootstrap';
      } else if (allowInteractiveBootstrap) {
        await bootstrapInteractiveLogin(
          page,
          authConfig,
          navigationTimeoutMs
        );

        await page.goto(String(selectorsConfig.postJobUrl), {
          waitUntil: 'domcontentloaded',
          timeout: navigationTimeoutMs,
        });

        if (await requiresAuthentication(page, authConfig)) {
          throw new Error(
            'Interactive login completed but posting page still requires authentication.'
          );
        }

        await saveSessionState(context, targetSessionStatePath);
        authSource = 'interactive_bootstrap';
      } else {
        const emailKey = platformEnvKey(args.platform, 'EMAIL');
        const passwordKey = platformEnvKey(args.platform, 'PASSWORD');
        const stateKey = platformEnvKey(args.platform, 'SESSION_STATE_PATH');

        throw new Error(
          `No valid ${args.platform} session found. Configure ${emailKey} and ${passwordKey} for automatic login, `
          + `or enable RPA_ALLOW_INTERACTIVE_LOGIN=true once (with RPA_HEADLESS=false) to bootstrap ${stateKey}.`
        );
      }
    }

    const firstRequiredSelector = resolveFirstRequiredSelector(selectorsConfig.steps);
    const postFormReadyTimeoutMs = envNumber('RPA_POST_FORM_READY_TIMEOUT_SECONDS', 180) * 1000;
    const ready = await waitForPostFormReady(
      page,
      firstRequiredSelector,
      authConfig,
      postFormReadyTimeoutMs
    );

    if (!ready) {
      throw new Error(
        `Post form did not become ready within ${Math.round(postFormReadyTimeoutMs / 1000)} seconds. `
        + 'If a verification page is visible, complete it and retry.'
      );
    }

    for (const step of selectorsConfig.steps) {
      await executeStep(page, step, payload, defaultTimeoutMs);
    }

    if (selectorsConfig.successCheck?.selector) {
      await page.waitForSelector(String(selectorsConfig.successCheck.selector), {
        timeout: Number(selectorsConfig.successCheck.timeoutMs ?? 15000),
      });
    }

    await saveSessionState(context, targetSessionStatePath);
    if (!existingSessionStatePath && cookies && authSource === 'cookies') {
      authSource = 'cookies_bootstrap';
    }

    const result = {
      ok: true,
      externalUrl: page.url(),
      platform: args.platform,
      authSource,
    };

    process.stdout.write(JSON.stringify(result));
  } catch (error) {
    const screenshotPath = await takeFailureScreenshot(
      page,
      args.platform,
      String(payload?.jobPostingId ?? '')
    );

    const failure = {
      ok: false,
      platform: args.platform,
      error: String(error instanceof Error ? error.message : error),
      screenshotPath,
    };

    process.stdout.write(JSON.stringify(failure));
    process.exitCode = 1;
  } finally {
    if (context) {
      await context.close();
    }
    await browser.close();
  }
}

main().catch(async (error) => {
  const failure = {
    ok: false,
    error: String(error instanceof Error ? error.message : error),
  };
  process.stdout.write(JSON.stringify(failure));
  process.exitCode = 1;
});
