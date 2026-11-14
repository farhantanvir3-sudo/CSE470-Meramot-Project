# Chrome Remote Debugging Configuration

This document records the configuration for remote debugging Chrome in the Meramot project.

## Remote Debugging Server

- **Host / Port**: `http://127.0.0.1:9222`
- **Protocol**: Chrome DevTools Protocol (CDP)

---

## Usage in Playwright / Node.js Scripts

Whenever live inspection, visual checks, or browser automation against the active Chrome instance is required, connect directly over CDP:

```javascript
import { chromium } from 'playwright';

const browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
const context = browser.contexts()[0] || await browser.newContext();
const page = context.pages()[0] || await context.newPage();

await page.goto('http://127.0.0.1:8000');
// Debug or inspect UI state
```

---

## Launching or Restarting Chrome with Remote Debugging (Windows)

If Chrome needs to be launched or restarted with the remote debugging endpoint enabled:

```powershell
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --remote-debugging-port=9222 --remote-allow-origins="*"
```

---

## Notes
- Retain this server address for automated end-to-end testing, live DOM inspection, and debugging sessions when headless mode is insufficient.
