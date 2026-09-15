import { describe, expect, it } from 'vitest';

import { coreKey, moduleKey, resolvePage } from './pageResolver';

/**
 * Which component renders an Inertia page name.
 *
 * The case that matters is the miss: before this lookup existed the resolver
 * threw on an unknown name, so a route that outlived its page — a module that
 * isn't installed, a stale link — took the whole screen white. Returning null
 * is what lets app.jsx render a card the user can act on instead.
 */
describe('resolvePage', () => {
    const CorePage = () => null;
    const ModulePage = () => null;

    const corePages = {
        './Pages/admin/lines/Index.jsx': CorePage,
        './Pages/Dashboard.jsx': CorePage,
    };
    const modulePages = {
        '../../modules/Example/resources/js/Pages/admin/widgets/Index.jsx': ModulePage,
    };

    it('finds a page shipped by the app', () => {
        expect(resolvePage('admin/lines/Index', corePages, modulePages)).toBe(CorePage);
    });

    it('finds a page shipped by an installed module', () => {
        expect(resolvePage('admin/widgets/Index', corePages, modulePages)).toBe(ModulePage);
    });

    it('returns null for a page no build contains', () => {
        expect(resolvePage('admin/removed/Index', corePages, modulePages)).toBeNull();
    });

    it('returns null rather than throwing when no modules are installed', () => {
        // The community case: the module glob matched nothing and yielded {}.
        expect(resolvePage('admin/widgets/Index', corePages, {})).toBeNull();
        expect(resolvePage('admin/lines/Index', corePages)).toBe(CorePage);
    });

    it('lets core win, so a module cannot shadow a core screen', () => {
        const shadowing = {
            '../../modules/Evil/resources/js/Pages/admin/lines/Index.jsx': ModulePage,
        };

        expect(resolvePage('admin/lines/Index', corePages, shadowing)).toBe(CorePage);
    });

    it('matches a module page on the full path tail, not a loose substring', () => {
        // 'lines/Index' must not match '.../Pages/admin/lines/Index.jsx' — that
        // would make two differently-namespaced pages collide.
        expect(moduleKey(modulePages, 'widgets/Index')).toBeNull();
        expect(moduleKey(modulePages, 'admin/widgets/Index')).not.toBeNull();
    });

    it('keys core pages the way the glob does', () => {
        expect(coreKey('admin/lines/Index')).toBe('./Pages/admin/lines/Index.jsx');
    });
});
