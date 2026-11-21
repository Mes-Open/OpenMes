import { test, expect } from '@playwright/test';
import { login } from './support/app';

// The admin layout is exactly one screen tall and scrolls inside <main>. An
// absolutely positioned element in the page content (Tailwind's `sr-only`, used
// for screen-reader text such as the severity of a "Needs action" row) is
// placed against the nearest positioned ancestor. If <main> is not positioned,
// it escapes main's scroll box, stretches the whole document and the window
// scrolls the layout away, leaving a grey band below the sidebar.
test('admin layout: absolutely positioned page content stays inside the scroll area', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 720 });
  await login(page);
  await page.goto('/admin/dashboard');
  await page.locator('main').waitFor();

  const heights = await page.evaluate(() => {
    const main = document.querySelector('main');
    if (!main) throw new Error('No <main> in the admin layout.');

    // Content taller than the screen, ending in screen-reader-only text.
    const filler = document.createElement('div');
    filler.style.height = '3000px';
    const srOnly = document.createElement('span');
    srOnly.className = 'sr-only';
    srOnly.textContent = 'Warning';
    filler.appendChild(srOnly);
    main.appendChild(filler);

    return { document: document.documentElement.scrollHeight, viewport: window.innerHeight };
  });

  expect(heights.document).toBeLessThanOrEqual(heights.viewport);
});
