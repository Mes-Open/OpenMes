/**
 * Phone top bar — safe-area-aware breadcrumb strip shown above every drawer
 * screen on handsets (tablets get the sidebar + clock strip instead).
 *
 * Crumbs follow the NAVIGATION TRAIL, not the URL: opening a work order from
 * the Alerts dashboard shows "Alerts Dashboard / #11", not the order's path.
 * The trail truncates when you revisit an earlier screen (going back, or
 * tapping a crumb) and resets when you jump to a bottom-nav root. On a cold
 * deep link the trail starts at the current screen and the back chevron falls
 * back to the URL's parent path.
 */
import { usePathname, useRouter } from 'expo-router';
import { useEffect, useSyncExternalStore } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { HeroIcon } from '@/components/ui/HeroIcon';
import { useAuthStore } from '@/stores/authStore';

/** Industry acronyms that must not be title-cased ("hr" → "HR", not "Hr"). */
const ACRONYMS: Record<string, string> = {
  hr: 'HR',
  oee: 'OEE',
  mqtt: 'MQTT',
  opcua: 'OPC UA',
  qc: 'QC',
  api: 'API',
  eans: 'EANs',
};

/** "work-orders" → "Work Orders"; "qc-triggers" → "QC Triggers". */
function prettify(segment: string): string {
  return segment
    .split('-')
    .map((w) => ACRONYMS[w] ?? (w ? w[0].toUpperCase() + w.slice(1) : w))
    .join(' ');
}

/** Label for a screen = its last path segment; numeric ids render as #id. */
function labelFor(path: string): string {
  const seg = path.split('/').filter(Boolean).pop() ?? '';
  return /^\d+$/.test(seg) ? `#${seg}` : prettify(seg);
}

/** Jumping to one of these resets the trail (bottom-nav roots + role homes). */
const TRAIL_ROOTS = new Set([
  '/admin/dashboard',
  '/admin/alerts-dashboard',
  '/admin/work-orders',
  '/supervisor',
]);

/**
 * Section roots that are pure redirects (no hub screens — navigation lives in
 * the sidebar/bottom nav, like the web). Mirrors the app/(drawer)/<section>/
 * index.tsx redirects; climbing "up" into one of these would bounce straight
 * back, so back navigation resolves through (or hides at) them.
 */
const SECTION_REDIRECTS: Record<string, string> = {
  '/admin': '/admin/dashboard',
  '/structure': '/structure/sites',
  '/hr': '/hr/workers',
  '/production': '/production/product-types',
  '/maintenance': '/maintenance/events',
  '/orders': '/admin/work-orders',
  '/quality': '/quality/inspections',
  '/admin/orders': '/admin/work-orders',
};

const MAX_TRAIL = 8;

// ── Module-level trail store (the bar remounts per screen; the trail must not).
interface TrailEntry {
  path: string;
  label: string;
}
let trail: TrailEntry[] = [];
const listeners = new Set<() => void>();

function visit(path: string) {
  const last = trail[trail.length - 1];
  if (last?.path === path) return;
  let next: TrailEntry[];
  if (TRAIL_ROOTS.has(path)) {
    next = [{ path, label: labelFor(path) }];
  } else {
    const i = trail.findIndex((e) => e.path === path);
    next =
      i >= 0
        ? trail.slice(0, i + 1)
        : [...trail, { path, label: labelFor(path) }].slice(-MAX_TRAIL);
  }
  trail = next;
  listeners.forEach((l) => l());
}

function subscribe(l: () => void) {
  listeners.add(l);
  return () => listeners.delete(l);
}

/** Wipe the trail — a different user's navigation must not leak into it. */
function resetTrail() {
  trail = [];
  listeners.forEach((l) => l());
}

let lastUserId: number | string | null = null;

export function PhoneTopBar() {
  const { t } = useTranslation();
  const router = useRouter();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();

  const userId = useAuthStore((s) => s.user?.id ?? null);
  useEffect(() => {
    if (userId !== lastUserId) {
      lastUserId = userId;
      resetTrail();
    }
    visit(pathname);
  }, [pathname, userId]);
  const entries = useSyncExternalStore(subscribe, () => trail);

  // Show at most the last 3 trail entries, with a leading ellipsis when older
  // screens are hidden.
  const shown = entries.slice(-3);
  const overflow = entries.length > shown.length;

  const parentFromUrl = () => {
    const segs = pathname.split('/').filter(Boolean);
    if (segs.length <= 1) return null;
    const parent = '/' + segs.slice(0, -1).join('/');
    const resolved = SECTION_REDIRECTS[parent] ?? parent;
    // A parent that redirects straight back to where we are is not a back target.
    return resolved === pathname ? null : resolved;
  };

  // Trail-based back — NEVER blind history. Browser/native history survives
  // logout/login (and trail resets), so popping it can land in another user's
  // or another section's screens; the trail is the truth of how we got here.
  const goBack = () => {
    if (entries.length > 1) {
      router.push(entries[entries.length - 2].path as never);
      return;
    }
    // Trail root (or cold deep link): climb the URL to the parent screen.
    const parent = parentFromUrl();
    if (parent) router.push(parent as never);
  };
  const canBack = entries.length > 1 || parentFromUrl() != null;

  return (
    <View style={[styles.bar, { paddingTop: insets.top + 6 }]}>
      {canBack ? (
        <Pressable onPress={goBack} hitSlop={10} style={({ pressed }) => [styles.backBtn, pressed && { opacity: 0.5 }]}>
          <HeroIcon name="chevronLeft" size={20} color={colors.ink} />
        </Pressable>
      ) : (
        <View style={styles.backSpacer} />
      )}
      <View style={styles.crumbs}>
        {overflow ? <Text style={styles.sep}>…</Text> : null}
        {shown.map((e, i) => {
          const isLast = i === shown.length - 1;
          return (
            <View key={e.path} style={styles.crumbWrap}>
              {i > 0 || overflow ? <Text style={styles.sep}>/</Text> : null}
              {isLast ? (
                <Text numberOfLines={1} style={[styles.crumb, styles.crumbLast]}>
                  {t(e.label)}
                </Text>
              ) : (
                <Pressable onPress={() => router.push(e.path as never)} hitSlop={6}>
                  <Text numberOfLines={1} style={styles.crumb}>
                    {t(e.label)}
                  </Text>
                </Pressable>
              )}
            </View>
          );
        })}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingHorizontal: 10,
    paddingBottom: 8,
    backgroundColor: colors.bg,
    borderBottomWidth: 1,
    borderBottomColor: colors.line2,
  },
  backBtn: { padding: 4 },
  backSpacer: { width: 28 },
  crumbs: { flex: 1, flexDirection: 'row', alignItems: 'center', flexWrap: 'nowrap', overflow: 'hidden' },
  crumbWrap: { flexDirection: 'row', alignItems: 'center', flexShrink: 1 },
  sep: { fontSize: 12, color: colors.faintest, marginHorizontal: 5 },
  crumb: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  crumbLast: { color: colors.ink, fontFamily: fonts.sans.native.semibold },
});
