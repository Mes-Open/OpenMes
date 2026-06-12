// OpenMES — Industrial-utility palette (signal amber on near-black / warm-light)
// Operator screens lean dark for shop-floor focus; supervisor/admin lean warm-light for data density.

const tintColorLight = '#f59021';
const tintColorDark = '#f5a524';

const Colors = {
  light: {
    // surfaces
    text: '#171715',
    textMuted: '#5f5d56',
    textFaint: '#8a8780',
    background: '#f3f1ec',
    surface: '#ffffff',
    surfaceAlt: '#ebe8e0',
    surfaceInverse: '#171715',
    border: '#d9d5cb',
    borderStrong: '#bfbab0',
    // brand
    tint: tintColorLight,
    accent: '#f59021',
    brandNavy: '#1f2547',
    // navigation
    tabIconDefault: '#8a8780',
    tabIconSelected: tintColorLight,
    // status
    success: '#1f9d6c',
    warning: '#f5a524',
    danger: '#dc2626',
    info: '#3a6ed6',
    // soft fills
    successSoft: '#dff5e9',
    warningSoft: '#fbe9c8',
    dangerSoft: '#fbe2e2',
    infoSoft: '#e2ecfa',
  },
  dark: {
    text: '#eaeaea',
    textMuted: '#9a9aa2',
    textFaint: '#6a6a72',
    background: '#0e0e10',
    surface: '#16161a',
    surfaceAlt: '#1d1d22',
    surfaceInverse: '#ffffff',
    border: '#26262d',
    borderStrong: '#3a3a44',
    tint: tintColorDark,
    accent: '#f5a524',
    brandNavy: '#1f2547',
    tabIconDefault: '#6a6a72',
    tabIconSelected: tintColorDark,
    success: '#3ecf8e',
    warning: '#f5a524',
    danger: '#ef4444',
    info: '#5b8def',
    successSoft: '#0e3424',
    warningSoft: '#3a2a0c',
    dangerSoft: '#3a0e0e',
    infoSoft: '#0e1a3a',
  },
};

export default Colors;

// Mono font — used for IDs, codes, timestamps, KPI numbers.
// Family is Geist Mono, registered in app/_layout.tsx via @expo-google-fonts.
// Design only loads weights 400 / 500 / 600 — Bold maps to SemiBold so we
// don't ship a fourth Mono weight.
export const MONO = 'GeistMono_500Medium';
export const MONO_REGULAR = 'GeistMono_400Regular';
export const MONO_SEMIBOLD = 'GeistMono_600SemiBold';
export const MONO_BOLD = 'GeistMono_600SemiBold';

// Sans font — Geist, primary face for titles + body text.
export const SANS_REGULAR = 'Geist_400Regular';
export const SANS_MEDIUM = 'Geist_500Medium';
export const SANS_SEMIBOLD = 'Geist_600SemiBold';
export const SANS_BOLD = 'Geist_700Bold';

// Brand colors used regardless of scheme
export const BRAND = {
  amber: '#f59021',
  amberSoft: '#fbe9c8',
  amberAccent: '#f5a524',
  navy: '#1f2547',
};

export type StatusKind = 'pending' | 'inProgress' | 'blocked' | 'paused' | 'done' | 'cancelled' | 'rejected';

// Status palette — pill backgrounds tuned for both schemes (using soft tints with strong fg).
export const statusPalette: Record<StatusKind, { bg: string; fg: string; dot: string }> = {
  pending: { bg: '#ebe8e0', fg: '#5f5d56', dot: '#8a8780' },
  inProgress: { bg: '#dff5e9', fg: '#0f7a4f', dot: '#1f9d6c' },
  blocked: { bg: '#fbe2e2', fg: '#b91c1c', dot: '#dc2626' },
  paused: { bg: '#fbe9c8', fg: '#8a5a0e', dot: '#f5a524' },
  done: { bg: '#e6e4dd', fg: '#5f5d56', dot: '#8a8780' },
  cancelled: { bg: '#ebe8e0', fg: '#8a8780', dot: '#8a8780' },
  rejected: { bg: '#fbe2e2', fg: '#991b1b', dot: '#dc2626' },
};

// Dark-scheme equivalents for use inside dark surfaces (operator screens).
export const statusPaletteDark: Record<StatusKind, { bg: string; fg: string; dot: string }> = {
  pending: { bg: '#1f1f24', fg: '#a8a8b0', dot: '#6a6a72' },
  inProgress: { bg: '#0e3424', fg: '#7eebbf', dot: '#3ecf8e' },
  blocked: { bg: '#3a0e0e', fg: '#ff9999', dot: '#ef4444' },
  paused: { bg: '#332b1c', fg: '#e6c585', dot: '#e6c585' },
  done: { bg: '#1f1f24', fg: '#a8a8b0', dot: '#a8a8b0' },
  cancelled: { bg: '#1f1f24', fg: '#6a6a72', dot: '#6a6a72' },
  rejected: { bg: '#3a0e0e', fg: '#ff9999', dot: '#ef4444' },
};

export function statusKindFor(status: string | undefined | null): StatusKind {
  switch ((status ?? '').toUpperCase()) {
    case 'IN_PROGRESS':
    case 'ACKNOWLEDGED':
    case 'ACCEPTED':
      return 'inProgress';
    case 'BLOCKED':
    case 'OPEN':
      return 'blocked';
    case 'PAUSED':
      return 'paused';
    case 'DONE':
    case 'RESOLVED':
    case 'CLOSED':
      return 'done';
    case 'CANCELLED':
      return 'cancelled';
    case 'REJECTED':
      return 'rejected';
    default:
      return 'pending';
  }
}
