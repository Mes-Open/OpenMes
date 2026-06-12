// OpenMES — Geist White palette (design/openmes-fable-remix; see packages/ui/src/tokens).
// Light-only v1: the `dark` block (and statusPaletteDark) intentionally mirrors
// the light values, so every screen doing Colors[scheme] renders Geist White
// regardless of device scheme; the dark shop-floor variant returns later via
// token theming. Export surface is unchanged — only the values moved.

import { colors as om } from '@openmes/ui';

const geistWhite = {
  // surfaces
  text: om.ink,
  textMuted: om.muted,
  textFaint: om.faint,
  background: om.bg,
  surface: om.card,
  surfaceAlt: om.chip,
  surfaceInverse: om.ink,
  border: om.line,
  borderStrong: om.faintest,
  // brand
  tint: om.accent,
  accent: om.accent,
  brandNavy: '#1f2547',
  // navigation
  tabIconDefault: om.faint,
  tabIconSelected: om.ink,
  // status
  success: om.running,
  warning: om.downtime,
  danger: om.blocked,
  info: om.accent,
  // soft fills
  successSoft: om.runningBg,
  warningSoft: om.downtimeBg,
  dangerSoft: om.blockedBg,
  infoSoft: om.chip,
};

const Colors = {
  light: geistWhite,
  dark: { ...geistWhite },
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
  amber: om.accent,
  amberSoft: om.chip,
  amberAccent: om.accent,
  navy: '#1f2547',
};

export type StatusKind = 'pending' | 'inProgress' | 'blocked' | 'paused' | 'done' | 'cancelled' | 'rejected';

// Status palette — fg/bg pairs from the Geist White state tokens.
export const statusPalette: Record<StatusKind, { bg: string; fg: string; dot: string }> = {
  pending: { bg: om.pendingBg, fg: om.pending, dot: om.faint },
  inProgress: { bg: om.runningBg, fg: om.running, dot: om.running },
  blocked: { bg: om.blockedBg, fg: om.blocked, dot: om.blocked },
  paused: { bg: om.downtimeBg, fg: om.downtime, dot: om.downtime },
  done: { bg: om.doneBg, fg: om.done, dot: om.faint },
  cancelled: { bg: om.pendingBg, fg: om.faint, dot: om.faint },
  rejected: { bg: om.blockedBg, fg: om.blocked, dot: om.blocked },
};

// Light-only v1 — mirrors statusPalette (dark shop-floor variant returns later).
export const statusPaletteDark: Record<StatusKind, { bg: string; fg: string; dot: string }> = statusPalette;

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
