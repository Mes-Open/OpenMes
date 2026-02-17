# PWA Testing Guide for OpenMES

This guide covers testing the Progressive Web App (PWA) functionality of OpenMES, including offline support, service workers, and tablet optimization.

## Unit Tests

### Running Unit Tests

```bash
npm test
```

### Test Coverage

The following PWA components have unit tests:

1. **Offline Queue Store** (`src/stores/offlineQueueStore.test.ts`)
   - Action queuing
   - Retry logic
   - Success/failure handling
   - Queue management

2. **Offline Indicator** (`src/components/common/OfflineIndicator.test.tsx`)
   - Display logic for different states
   - Offline/online status indication
   - Syncing status
   - Failed action display

3. **Network Status Hook** (`src/hooks/useNetworkStatus.test.ts`)
   - Online/offline detection
   - Event listener management
   - Store integration

## Manual Testing

### 1. Service Worker Registration

**Test Steps:**
1. Build the app: `npm run build`
2. Preview the build: `npm run preview`
3. Open DevTools > Application > Service Workers
4. Verify service worker is registered and active

**Expected Result:**
- Service worker appears in the list
- Status shows "activated and is running"

### 2. PWA Installation

**Desktop Testing:**
1. Open the app in Chrome/Edge
2. Look for install button in address bar
3. Click install
4. Verify app opens in standalone window

**Tablet Testing:**
1. Open the app in Safari (iOS) or Chrome (Android)
2. iOS: Tap Share > Add to Home Screen
3. Android: Tap menu > Install app
4. Launch from home screen
5. Verify standalone mode (no browser chrome)

**Expected Result:**
- App installs successfully
- Opens in standalone mode
- Shows OpenMES icon and name

### 3. Offline Functionality

**Setup:**
1. Log in to the app
2. Navigate to a work order detail page
3. Open DevTools > Network tab
4. Click "Offline" checkbox

**Test Case 1: Offline Queue**
1. While offline, try to start a step
2. Observe "Offline Mode" notification
3. Check DevTools > Application > Local Storage
4. Verify action appears in `offline-queue-storage`

**Expected Result:**
- Yellow notification: "Action queued. Will sync when connection restored."
- Action stored in offline queue
- Offline indicator appears in top-right corner

**Test Case 2: Background Sync**
1. While still offline, queue 2-3 actions (start step, complete step)
2. Verify offline indicator shows queued count
3. Uncheck "Offline" in DevTools
4. Wait 1 second

**Expected Result:**
- Automatic sync initiates
- "Syncing..." indicator appears
- Actions execute in order
- "Sync Complete" notification appears
- Offline indicator disappears

**Test Case 3: Failed Sync**
1. Go offline
2. Queue an action
3. Stop the backend server
4. Go online
5. Observe sync attempt

**Expected Result:**
- "Sync Failed" notification appears
- Failed action shown in offline indicator
- "Retry Sync" button available

### 4. Caching Strategy

**Test Steps:**
1. Load the app while online
2. Navigate through several pages
3. Open DevTools > Application > Cache Storage
4. Check cached resources

**Expected Result:**
- `workbox-precache` cache contains app shell resources
- `api-cache` contains recent API responses
- `google-fonts-cache` contains font files

**Test Offline Loading:**
1. With cache populated, go offline
2. Refresh the page
3. Navigate to previously visited pages

**Expected Result:**
- App loads from cache
- Previously viewed work orders/batches display
- Stale data shown with offline indicator

### 5. Tablet UI Testing

**Viewport Testing:**
1. Open DevTools > Toggle Device Toolbar
2. Select iPad Pro (landscape: 1366x1024)
3. Test all operator screens

**Touch Target Testing:**
1. Verify all buttons ≥ 48px height
2. Test step action buttons (START, COMPLETE, PROBLEM)
3. Verify comfortable spacing between elements

**Expected Result:**
- All buttons easily tappable
- No accidental taps on adjacent elements
- Text readable at arm's length
- Landscape orientation works smoothly

### 6. Performance Testing

**Lighthouse Audit:**
1. Build production app: `npm run build`
2. Preview: `npm run preview`
3. Open DevTools > Lighthouse
4. Run PWA audit

**Expected Scores:**
- PWA: ≥ 90
- Performance: ≥ 80
- Accessibility: ≥ 90
- Best Practices: ≥ 90

**Key Metrics to Check:**
- Installable: ✓
- Configured for a custom splash screen: ✓
- Sets a theme color: ✓
- Registers a service worker: ✓
- Works offline: ✓

## Integration Testing

### Scenario: Complete Work Order Offline

**Steps:**
1. Log in and navigate to work order
2. Create a batch
3. Go offline (DevTools)
4. Start step 1 → queued
5. Complete step 1 → queued
6. Start step 2 → queued
7. Go online
8. Wait for auto-sync

**Expected Result:**
- All 3 actions execute in order
- Batch updates correctly
- Step statuses reflect completion
- Work order status updates
- Audit logs created for all actions

### Scenario: Handle Concurrent Offline Actions

**Steps:**
1. Open two browser tabs with same work order
2. Go offline in both tabs
3. Tab 1: Start step 1
4. Tab 2: Try to start step 1 (will be queued)
5. Go online in Tab 1 first
6. Wait for sync
7. Go online in Tab 2

**Expected Result:**
- Tab 1 sync succeeds
- Tab 2 sync fails gracefully (step already started)
- Failed action shown in Tab 2
- No data corruption

## Debugging Tips

### Check Service Worker Logs
```javascript
// In DevTools Console
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(reg => console.log(reg));
});
```

### Inspect Offline Queue
```javascript
// In DevTools Console
JSON.parse(localStorage.getItem('offline-queue-storage'))
```

### Force Service Worker Update
```javascript
// In DevTools Console
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(reg => reg.update());
});
```

### Clear All Caches
```javascript
// In DevTools Console
caches.keys().then(names => {
  names.forEach(name => caches.delete(name));
});
```

## Troubleshooting

### Issue: Service worker not updating
**Solution:**
1. DevTools > Application > Service Workers
2. Check "Update on reload"
3. Hard refresh (Ctrl+Shift+R)

### Issue: App not installing
**Solution:**
1. Verify HTTPS or localhost
2. Check manifest.json is valid
3. Ensure service worker registered
4. Check console for errors

### Issue: Offline queue not syncing
**Solution:**
1. Check network status hook is initialized
2. Verify offline sync hook is mounted
3. Check browser console for errors
4. Inspect queue in localStorage

### Issue: Actions executing out of order
**Solution:**
- Offline sync processes actions sequentially
- Check console logs for sync order
- Verify actions added to queue in correct order

## Automated E2E Tests (Future)

For comprehensive E2E testing, consider:
- **Playwright** with offline mode simulation
- **Cypress** with service worker testing
- Test scenarios in tablet viewports
- Automated offline/online switching

Example Playwright test:
```typescript
test('should queue action when offline', async ({ page, context }) => {
  await context.setOffline(true);
  await page.click('[data-testid="start-step-button"]');
  await expect(page.locator('.offline-indicator')).toBeVisible();
  await context.setOffline(false);
  await page.waitForSelector('.offline-indicator', { state: 'hidden' });
});
```

## Continuous Monitoring

In production, monitor:
- Service worker activation rate
- Offline queue usage patterns
- Failed sync rates
- Cache hit ratios
- PWA install conversion

Use analytics events:
- `pwa_installed`
- `offline_action_queued`
- `offline_sync_success`
- `offline_sync_failed`
