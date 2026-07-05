/**
 * OpenMES design tokens — "Geist White" system.
 * Source of truth: design/openmes-fable-remix/project/OpenMES Components.dc.html
 *
 * Web mirrors these as Tailwind theme variables (--color-om-*) declared in
 * backend/resources/css/app.css — keep both in sync when a value changes.
 */

/** Light ("Geist White") palette — mirrors :root --om-* in backend app.css. */
const LIGHT = {
    // Surfaces
    bg: '#F6F5F1',
    panel: '#FBFAF8',
    card: '#FFFFFF',
    chip: '#F1EFEA',
    selectedRow: '#FCF6F3',

    // Hairlines
    line: '#E6E4DE',
    line2: '#EDEBE6',

    // Text
    ink: '#1A1917',
    muted: '#6F6C66',
    faint: '#9B9892',
    faintest: '#C4C0B8',

    // Foreground on ink surfaces (primary buttons, active nav) — flips in
    // dark mode since ink itself flips light (web --om-on-ink).
    onInk: '#FFFFFF',

    // Accent
    accent: '#EA5A2B',
    accentBg: '#FBEDE7',

    // States (fg / bg pairs)
    running: '#1C9A55',
    runningBg: '#E6F4EA',
    pending: '#6F6C66',
    pendingBg: '#F1EFEA',
    blocked: '#D6442F',
    blockedBg: '#FBEAE6',
    downtime: '#C9821E',
    downtimeBg: '#FAF0DD',
    done: '#54514B',
    doneBg: '#ECEBE7',
    accepted: '#3E73C4',
    acceptedBg: '#E7EFFA',

    // Overlay scrim
    scrim: 'rgba(10, 9, 8, 0.4)',

    // Focus ring around accent-bordered inputs
    focusRing: 'rgba(234, 90, 43, 0.12)',
};

/** Dark palette — mirrors html.dark --om-* in backend app.css verbatim. */
const DARK: typeof LIGHT = {
    bg: '#131211',
    panel: '#161513',
    card: '#1C1B19',
    chip: '#242220',
    selectedRow: '#231C18',

    line: '#2C2A27',
    line2: '#242220',

    ink: '#F4F2ED',
    muted: '#A6A29B',
    faint: '#6E6A63',
    faintest: '#3A3833',

    onInk: '#131211',

    accent: '#F2632F',
    accentBg: 'rgba(242, 99, 47, 0.16)',

    running: '#2BBE6E',
    runningBg: 'rgba(43, 190, 110, 0.16)',
    pending: '#A6A29B',
    pendingBg: '#242220',
    blocked: '#F2604A',
    blockedBg: 'rgba(242, 96, 74, 0.15)',
    downtime: '#E0A53C',
    downtimeBg: 'rgba(224, 165, 60, 0.16)',
    done: '#A6A29B',
    doneBg: '#242220',
    accepted: '#5E92E0',
    acceptedBg: 'rgba(94, 146, 224, 0.16)',

    scrim: 'rgba(0, 0, 0, 0.6)',
    focusRing: 'rgba(242, 99, 47, 0.18)',
};

/**
 * Live token object. Mutated in place by applyTheme() so every consumer that
 * reads `colors.*` at render time — and, on the mobile app, every lazily
 * required screen module — picks up the active theme. Mirrors the web's
 * runtime --om-* variable flip (html.dark).
 */
export const colors: typeof LIGHT = { ...LIGHT };

export type ThemeName = 'light' | 'dark';

let activeTheme: ThemeName = 'light';

/** The theme applyTheme() last activated. */
export function currentTheme(): ThemeName {
    return activeTheme;
}

/**
 * Switch the token set. Call before screens render (styles created earlier
 * keep the previous values — the mobile app reloads after toggling, exactly
 * like the web's full-CSS variable flip is instant only because it's CSS).
 */
export function applyTheme(theme: ThemeName): void {
    activeTheme = theme;
    Object.assign(colors, theme === 'dark' ? DARK : LIGHT);
    (Object.keys(statusColors) as StatusKey[]).forEach((k) => {
        statusColors[k] = { fg: colors[k], bg: colors[`${k}Bg`] };
    });
}

// Web: resolve the persisted theme synchronously at module init, before any
// consumer bakes token values. 'om-theme' is written by the mobile app's
// toggle (Expo web); 'theme' is the Inertia app's own toggle key, so the
// package's JS-token consumers follow the same switch as the CSS variables.
if (typeof localStorage !== 'undefined') {
    try {
        const stored = localStorage.getItem('om-theme') ?? localStorage.getItem('theme');
        if (stored === 'dark') {
            activeTheme = 'dark';
            Object.assign(colors, DARK);
        }
    } catch {
        // storage unavailable (private mode) — stay light
    }
}

export type StatusKey = 'running' | 'pending' | 'blocked' | 'downtime' | 'done';

/** fg/bg pair per workflow state — green=running, gray=pending, red=blocked, amber=downtime, faded ink=done. */
export const statusColors: Record<StatusKey, { fg: string; bg: string }> = {
    running: { fg: colors.running, bg: colors.runningBg },
    pending: { fg: colors.pending, bg: colors.pendingBg },
    blocked: { fg: colors.blocked, bg: colors.blockedBg },
    downtime: { fg: colors.downtime, bg: colors.downtimeBg },
    done: { fg: colors.done, bg: colors.doneBg },
};

export const radius = {
    /** Cards, menus, modals */
    md: 12,
    /** Buttons, inputs, controls */
    sm: 8,
    /** Pills, badges, switch track */
    pill: 20,
    /** Phone-frame / sheet corners */
    sheet: 20,
} as const;

export const spacing = {
    xs: 4,
    sm: 8,
    md: 12,
    lg: 16,
    xl: 20,
    xxl: 24,
} as const;

/**
 * Font families. Web loads Geist via Google Fonts (inertia.blade.php);
 * native loads @expo-google-fonts/geist + geist-mono, whose PostScript
 * names are the keys used by StyleSheet.
 */
export const fonts = {
    sans: {
        web: "'Geist', system-ui, sans-serif",
        native: {
            regular: 'Geist_400Regular',
            medium: 'Geist_500Medium',
            semibold: 'Geist_600SemiBold',
            bold: 'Geist_700Bold',
        },
    },
    mono: {
        web: "'Geist Mono', ui-monospace, monospace",
        native: {
            regular: 'GeistMono_400Regular',
            medium: 'GeistMono_500Medium',
            semibold: 'GeistMono_600SemiBold',
        },
    },
} as const;

/** Mono uppercase letterspaced label style values (the system's metadata idiom). */
export const monoLabel = {
    fontSize: 10,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
} as const;

export const shadows = {
    /** Dropdown / popover */
    menu: '0 18px 44px -18px rgba(0,0,0,.3)',
    /** Modal dialog */
    modal: '0 20px 50px -20px rgba(0,0,0,.35)',
    /** Toast */
    toast: '0 14px 34px -18px rgba(0,0,0,.3)',
} as const;
