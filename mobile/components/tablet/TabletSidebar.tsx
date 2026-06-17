import { FontAwesome } from '@expo/vector-icons';
import {
  DrawerContentScrollView,
  type DrawerContentComponentProps,
} from '@react-navigation/drawer';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { usePathname, useRouter } from 'expo-router';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { logout } from '@/api/auth';
import { BrandLogo } from '@/components/ui/Brand';
import { Mono } from '@/components/ui/Mono';
import { RoleBadge, roleColor } from '@/components/ui/RoleBadge';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAlertCounts } from '@/hooks/queries/useSystem';
import { getRole, useAuthStore } from '@/stores/authStore';
import { useSettingsStore, type ThemePreference } from '@/stores/settingsStore';

interface NavItem {
  key: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  label: string;
  route: string;
  match: string;
}

// `match` is an absolute pathname prefix. expo-router strips route groups
// like `(drawer)` and `(tabs)` so the runtime pathnames look like the
// segments below. Active selection uses longest-prefix-wins (see findActive
// below) so /admin/oee picks OEE, never both OEE and Catalog.
//
// Items are role-scoped per the design (om-tablet-frame.jsx OM_ROLES):
//   • Operator → Today / Orders / Scan / Alerts / Shift
//   • Supervisor → Floor / Orders / Lines / Alerts / Schedule / Maint / Reports
//   • Admin → Wall / Orders / Lines / OEE / Maint / MQTT / Users / Catalog
// HomeTab dispatches the (tabs)/index route to the right per-role dashboard
// (TabletPlantWall for admins, SupervisorWarRoom for supers, etc.) so the
// "home" rail icon resolves to the design-labelled landing without us
// needing separate routes for Wall/Floor/Today.

// Operator-scoped URLs — each item mounts the shared screen at /operator/<key>
// so the operator stays inside their role's URL space (same pattern as admin).
const OPERATOR_ITEMS: NavItem[] = [
  { key: 'home', icon: 'home', label: 'Today', route: '/(drawer)/operator/today', match: '/operator/today' },
  { key: 'orders', icon: 'list-alt', label: 'Orders', route: '/(drawer)/operator/orders', match: '/operator/orders' },
  { key: 'scan', icon: 'qrcode', label: 'Scan', route: '/(drawer)/operator/scan', match: '/operator/scan' },
  { key: 'alerts', icon: 'bell', label: 'Alerts', route: '/(drawer)/operator/alerts', match: '/operator/alerts' },
  { key: 'shift', icon: 'calendar', label: 'Shift', route: '/(drawer)/operator/shift', match: '/operator/shift' },
];

const SUPERVISOR_ITEMS: NavItem[] = [
  { key: 'home', icon: 'home', label: 'Floor', route: '/(drawer)/(tabs)', match: '/' },
  { key: 'orders', icon: 'list-alt', label: 'Orders', route: '/(drawer)/orders', match: '/orders' },
  { key: 'lines', icon: 'sitemap', label: 'Lines', route: '/(drawer)/structure', match: '/structure' },
  { key: 'alerts', icon: 'bell', label: 'Alerts', route: '/(drawer)/(tabs)/issues', match: '/issues' },
  { key: 'schedule', icon: 'calendar', label: 'Schedule', route: '/(drawer)/schedule', match: '/schedule' },
  { key: 'employee-schedule', icon: 'users', label: 'Workers', route: '/(drawer)/admin/employee-schedule', match: '/admin/employee-schedule' },
  { key: 'maint', icon: 'wrench', label: 'Maint', route: '/(drawer)/maintenance/' as string, match: '/maintenance' },
  { key: 'reports', icon: 'line-chart', label: 'Reports', route: '/(drawer)/admin/reports', match: '/reports' },
];

const ADMIN_ITEMS: NavItem[] = [
  // Admin lands on the dedicated dashboard URL — matches the web admin path
  // /admin/dashboard so the two surfaces share the same vocabulary. Shared
  // screens (Orders, Lines, …) get admin-scoped URLs by mounting the same
  // screen file at /admin/<resource> so the URL stays in admin context.
  { key: 'home', icon: 'home', label: 'Dashboard', route: '/(drawer)/admin/dashboard', match: '/admin/dashboard' },
  { key: 'orders', icon: 'list-alt', label: 'Orders', route: '/(drawer)/admin/orders', match: '/admin/orders' },
  { key: 'lines', icon: 'sitemap', label: 'Lines', route: '/(drawer)/structure', match: '/structure' },
  { key: 'oee', icon: 'line-chart', label: 'OEE', route: '/(drawer)/admin/oee', match: '/admin/oee' },
  { key: 'schedule', icon: 'calendar', label: 'Schedule', route: '/(drawer)/admin/schedule', match: '/admin/schedule' },
  { key: 'employee-schedule', icon: 'users', label: 'Workers', route: '/(drawer)/admin/employee-schedule', match: '/admin/employee-schedule' },
  { key: 'maint', icon: 'wrench', label: 'Maint', route: '/(drawer)/maintenance/' as string, match: '/maintenance' },
  { key: 'mqtt', icon: 'plug', label: 'MQTT', route: '/(drawer)/admin/connectivity-admin', match: '/connectivity' },
  { key: 'users', icon: 'users', label: 'Users', route: '/(drawer)/admin/users', match: '/admin/users' },
  { key: 'catalog', icon: 'cog', label: 'Catalog', route: '/admin', match: '/admin' },
];

function itemsForRole(role: 'Admin' | 'Supervisor' | 'Operator' | null): NavItem[] {
  if (role === 'Admin') return ADMIN_ITEMS;
  if (role === 'Supervisor') return SUPERVISOR_ITEMS;
  return OPERATOR_ITEMS;
}

/** Pick the deepest matching item for `pathname` so /admin/oee picks OEE
 * (whose `match` is /admin/oee) rather than both OEE and Admin. Exact "/"
 * is treated specially since every other match also starts with "/". */
function findActiveKey(pathname: string, items: NavItem[]): string | null {
  let best: NavItem | null = null;
  for (const it of items) {
    const m = it.match;
    const isMatch =
      m === '/'
        ? pathname === '/'
        : pathname === m || pathname.startsWith(m + '/');
    if (isMatch && (best == null || m.length > best.match.length)) {
      best = it;
    }
  }
  return best?.key ?? null;
}

/**
 * Slim icon sidebar for tablet landscape layouts. Renders as the drawer content
 * when the drawer is in permanent mode (tablet), with icon + mono uppercase
 * label per route and a role badge at the bottom.
 */
export function TabletSidebar(props: DrawerContentComponentProps) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const router = useRouter();
  const pathname = usePathname();
  const qc = useQueryClient();
  const { t } = useTranslation();

  const user = useAuthStore((s) => s.user);
  const role = getRole(user);
  const clear = useAuthStore((s) => s.clear);

  const themePref = useSettingsStore((s) => s.theme);
  const setTheme = useSettingsStore((s) => s.setTheme);
  // Cycle order matches profile chip order: SYSTEM → LIGHT → DARK → SYSTEM.
  const cycleTheme = () => {
    const next: ThemePreference =
      themePref === 'system' ? 'light' : themePref === 'light' ? 'dark' : 'system';
    setTheme(next);
  };

  // Sidebar badge mirrors the web AlertController total — overdue WOs +
  // blocked WOs + blocking issues + machine offline + maintenance.
  const alertCountsQ = useAlertCounts();
  const alertsTotal = alertCountsQ.data?.total ?? 0;

  const logoutMutation = useMutation({
    mutationFn: () => logout().catch(() => undefined),
    onSettled: () => {
      qc.clear();
      clear();
    },
  });

  const items = itemsForRole(role);
  const activeKey = findActiveKey(pathname, items);
  const isActive = (item: NavItem) => item.key === activeKey;

  const onSelect = (item: NavItem) => {
    router.push(item.route as never);
  };

  const initials = (() => {
    const name = user?.name ?? user?.username ?? '';
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return (name.slice(0, 2) || '?').toUpperCase();
  })();

  return (
    <DrawerContentScrollView
      {...props}
      contentContainerStyle={[styles.scroll, { backgroundColor: palette.surface }]}
      showsVerticalScrollIndicator={false}>
      {/* Logo block — uses the official wordmark + gear asset rather than a
          generated mark, so it matches every other surface in the app. */}
      <View style={styles.logoWrap}>
        <BrandLogo size={20} />
      </View>

      {/* Role badge — color-coded per role per the design (operator amber,
          supervisor blue, admin red). Anchors the user to the active role. */}
      <View style={styles.roleWrap}>
        <RoleBadge role={role} />
      </View>

      {/* Nav rail */}
      <View style={{ gap: 6 }}>
        {items.map((item) => {
          const active = isActive(item);
          const badge = item.key === 'alerts' && alertsTotal > 0 ? alertsTotal : null;
          return (
            <Pressable
              key={item.key}
              onPress={() => onSelect(item)}
              style={({ pressed }) => [
                styles.item,
                {
                  backgroundColor: active
                    ? scheme === 'dark'
                      ? '#241a08'
                      : palette.surfaceAlt
                    : 'transparent',
                  borderColor: active ? (scheme === 'dark' ? BRAND.amber : palette.border) : 'transparent',
                  opacity: pressed ? 0.7 : 1,
                },
              ]}>
              <View style={{ position: 'relative' }}>
                <FontAwesome
                  name={item.icon}
                  size={22}
                  color={active ? BRAND.amber : palette.textMuted}
                />
                {badge ? (
                  <View style={styles.badge}>
                    <Text style={styles.badgeText}>{badge > 9 ? '9+' : badge}</Text>
                  </View>
                ) : null}
              </View>
              <Mono
                size={10}
                letterSpacing={0.4}
                color={active ? (scheme === 'dark' ? BRAND.amber : palette.text) : palette.textMuted}>
                {t(item.label).toUpperCase()}
              </Mono>
            </Pressable>
          );
        })}
      </View>

      <View style={{ flex: 1 }} />

      {/* Theme toggle — cycles System → Light → Dark → System. Tiny icon
          matches the design (sun-ish glyph just above the user badge). */}
      <Pressable
        onPress={cycleTheme}
        accessibilityRole="button"
        accessibilityLabel={`Theme: ${themePref}`}
        hitSlop={8}
        style={({ pressed }) => [styles.themeToggle, { opacity: pressed ? 0.5 : 1 }]}>
        <FontAwesome
          name={themePref === 'dark' ? 'moon-o' : themePref === 'light' ? 'sun-o' : 'mobile'}
          size={18}
          color={palette.textMuted}
        />
      </Pressable>

      {/* User badge bottom — color-coded by role; long-press to sign out. */}
      <Pressable
        onPress={() => router.push('/(drawer)/(tabs)/profile' as never)}
        onLongPress={() => {
          Alert.alert('Sign out', `Sign out ${user?.username ?? ''}?`, [
            { text: 'Cancel', style: 'cancel' },
            { text: 'Sign out', style: 'destructive', onPress: () => logoutMutation.mutate() },
          ]);
        }}
        style={[styles.badgeBlock, { backgroundColor: palette.surfaceAlt }]}>
        <View style={[styles.avatar, { backgroundColor: roleColor(role) }]}>
          <Text style={styles.avatarText}>{initials}</Text>
        </View>
        <Mono size={9.5} color={palette.textMuted} letterSpacing={0.3} style={{ marginTop: 6 }}>
          {(user?.username ?? role ?? 'OPERATOR').toUpperCase()}
        </Mono>
      </Pressable>
    </DrawerContentScrollView>
  );
}

const styles = StyleSheet.create({
  scroll: {
    flexGrow: 1,
    paddingTop: 20,
    paddingBottom: 16,
    paddingHorizontal: 12,
  },
  logoWrap: { alignItems: 'center', marginBottom: 12 },
  roleWrap: { alignItems: 'center', marginBottom: 18 },
  item: {
    paddingVertical: 12,
    borderRadius: 12,
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
  },
  badge: {
    position: 'absolute',
    top: -4,
    right: -8,
    minWidth: 16,
    height: 16,
    paddingHorizontal: 4,
    borderRadius: 8,
    backgroundColor: '#c0392b',
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: {
    color: '#fff',
    fontFamily: MONO,
    fontSize: 10,
    fontWeight: '700',
  },
  badgeBlock: {
    padding: 10,
    borderRadius: 12,
    alignItems: 'center',
    marginTop: 10,
  },
  themeToggle: {
    alignSelf: 'center',
    padding: 8,
    marginTop: 6,
  },
  avatar: {
    width: 44,
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    color: '#fff',
    fontFamily: MONO,
    fontSize: 14,
    fontWeight: '700',
  },
});
