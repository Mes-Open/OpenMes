# PWA Testing Guide

This guide covers how to test the Progressive Web App (PWA) features of OpenMES, including offline mode, installation, and background sync.

---

## Table of Contents

- [Prerequisites](#prerequisites)
- [Testing Environment Setup](#testing-environment-setup)
- [Installation Testing](#installation-testing)
  - [iOS (Safari)](#ios-safari)
  - [Android (Chrome)](#android-chrome)
  - [Desktop Chrome/Edge](#desktop-chromeedge)
- [Offline Mode Testing](#offline-mode-testing)
  - [Using Chrome DevTools](#using-chrome-devtools)
  - [Physical Network Disconnection](#physical-network-disconnection)
- [Service Worker Testing](#service-worker-testing)
- [Manifest Testing](#manifest-testing)
- [Performance Testing](#performance-testing)
- [Common Issues and Fixes](#common-issues-and-fixes)

---

## Prerequisites

To test PWA features, OpenMES must be served over **HTTPS** (or `localhost` for development). PWA features (service worker, install prompt) are blocked on plain HTTP connections unless you are on localhost.

For production testing:
- Use a valid SSL certificate
- Ensure `APP_URL` in `.env` starts with `https://`

For local development:
- `http://localhost` is allowed by browsers for PWA testing
- Use `http://localhost:8080` (or whatever port you configured)

---

## Testing Environment Setup

### Chrome Flags (useful for testing)

Open `chrome://flags` and enable:
- `#enable-desktop-pwas-remove-status-bar` — full-screen mode testing
- `#bypass-app-banner-engagement-checks` — see the install prompt without needing real engagement

### Clear Service Worker State

Before each test run, clear the service worker state:
1. Open DevTools → Application → Service Workers
2. Click **Unregister** on any existing OpenMES service worker
3. Open Application → Storage → Click **Clear site data**

---

## Installation Testing

### iOS (Safari)

**Steps:**
1. Open Safari on your iPhone or iPad
2. Navigate to your OpenMES URL
3. Tap the **Share** button (box with upward arrow)
4. Scroll down in the share sheet and tap **Add to Home Screen**
5. Confirm the name is "OpenMES" and tap **Add**

**Expected result:**
- An OpenMES icon appears on the home screen
- Launching from the icon opens OpenMES in full-screen (no Safari browser chrome)
- The theme color (`#1e40af`) appears in the status bar area

**Known limitations on iOS:**
- iOS requires HTTPS for full PWA support
- Background sync is limited on iOS — actions queue locally and sync on next app open
- Push notifications require iOS 16.4+

### Android (Chrome)

**Steps:**
1. Open Chrome on your Android device
2. Navigate to your OpenMES URL
3. Chrome should show an **"Add to Home Screen"** banner at the bottom
4. Tap **Install** (or use the menu → Add to Home screen)

If the banner does not appear:
- Open Chrome menu (⋮) → **Install app**
- Or: Chrome menu → **Add to Home Screen**

**Expected result:**
- OpenMES icon appears in app drawer and can be pinned to home screen
- Launching opens in standalone mode (no browser chrome)
- App appears in Android Recent Apps with its own entry

### Desktop Chrome/Edge

**Steps:**
1. Navigate to your OpenMES URL in Chrome or Edge
2. Look for the install icon (📥) in the address bar
3. Click it and confirm installation

**Expected result:**
- OpenMES opens in its own window
- Appears in the system's application list

---

## Offline Mode Testing

### Using Chrome DevTools

This is the recommended approach for development testing.

1. Open OpenMES in Chrome and log in
2. Navigate to a page with data (e.g., work order queue)
3. Open DevTools (F12)
4. Go to **Application → Service Workers**
5. Check the **Offline** checkbox

**Test scenarios:**

| Action | Expected Behavior |
|---|---|
| Reload the page | Page loads from cache (no network error) |
| Navigate to a cached page | Page loads normally |
| Navigate to an uncached page | Offline page (`/offline`) is shown |
| Try to complete a step | Action is queued, shown as pending |
| Try to report an issue | Action is queued, shown as pending |

6. Uncheck **Offline** to restore the connection
7. Verify: queued actions are automatically submitted within a few seconds

### Physical Network Disconnection

For real-device testing:
1. Log in to OpenMES on your tablet
2. Load the work queue page
3. Disconnect from WiFi or enable Airplane Mode
4. Verify an offline banner appears at the top of the screen
5. Attempt to complete a production step
6. Reconnect to WiFi
7. Verify the completed step is synced

---

## Service Worker Testing

### Verify Registration

1. Open DevTools → Application → Service Workers
2. You should see `/sw.js` registered for your OpenMES origin
3. Status should be **activated and running**

### Cache Inspection

1. Open DevTools → Application → Cache Storage
2. You should see at least one cache named `openmmes-v1` (or similar)
3. Inspect cached resources — should include core HTML, CSS, JS, and images

### Update Flow

To test service worker updates:
1. Change a cached asset (e.g., modify a view)
2. Rebuild assets: `npm run build`
3. Reload the page twice (first load detects the new SW, second load activates it)
4. Alternatively, in DevTools → Application → Service Workers, click **Update**

---

## Manifest Testing

Verify the Web App Manifest is correct:

1. Open DevTools → Application → Manifest
2. Check:
   - **Name**: OpenMES
   - **Short name**: OpenMES
   - **Start URL**: `/`
   - **Display**: `standalone`
   - **Theme color**: `#1e40af`
   - **Background color**: appropriate color
   - **Icons**: 192x192 and 512x512 PNG icons present

To inspect manually:
```
GET /manifest.json
```

Expected response:
```json
{
    "name": "OpenMES",
    "short_name": "OpenMES",
    "start_url": "/",
    "display": "standalone",
    "theme_color": "#1e40af",
    "background_color": "#ffffff",
    "icons": [
        {
            "src": "/icon-192.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/icon-512.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ]
}
```

---

## Performance Testing

For tablets on the shop floor, performance matters. Run a Lighthouse audit:

1. Open DevTools → Lighthouse
2. Select **Mobile** device simulation
3. Check: **Performance**, **PWA**, **Best Practices**, **Accessibility**
4. Click **Analyze page load**

**Target scores:**
- PWA: 100 (all installability checks pass)
- Performance: ≥ 70 on a simulated mobile device
- Best Practices: ≥ 90
- Accessibility: ≥ 80

### Network Throttling

Simulate a slower factory WiFi connection:
1. DevTools → Network → Throttling
2. Select **Slow 3G** or **Fast 3G**
3. Reload the page and measure time to interactive

Target: main pages load within 3 seconds on Fast 3G.

---

## Common Issues and Fixes

### Install prompt does not appear

- Ensure you are on HTTPS (not HTTP, except localhost)
- Check the manifest is valid (DevTools → Application → Manifest)
- The service worker must be registered and active
- On Chrome, there must be at least one prior visit to the site (or bypass engagement checks via flags)

### Service worker not updating

- Hard-refresh with Ctrl+Shift+R
- Or go to DevTools → Application → Service Workers → click **Update**
- If still stuck: click **Unregister**, then reload

### Offline page not showing

- The `/offline` route must be cached by the service worker on first load
- Check that the service worker's install event caches the offline page

### Actions not syncing after reconnect

- Check browser console for errors after going back online
- Ensure Background Sync is supported in the browser (Chrome on Android supports it; Safari on iOS has limited support)
- On iOS, sync happens when the app is opened, not automatically in the background

### Dark theme not applying on install

The theme color is set in both the manifest and a `<meta name="theme-color">` tag. If it doesn't apply, check that the meta tag is present in the `<head>` section of the main layout.
