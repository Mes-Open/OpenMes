/**
 * OpenMES design tokens — "Geist White" system.
 * Source of truth: design/openmes-fable-remix/project/OpenMES Components.dc.html
 *
 * Web mirrors these as Tailwind theme variables (--color-om-*) declared in
 * backend/resources/css/app.css — keep both in sync when a value changes.
 */

export const colors = {
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

    // Accent
    accent: '#EA5A2B',

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

    // Overlay scrim
    scrim: 'rgba(10, 9, 8, 0.4)',

    // Focus ring around accent-bordered inputs
    focusRing: 'rgba(234, 90, 43, 0.12)',
} as const;

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
