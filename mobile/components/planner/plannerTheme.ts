/**
 * Planner status/priority colours mapped onto the shared @openmes/ui tokens.
 *
 * The web planner reads `--om-*` CSS variables directly (which is how it gets
 * dark mode for free). React Native has no CSS variables, so this module is the
 * equivalent lookup — `colors` is a live object that applyTheme() mutates, so
 * read it inside render, never at module scope.
 */

import { colors } from '@openmes/ui';

export type PlannerStatus =
  | 'PENDING'
  | 'ACCEPTED'
  | 'IN_PROGRESS'
  | 'BLOCKED'
  | 'PAUSED'
  | 'DONE';

export interface StatusPair {
  label: string;
  fg: string;
  bg: string;
}

/** Mirrors STATUS in the web planner's helpers.js. */
export function statusOf(status: string | null | undefined): StatusPair {
  switch (status) {
    case 'ACCEPTED':
      return { label: 'Accepted', fg: colors.accepted, bg: colors.acceptedBg };
    case 'IN_PROGRESS':
      return { label: 'Running', fg: colors.running, bg: colors.runningBg };
    case 'BLOCKED':
      return { label: 'Blocked', fg: colors.blocked, bg: colors.blockedBg };
    case 'PAUSED':
      return { label: 'Paused', fg: colors.downtime, bg: colors.downtimeBg };
    case 'DONE':
      return { label: 'Done', fg: colors.done, bg: colors.doneBg };
    case 'PENDING':
    default:
      return { label: 'Pending', fg: colors.pending, bg: colors.pendingBg };
  }
}

/** Priority on the OpenMES 1–5 scale (0 reads as Lowest). */
export function priorityMeta(p: number | null | undefined): { label: string; color: string } {
  const v = p ?? 0;
  if (v >= 5) return { label: 'Urgent', color: colors.blocked };
  if (v === 4) return { label: 'High', color: colors.accent };
  if (v === 3) return { label: 'Medium', color: colors.downtime };
  if (v === 2) return { label: 'Low', color: colors.accepted };
  return { label: 'Lowest', color: colors.faint };
}

/** Load heat, matching the capacity view's thresholds. */
export function loadColor(pct: number): string {
  if (pct > 100) return colors.blocked;
  if (pct > 80) return colors.downtime;
  return colors.running;
}

/**
 * Customer-tier marker. Mirrors TIER_DOT in the web planner's OrderCard.jsx —
 * the same Tailwind palette values, since these aren't brand tokens.
 */
const TIER_DOT: Record<string, string> = {
  bronze: '#f59e0b', // amber-500
  silver: '#9ca3af', // gray-400
  gold: '#facc15', // yellow-400
  vip: '#a855f7', // purple-500
};

export function tierColor(tier: string | null | undefined): string | null {
  return tier ? (TIER_DOT[tier] ?? null) : null;
}

/** Soft fg/bg pair per tier — the backlog card's badge (TIER_BADGE_STYLES). */
const TIER_BADGE: Record<string, { fg: string; bg: string }> = {
  bronze: { fg: '#92400e', bg: '#fef3c7' }, // amber-800 on amber-100
  silver: { fg: '#374151', bg: '#e5e7eb' }, // gray-700 on gray-200
  gold: { fg: '#854d0e', bg: '#fef9c3' }, // yellow-800 on yellow-100
  vip: { fg: '#6b21a8', bg: '#f3e8ff' }, // purple-800 on purple-100
};

export function tierBadge(tier: string | null | undefined): { fg: string; bg: string } | null {
  return tier ? (TIER_BADGE[tier] ?? null) : null;
}

export const TIER_VALUES = ['bronze', 'silver', 'gold', 'vip'] as const;

/**
 * Translation key per tier — mirrors tierOptions() in the web's customers/fields.
 * Don't derive these by capitalising the value: 'vip' would give 'Vip', which
 * isn't a key in the catalogs (the label is 'VIP').
 */
const TIER_LABEL: Record<string, string> = {
  bronze: 'Bronze',
  silver: 'Silver',
  gold: 'Gold',
  vip: 'VIP',
};

export function tierLabel(tier: string | null | undefined): string {
  return tier ? (TIER_LABEL[tier] ?? tier) : '';
}

/** Maintenance events — purple, distinct from every work-order status. */
export function maintColors(): { fg: string; bg: string } {
  return { fg: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.10)' };
}

/**
 * Decorative accents telling the shift sub-columns apart. These carry no status
 * meaning, which is why they're literals rather than tokens (same as the web).
 */
const SHIFT_COLORS: Record<number, string> = {
  1: '#6366f1',
  2: '#0ea5e9',
  3: '#14b8a6',
  4: '#8b5cf6',
};

export function shiftColor(n: number): string {
  return SHIFT_COLORS[n] ?? colors.accent;
}
