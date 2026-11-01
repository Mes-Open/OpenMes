import { Browser, BrowserContext, Locator, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';

/**
 * A browser context that films itself the way a person would be filmed using
 * the app: a visible cursor that travels to what it clicks, typing at a human
 * pace, a caption naming each step, and a title card at the start. Playwright
 * records the video; `finish()` saves it under the section's name (as MP4 when
 * ffmpeg is installed, else WebM).
 */

export const SIZE = { width: 1440, height: 900 };

// Injected into every document the context opens. It survives Inertia visits
// (same document) and full loads (re-run, caption restored from sessionStorage).
const OVERLAY = () => {
  const install = () => {
    if (document.getElementById('__rec-cursor')) return;
    const style = document.createElement('style');
    style.textContent = `
      #__rec-cursor { position: fixed; z-index: 2147483647; pointer-events: none; width: 22px; height: 22px;
        margin: -3px 0 0 -3px; left: -40px; top: -40px; transition: transform .08s; }
      #__rec-cursor.down { transform: scale(.82); }
      .__rec-ripple { position: fixed; z-index: 2147483646; pointer-events: none; width: 38px; height: 38px; margin: -19px 0 0 -19px;
        border-radius: 50%; border: 3px solid rgba(234,90,43,.85); animation: __rec-ripple .55s ease-out forwards; }
      @keyframes __rec-ripple { from { transform: scale(.3); opacity: 1 } to { transform: scale(1.6); opacity: 0 } }
      #__rec-caption { position: fixed; z-index: 2147483645; pointer-events: none; left: 50%; bottom: 26px; transform: translateX(-50%);
        max-width: 78%; padding: 12px 22px; border-radius: 12px; background: rgba(17,17,17,.86); color: #fff;
        font: 600 19px/1.35 system-ui, -apple-system, 'Segoe UI', sans-serif; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.25);
        transition: opacity .25s; }
      #__rec-caption:empty { opacity: 0; }
      #__rec-card { position: fixed; inset: 0; z-index: 2147483644; pointer-events: none; display: flex; flex-direction: column;
        align-items: center; justify-content: center; gap: 14px; background: #111; color: #fff; transition: opacity .6s;
        font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; text-align: center; }
      #__rec-card h1 { font-size: 46px; font-weight: 700; margin: 0; }
      #__rec-card p { font-size: 22px; margin: 0; opacity: .75; max-width: 70%; }
      #__rec-card small { font-size: 15px; letter-spacing: .12em; text-transform: uppercase; color: #ea5a2b; }`;
    document.documentElement.appendChild(style);
    const cursor = document.createElement('div');
    cursor.id = '__rec-cursor';
    cursor.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22"><path d="M3 2l7 19 2.6-7.6L20 11z" fill="#111" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';
    document.documentElement.appendChild(cursor);
    const caption = document.createElement('div');
    caption.id = '__rec-caption';
    const stored = (key: string) => { try { return sessionStorage.getItem(key); } catch { return null; } };
    caption.textContent = stored('__rec-caption') || '';
    document.documentElement.appendChild(caption);
    const last = stored('__rec-xy');
    if (last) { const [x, y] = last.split(',').map(Number); cursor.style.left = `${x}px`; cursor.style.top = `${y}px`; }
    addEventListener('mousemove', (e) => { cursor.style.left = `${e.clientX}px`; cursor.style.top = `${e.clientY}px`; try { sessionStorage.setItem('__rec-xy', `${e.clientX},${e.clientY}`); } catch { /* no storage */ } }, true);
    addEventListener('mousedown', (e) => {
      cursor.classList.add('down');
      const r = document.createElement('div');
      r.className = '__rec-ripple'; r.style.left = `${e.clientX}px`; r.style.top = `${e.clientY}px`;
      document.documentElement.appendChild(r); setTimeout(() => r.remove(), 600);
    }, true);
    addEventListener('mouseup', () => cursor.classList.remove('down'), true);
    // A cover pending across a navigation is the new document's first paint.
    const cover = stored('__rec-cover');
    if (cover) (window as any).__recCard(JSON.parse(cover), false);
  };
  (window as any).__recCard = (text: [string, string, string], fade: boolean) => {
    document.getElementById('__rec-card')?.remove();
    const card = document.createElement('div');
    card.id = '__rec-card';
    card.innerHTML = '<small></small><h1></h1><p></p>';
    card.querySelector('small')!.textContent = text[0];
    card.querySelector('h1')!.textContent = text[1];
    card.querySelector('p')!.textContent = text[2];
    if (fade) { card.style.opacity = '0'; requestAnimationFrame(() => { card.style.opacity = '1'; }); }
    document.documentElement.appendChild(card);
  };
  if (document.documentElement) install(); else addEventListener('DOMContentLoaded', install);
  // React may re-render the root, never <html>'s direct children; this only guards a full document swap.
  addEventListener('DOMContentLoaded', install);
};

export class Recorder {
  private coveredAt = 0;

  private constructor(readonly context: BrowserContext, readonly page: Page, private readonly name: string, private readonly dir: string, private readonly out: string) {}

  static async open(browser: Browser, opts: { name: string; out: string; storageState?: string }): Promise<Recorder> {
    const dir = mkdtempSync(path.join(tmpdir(), 'openmes-rec-'));
    const context = await browser.newContext({
      viewport: SIZE,
      recordVideo: { dir, size: SIZE },
      locale: 'en-GB',
      storageState: opts.storageState,
    });
    await context.addInitScript(OVERLAY);
    const page = await context.newPage();
    // Open on the app's own origin (a plain-text page, a blink at most), so
    // sessionStorage can carry a cover card across the section's first navigation.
    await page.goto('/robots.txt');
    return new Recorder(context, page, opts.name, dir, opts.out);
  }

  pause(ms = 700) {
    return this.page.waitForTimeout(ms);
  }

  /**
   * A full-screen title card that stays up across navigations until the next
   * caption: the page behind it loads (and gets filtered) out of sight, and
   * the card fades into the screen the section is about.
   */
  async cover(kicker: string, title: string, subtitle: string) {
    await this.page.evaluate(OVERLAY);
    await this.page.evaluate((text) => {
      sessionStorage.setItem('__rec-cover', JSON.stringify(text));
      // The last caption belonged to the previous scene.
      sessionStorage.removeItem('__rec-caption');
      const caption = document.getElementById('__rec-caption');
      if (caption) caption.textContent = '';
      (window as any).__recCard(text, true);
    }, [kicker, title, subtitle]);
    this.coveredAt = Date.now();
  }

  /** Fade the cover out once it has been readable for `minMs`. */
  async uncover(minMs = 3200) {
    if (!this.coveredAt) return;
    await this.pause(Math.max(0, minMs - (Date.now() - this.coveredAt)));
    this.coveredAt = 0;
    await this.page.evaluate(() => {
      sessionStorage.removeItem('__rec-cover');
      const card = document.getElementById('__rec-card');
      if (card) { card.style.opacity = '0'; setTimeout(() => card.remove(), 700); }
    });
    await this.pause(700);
  }

  /** The line of text at the bottom of the frame; kept across page loads. Lifts a cover first. */
  async caption(text: string, readMs = 1400) {
    await this.uncover();
    await this.page.evaluate(OVERLAY);
    await this.page.evaluate((text) => {
      try { sessionStorage.setItem('__rec-caption', text); } catch { /* blank page: no storage */ }
      const el = document.getElementById('__rec-caption');
      if (el) el.textContent = text;
    }, text);
    await this.pause(readMs);
  }

  /** Glide the cursor to the element, the way a hand moves a mouse. */
  async point(target: Locator) {
    await target.scrollIntoViewIfNeeded();
    const box = await target.boundingBox();
    if (!box) return;
    const x = box.x + box.width / 2;
    const y = box.y + box.height / 2;
    await this.page.mouse.move(x, y, { steps: 22 });
    await this.pause(220);
  }

  async click(target: Locator, after = 650) {
    await target.waitFor({ state: 'visible' });
    await this.point(target);
    await target.click();
    await this.pause(after);
  }

  /** Click into the field, clear it, and type the text key by key. */
  async type(target: Locator, text: string, delay = 65) {
    await this.click(target, 200);
    await target.fill('');
    await target.pressSequentially(text, { delay });
    await this.pause(350);
  }

  /**
   * Open a Dropdown (@openmes/ui combobox) and pick the option. With `search`,
   * type it into the list's search box first, as a person narrowing a long
   * list would — it also keeps the rest of the instance's data off the video.
   */
  async choose(combobox: Locator, option: string | RegExp, search?: string) {
    await this.point(combobox);
    await combobox.click();
    if (search) {
      const box = this.page.getByPlaceholder(/^Search/).last();
      if (await box.isVisible().catch(() => false)) await this.page.keyboard.type(search, { delay: 25 });
    }
    await this.pause(450);
    await this.click(this.page.getByRole('option', { name: option }).first(), 500);
  }

  async goto(url: string) {
    await this.page.goto(url, { waitUntil: 'commit' });
    await this.settle();
  }

  /** Wait for the page's requests to finish (live-sync sockets do not count). */
  async settle(ms = 600) {
    await this.page.waitForLoadState('domcontentloaded').catch(() => {});
    await this.page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
    await this.pause(ms);
  }

  /** Close the context, which finalises the video, and file it under the section's name. */
  async finish(): Promise<string> {
    await this.caption('', 0);
    await this.pause(900);
    const video = this.page.video();
    await this.context.close();
    mkdirSync(this.out, { recursive: true });
    const webm = path.join(this.out, `${this.name}.webm`);
    await video!.saveAs(webm);
    rmSync(this.dir, { recursive: true, force: true });
    const mp4 = path.join(this.out, `${this.name}.mp4`);
    try {
      execFileSync('ffmpeg', ['-y', '-loglevel', 'error', '-i', webm, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '20', '-movflags', '+faststart', '-an', mp4]);
      rmSync(webm);
      return mp4;
    } catch {
      return existsSync(webm) ? webm : '';
    }
  }
}
