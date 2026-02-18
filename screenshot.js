const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1200, height: 900 } });

  await page.goto('http://localhost:8080/index.html', { waitUntil: 'networkidle' });

  // Wait a moment for rendering
  await page.waitForTimeout(500);

  // Full page screenshot
  await page.screenshot({
    path: '/home/user/nekos/screenshot_full.png',
    fullPage: true
  });
  console.log('Full page screenshot saved: screenshot_full.png');

  // Viewport screenshot
  await page.screenshot({
    path: '/home/user/nekos/screenshot_viewport.png',
    fullPage: false
  });
  console.log('Viewport screenshot saved: screenshot_viewport.png');

  await browser.close();
})();
