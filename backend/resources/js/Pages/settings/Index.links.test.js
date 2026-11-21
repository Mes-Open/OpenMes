import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// Every card on Settings leads somewhere an admin can use. "Setup Wizard —
// Re-launch the onboarding wizard" pointed at /onboarding, which only exists
// until the first-run choice is made (OnboardingController::index sends a
// set-up installation straight to the dashboard), so the card could only ever
// bounce the admin to the dashboard. Its one remaining choice — an example
// company — lives in Settings → System → Data.
const source = readFileSync(new URL('./Index.jsx', import.meta.url), 'utf8');

describe('Settings index cards', () => {
    it('does not link the first-run onboarding screen', () => {
        expect(source).not.toMatch(/href=["']\/onboarding["']/);
    });
});
