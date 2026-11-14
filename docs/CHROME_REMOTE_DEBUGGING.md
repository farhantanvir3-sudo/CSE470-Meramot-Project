# Chrome Remote Debugging Reference

## Overview
When remote debugging in Google Chrome is needed for testing, inspecting Alpine.js state, or validating responsive layouts in the Meramot project, use the designated debugging server:

- **Server URL**: `http://127.0.0.1:9222`
- **Target Protocol**: Chrome DevTools Protocol (CDP)

---

## Connecting via Playwright / CDP

```javascript
import { chromium } from 'playwright';

// Connect directly to the existing Chrome instance
const browser = await chromium.connectOverCDP('http://127.0.0.1:9222');
const context = browser.contexts()[0] || await browser.newContext();
const page = context.pages()[0] || await context.newPage();

// Perform assertions or visual inspection
console.log('Connected to Chrome debugging instance:', page.url());
```

---

## Launch Parameters (Windows PowerShell)

```powershell
& "C:\Program Files\Google\Chrome\Application\chrome.exe" --remote-debugging-port=9222 --remote-allow-origins="*"
```
