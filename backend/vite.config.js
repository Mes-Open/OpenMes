import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/app.jsx',
            ],
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    // The dev-overlay watcher (`npm run watch`) sets WATCH_POLL=1: poll for
    // changes because inotify misses newly-created files across the bind mount.
    // Gated on the env var so the one-shot production `vite build` is unaffected
    // (a non-null build.watch would otherwise make it hang in watch mode).
    build: process.env.WATCH_POLL
        ? { watch: { chokidar: { usePolling: true, interval: 300 } } }
        : {},
    // @openmes/ui (symlinked from ../packages/ui) ships twin-platform source:
    // index.web.jsx for the browser, index.native.tsx for React Native. Web
    // extensions are listed first so `import '@openmes/ui/...'` picks the
    // .web.* file; dedupe keeps the package's react import pointing at
    // backend/node_modules (its real path lives outside backend/).
    resolve: {
        extensions: ['.web.jsx', '.web.tsx', '.web.js', '.web.ts', '.mjs', '.js', '.mts', '.ts', '.jsx', '.tsx', '.json'],
        // react-table, lucide and dnd-kit are listed too: @openmes/ui imports them,
        // but the package's real path is outside backend/, where the upward
        // node_modules walk would miss backend/node_modules. dedupe pins these
        // to the app root.
        dedupe: ['react', 'react-dom', '@tanstack/react-table', 'lucide-react', '@dnd-kit/react'],
        alias: {
            // A module's pages live under modules/<Name>/resources/js and sit at
            // a different depth from this app's own pages, so a relative import
            // of a shared layout or component would be a different '../../..'
            // chain for every file. They import '@core/…' instead, which is the
            // same from anywhere and does not break when a page is moved.
            '@core': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    optimizeDeps: {
        exclude: ['@openmes/ui'],
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
            port: 5173,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        // Let the dev server serve @openmes/ui source from outside backend/.
        fs: {
            allow: ['.', '../packages'],
        },
    },
});
