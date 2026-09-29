/**
 * Module-side Vite plugin: builds an installed module's pages into one file
 * that binds to core's shared runtime instead of carrying its own copy of it.
 *
 * A module repository's whole vite.config.js is meant to be:
 *
 *     import { openmesModule } from '@openmes/module';
 *     export default { plugins: [openmesModule()] };
 *
 * Everything else — externals, the globals map, output format and filename, the
 * page registration boilerplate — comes from `contract.js`, so a module author
 * never writes (and never has to update) the 19-entry mapping by hand.
 *
 * The build deliberately does NOT need core's source tree: every core import is
 * external, so Rollup never resolves it. That is what lets a module repository
 * build on its own, without pinning itself to a core checkout.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { ALLOWED_IMPORTS, CONTRACT, CONTRACT_VERSION, PAGES_GLOBAL } from './contract.js';

const VIRTUAL_ENTRY = 'virtual:openmes-module-entry';
const RESOLVED_ENTRY = `\0${VIRTUAL_ENTRY}`;

/**
 * The generated entry. Globs the module's pages and registers each one under
 * the name Laravel passes to `Inertia::render()` — the path under Pages/,
 * without the extension, which is exactly how core names its own.
 */
function entrySource(pagesGlob) {
    return `
const pages = import.meta.glob(${JSON.stringify(pagesGlob)}, { eager: true });

// Created by core's runtime before any module script runs. The fallback keeps a
// module loadable in isolation (a test harness, a preview), where core is absent.
window.${PAGES_GLOBAL} = window.${PAGES_GLOBAL} ?? {};

for (const [path, page] of Object.entries(pages)) {
    const name = path
        .replace(/^.*\\/Pages\\//, '')
        .replace(/\\.jsx$/, '');

    window.${PAGES_GLOBAL}[name] = page;
}
`;
}

function readManifest(root) {
    const path = resolve(root, 'module.json');

    let raw;
    try {
        raw = readFileSync(path, 'utf8');
    } catch {
        throw new Error(
            `[openmes-module] No module.json at ${path}. The plugin takes the module's name from it.`,
        );
    }

    const manifest = JSON.parse(raw);

    if (!manifest.name || !/^[A-Za-z0-9_-]+$/.test(manifest.name)) {
        throw new Error(
            `[openmes-module] module.json "name" must be alphanumeric (got ${JSON.stringify(manifest.name)}). ` +
            'Core rejects anything else when installing the ZIP.',
        );
    }

    return manifest;
}

/**
 * @param {{ pages?: string, outDir?: string }} options
 *   pages  — glob for the module's Inertia pages. Root-absolute ('/…'), which
 *            Vite reads as relative to the project root: a glob inside a virtual
 *            module has no file of its own to be relative to, and Vite rejects
 *            anything else there.
 *   outDir — where the built file lands; core publishes it from here on enable.
 */
export function openmesModule(options = {}) {
    const pagesGlob = options.pages ?? '/resources/js/Pages/**/*.jsx';
    const outDir = options.outDir ?? 'public';

    let manifest = null;

    return {
        name: 'openmes-module',

        config(config) {
            const root = config.root ?? process.cwd();
            manifest = readManifest(root);

            return {
                build: {
                    outDir,
                    // A module's public/ may hold other published assets; wiping
                    // it on every build would take them with it.
                    emptyOutDir: false,
                    rollupOptions: {
                        input: VIRTUAL_ENTRY,
                        external: ALLOWED_IMPORTS,
                        output: {
                            format: 'iife',
                            name: `OpenMesModule_${manifest.name}`,
                            entryFileNames: `${manifest.name}.js`,
                            globals: CONTRACT,
                            // Core exposes namespace objects, so a default import
                            // has to read `.default`. Without this, `import X from`
                            // binds the namespace and the page renders nothing.
                            interop: 'esModule',
                            // One <script> is the whole delivery mechanism; a
                            // split chunk would 404 on the client.
                            inlineDynamicImports: true,
                        },
                    },
                },
            };
        },

        resolveId(source) {
            if (source === VIRTUAL_ENTRY) {
                return RESOLVED_ENTRY;
            }

            // The contract gate. An unknown '@core/…' import resolves to nothing
            // at runtime and the page dies in the customer's browser with
            // "undefined is not a function" — a build error naming the file is a
            // far better place to find out.
            if (source.startsWith('@core/') && ! ALLOWED_IMPORTS.includes(source)) {
                throw new Error(
                    `[openmes-module] "${source}" is not part of the core contract.\n` +
                    'Core only exposes what packages/module/contract.js lists. Either use an ' +
                    'exposed entry, or add it to the contract in core (which needs a core release).',
                );
            }

            return null;
        },

        load(id) {
            return id === RESOLVED_ENTRY ? entrySource(pagesGlob) : null;
        },

        /**
         * Record the contract the module was built against, next to the built
         * file. Core reads it when enabling the module and refuses a mismatch,
         * rather than letting a module built for an older core half-work.
         */
        generateBundle() {
            this.emitFile({
                type: 'asset',
                fileName: `${manifest.name}.runtime.json`,
                source: `${JSON.stringify({
                    module: manifest.name,
                    version: manifest.version ?? null,
                    requires_runtime: CONTRACT_VERSION,
                }, null, 2)}\n`,
            });
        },
    };
}
