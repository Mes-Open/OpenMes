import { defineConfig } from 'vitest/config';

// Standalone Vitest config (kept separate from vite.config.js so tests don't
// pull in the Laravel/Tailwind build plugins). Still Vite-powered, so Vite-only
// syntax like `import.meta.glob` in the modules under test resolves.
export default defineConfig({
    test: {
        environment: 'node',
        include: ['resources/js/**/*.{test,spec}.{js,jsx}'],
    },
});
