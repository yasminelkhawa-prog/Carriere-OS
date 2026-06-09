# RPA Worker (Playwright)

This worker automates job-board posting for platforms that do not support XML ingestion.

## Setup

1. Install worker dependencies:
```bash
cd scripts/rpa
npm install
npm run install-browser
```

2. Configure Laravel env for automatic session bootstrap:
- `MULTIPOSTING_AUTOMATION_ENABLED=true`
- `MULTIPOSTING_AUTOMATION_PLATFORMS=linkedin,indeed`
- `MULTIPOSTING_AUTOMATION_SCRIPT_PATH=scripts/rpa/post-job.mjs`
- `RPA_LINKEDIN_EMAIL=...`
- `RPA_LINKEDIN_PASSWORD=...`
- `RPA_LINKEDIN_SESSION_STATE_PATH=storage/app/private/rpa_sessions/linkedin.json`
- `RPA_INDEED_EMAIL=...`
- `RPA_INDEED_PASSWORD=...`
- `RPA_INDEED_SESSION_STATE_PATH=storage/app/private/rpa_sessions/indeed.json`

3. Optional fallback modes:
- Keep `RPA_LINKEDIN_COOKIES_JSON` empty unless you intentionally want cookie mode.
- For one-time manual bootstrap (when LinkedIn forces challenge/2FA):
  - `MULTIPOSTING_AUTOMATION_HEADLESS=false`
  - `RPA_ALLOW_INTERACTIVE_LOGIN=true`
  - the worker will wait up to `RPA_INTERACTIVE_LOGIN_WAIT_SECONDS` and then save session state automatically.

4. Legacy manual cookie extraction (optional only):
```bash
npm run extract-cookies -- --url https://www.linkedin.com/talent/post-a-job
```

## Execution Model

- Laravel queues `RunJobBoardAutomationJob`.
- The job executes `post-job.mjs` with a JSON payload via stdin.
- Session resolution order is automatic:
  - existing `SESSION_STATE_PATH`
  - cookie env (if provided)
  - credentials login (`RPA_LINKEDIN_EMAIL` + `RPA_LINKEDIN_PASSWORD`)
  - optional interactive bootstrap (if enabled)
- If selectors break, the script saves a screenshot to `MULTIPOSTING_AUTOMATION_SCREENSHOT_DIR`.
- Laravel marks posting status as `failed` and records an audit event:
  - `job_posting.automation_manual_fallback_required`

## Selector Config

Use JSON selector maps in `scripts/rpa/selectors/`.

- `linkedin.json` is the active default.
- `indeed.json` is included and should be tuned to your Indeed region/account UI.
- `localboard.example.json` is a template for local board integrations.

Update selectors whenever board UI changes.
