import { describe, it, expect, vi } from 'vitest';

vi.mock('posthog-js', () => ({
    default: { init: vi.fn() },
}));

describe('posthog init', () => {
    it('is a no-op without VITE_POSTHOG_KEY', async () => {
        const { initPostHog } = await import('../posthog.js');
        const posthogModule = await import('posthog-js');

        initPostHog();

        expect(posthogModule.default.init).not.toHaveBeenCalled();
    });
});
