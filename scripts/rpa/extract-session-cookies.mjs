import readline from 'node:readline/promises';
import process from 'node:process';

function parseArgs(argv) {
  const args = { url: '' };

  for (let i = 0; i < argv.length; i += 1) {
    const token = argv[i];
    if (token === '--url') {
      args.url = String(argv[i + 1] ?? '').trim();
      i += 1;
    }
  }

  return args;
}

async function main() {
  const { url } = parseArgs(process.argv.slice(2));
  if (!url) {
    throw new Error('Missing required --url argument.');
  }

  let playwright;
  try {
    playwright = await import('playwright');
  } catch {
    throw new Error('Playwright package is missing. Install it in scripts/rpa first.');
  }

  const browser = await playwright.chromium.launch({ headless: false });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
  });

  const page = await context.newPage();
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 45000 });

  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stderr,
  });

  await rl.question(
    'Complete login manually in the opened browser, then press Enter to export session cookies...'
  );
  rl.close();

  const cookies = await context.cookies();
  process.stdout.write(`${JSON.stringify(cookies)}\n`);

  await context.close();
  await browser.close();
}

main().catch((error) => {
  process.stderr.write(`${String(error instanceof Error ? error.message : error)}\n`);
  process.exit(1);
});

