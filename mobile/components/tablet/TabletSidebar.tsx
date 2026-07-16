import { type DrawerContentComponentProps } from '@react-navigation/drawer';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { usePathname, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { logout } from '@/api/auth';
import { BrandLogo } from '@/components/ui/Brand';
import { HeroIcon, type HeroIconKey } from '@/components/ui/HeroIcon';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import { roleColor } from '@/components/ui/RoleBadge';
import {
  activeMatch,
  containsActive,
  filterByTabs,
  navForRole,
  searchHits,
  toMatch,
  type NavNode,
} from '@/components/tablet/navItems';
import { colors as tokens, fonts, currentTheme } from '@openmes/ui';

import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAlertCounts, useUpdateCheck } from '@/hooks/queries/useSystem';
import { getRole, useAuthStore } from '@/stores/authStore';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { useSettingsStore, type ThemePreference } from '@/stores/settingsStore';

/**
 * Tablet sidebar — a 1:1 port of the web admin sidebar (backend/.../AppLayout.jsx):
 * a search box, the mixed-case links (Dashboard/Alerts/Schedule), a separator, then
 * UPPERCASE-mono collapsible groups whose children indent under a left rule. Same
 * Heroicon-style glyphs (via @expo/vector-icons), same labels/order, dark-pill
 * active state, and a Dark Mode / Settings / user / Collapse footer. Collapsible to
 * a 96px icon rail (persisted in settings).
 */
export function TabletSidebar(props: DrawerContentComponentProps) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const insets = useSafeAreaInsets();
  const router = useRouter();
  const pathname = usePathname();
  const qc = useQueryClient();
  const { t } = useTranslation();

  const user = useAuthStore((s) => s.user);
  const role = getRole(user);
  const accessibleTabs = useAuthStore((s) => s.accessibleTabs);
  const clear = useAuthStore((s) => s.clear);

  // On phones this same component renders inside the 296px slide-over drawer:
  // always expanded (no icon rail) and navigation closes the drawer.
  const { useTabletLayout: isTablet } = useDeviceClass();
  const collapsed = useSettingsStore((s) => s.sidebarCollapsed) && isTablet;
  const toggleSidebar = useSettingsStore((s) => s.toggleSidebar);

  const setTheme = useSettingsStore((s) => s.setTheme);
  // Simple light<->dark flip like the web sidebar toggle (AppLayout).
  const isDark = currentTheme() === 'dark';
  const cycleTheme = () => setTheme(isDark ? 'light' : 'dark');

  const alertCountsQ = useAlertCounts();
  const updateQ = useUpdateCheck();
  const appVersion = updateQ.data?.current_version ? `v${updateQ.data.current_version.replace(/^v/, '')}` : null;
  const alertsTotal = alertCountsQ.data?.total ?? 0;

  const logoutMutation = useMutation({
    mutationFn: () => logout().catch(() => undefined),
    onSettled: () => {
      qc.clear();
      clear();
    },
  });

  // Filter the shared admin tree by the tabs the backend grants this role —
  // identical mechanism to the web sidebar (AppLayout's showTab).
  const nodes = useMemo(() => filterByTabs(navForRole(role), accessibleTabs), [role, accessibleTabs]);
  const am = activeMatch(pathname, nodes);

  const [query, setQuery] = useState('');
  const q = query.trim().toLowerCase();
  const hits = useMemo(() => (q ? searchHits(nodes).filter((h) => t(h.label).toLowerCase().includes(q)) : []), [q, nodes, t]);

  const [open, setOpen] = useState<Record<string, boolean>>({});
  const isOpen = (node: NavNode) => open[node.key] ?? containsActive(node, am);
  const toggleOpen = (key: string, fallback: boolean) =>
    setOpen((o) => ({ ...o, [key]: !(o[key] ?? fallback) }));

  const activeBg = palette.text;
  const activeFg = palette.surface;
  const go = (route: string) => {
    router.push(route as never);
    if (!isTablet) props.navigation.closeDrawer();
  };

  const initials = (() => {
    const name = user?.name ?? user?.username ?? '';
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return (name.slice(0, 2) || '?').toUpperCase();
  })();

  // ── Collapsed rail item ─────────────────────────────────────────────────────
  const renderRail = (node: NavNode) => {
    const active = node.children
      ? containsActive(node, am)
      : (node.match ?? toMatch(node.route ?? '')) === am;
    const badge = node.alert && alertsTotal > 0 ? alertsTotal : null;
    const onPress = () => {
      if (node.children) {
        if (!isOpen(node)) toggleOpen(node.key, containsActive(node, am));
        toggleSidebar();
      } else if (node.route) {
        go(node.route);
      }
    };
    return (
      <Pressable
        key={node.key}
        onPress={onPress}
        style={({ pressed }) => [
          styles.itemRail,
          { backgroundColor: active ? activeBg : 'transparent', opacity: pressed ? 0.7 : 1 },
        ]}>
        <View style={{ position: 'relative' }}>
          {node.icon ? <HeroIcon name={node.icon} size={22} color={active ? activeFg : palette.textMuted} /> : null}
          {badge ? (
            <View style={styles.dot}>
              <Text style={styles.dotText}>{badge > 9 ? '9+' : badge}</Text>
            </View>
          ) : null}
        </View>
        <Mono size={10} letterSpacing={0.4} color={active ? activeFg : palette.textMuted}>
          {t(node.label).toUpperCase()}
        </Mono>
      </Pressable>
    );
  };

  // ── Expanded tree node (recursive) ──────────────────────────────────────────
  const renderNode = (node: NavNode, depth: number): React.ReactNode => {
    // Group / subgroup
    if (node.children) {
      const opened = isOpen(node);
      const within = containsActive(node, am);
      const isTop = depth === 0;
      return (
        <View key={node.key}>
          <Pressable
            onPress={() => toggleOpen(node.key, within)}
            style={({ pressed }) => [styles.row, { paddingLeft: 12, opacity: pressed ? 0.7 : 1 }]}>
            {isTop && node.icon ? (
              <HeroIcon name={node.icon} size={20} color={within ? palette.text : palette.textMuted} />
            ) : (
              <View style={[styles.childDot, { backgroundColor: within ? palette.text : palette.textMuted, opacity: 0.6 }]} />
            )}
            {isTop ? (
              <Mono size={10} upper letterSpacing={1.2} color={within ? palette.text : palette.textFaint} style={styles.flex1}>
                {t(node.label)}
              </Mono>
            ) : (
              <Text numberOfLines={1} style={[styles.childLabel, styles.flex1, { color: within ? palette.text : palette.textMuted }]}>
                {t(node.label)}
              </Text>
            )}
            <HeroIcon name={opened ? 'chevronUp' : 'chevronRight'} size={isTop ? 15 : 13} color={palette.textFaint} />
          </Pressable>
          {opened ? (
            <View style={[styles.childrenWrap, { borderLeftColor: palette.border }]}>
              {node.children.map((c) => renderNode(c, depth + 1))}
            </View>
          ) : null}
        </View>
      );
    }

    // Leaf
    const isTop = depth === 0;
    const active = (node.match ?? toMatch(node.route ?? '')) === am;
    const badge = node.alert && alertsTotal > 0 ? alertsTotal : null;
    const fg = node.disabled ? palette.textFaint : active ? activeFg : isTop ? palette.text : palette.textMuted;
    return (
      <Pressable
        key={node.key}
        disabled={node.disabled || !node.route}
        onPress={() => node.route && go(node.route)}
        style={({ pressed }) => [
          isTop ? styles.row : styles.childRow,
          {
            paddingLeft: isTop ? 12 : 8,
            backgroundColor: active ? activeBg : 'transparent',
            opacity: node.disabled ? 0.5 : pressed ? 0.7 : 1,
          },
        ]}>
        {isTop && node.icon ? (
          <View style={{ position: 'relative' }}>
            <HeroIcon name={node.icon} size={20} color={fg} />
            {badge ? (
              <View style={styles.iconDot}>
                <Text style={styles.iconDotText}>{badge > 9 ? '9+' : badge}</Text>
              </View>
            ) : null}
          </View>
        ) : (
          <View style={[styles.childDot, { backgroundColor: active ? activeFg : palette.textMuted, opacity: active ? 1 : 0.55 }]} />
        )}
        <Text numberOfLines={1} style={[isTop ? styles.linkLabel : styles.childLabel, { color: fg }]}>
          {t(node.label)}
        </Text>
        {badge ? (
          <View style={[styles.countPill, { backgroundColor: tokens.blockedBg }]}>
            <Text style={[styles.countText, { color: tokens.blocked }]}>{badge > 99 ? '99+' : badge}</Text>
          </View>
        ) : null}
        <View style={styles.flex1} />
      </Pressable>
    );
  };

  const renderHit = (hit: { key: string; label: string; route: string; trail: string[] }) => (
    <Pressable
      key={`${hit.trail.join('/')}>${hit.key}`}
      onPress={() => {
        setQuery('');
        go(hit.route);
      }}
      style={({ pressed }) => [styles.row, { paddingLeft: 12, opacity: pressed ? 0.7 : 1 }]}>
      <View style={[styles.childDot, { backgroundColor: palette.textMuted, opacity: 0.55 }]} />
      <View style={styles.flex1}>
        <Text numberOfLines={1} style={[styles.linkLabel, { color: palette.text }]}>
          {t(hit.label)}
        </Text>
        {hit.trail.length ? (
          <Mono size={9} upper letterSpacing={0.6} color={palette.textFaint}>
            {hit.trail.map((s) => t(s)).join(' / ')}
          </Mono>
        ) : null}
      </View>
    </Pressable>
  );

  const footRow = (icon: HeroIconKey, label: string, onPress: () => void, key?: string) => (
    <Pressable
      key={key}
      onPress={onPress}
      style={({ pressed }) => [collapsed ? styles.footRail : styles.footRow, { opacity: pressed ? 0.6 : 1 }]}>
      <HeroIcon name={icon} size={collapsed ? 18 : 18} color={palette.textMuted} />
      {collapsed ? null : <Text style={[styles.footLabel, { color: palette.textMuted }]}>{t(label)}</Text>}
    </Pressable>
  );

  return (
    <View style={[styles.container, { backgroundColor: palette.surface, paddingTop: insets.top + 16 }]}>
      <ScrollView style={{ flex: 1 }} contentContainerStyle={styles.scrollInner} showsVerticalScrollIndicator={false}>
        {/* Logo */}
        <View style={[styles.logoWrap, collapsed ? styles.centerX : styles.logoExpanded, !collapsed && styles.logoRow]}>
          <BrandLogo size={collapsed ? 20 : 24} />
          {!collapsed && appVersion ? (
            <View style={[styles.versionChip, { borderColor: palette.border }]}>
              <Mono size={9} color={palette.textFaint}>{appVersion}</Mono>
            </View>
          ) : null}
        </View>

        {/* Search (expanded only) */}
        {collapsed ? null : (
          <View style={styles.searchWrap}>
            <SearchBar placeholder="Search menu…" value={query} onChangeText={setQuery} />
          </View>
        )}

        {/* Nav */}
        {collapsed ? (
          <View style={{ gap: 6 }}>{nodes.map(renderRail)}</View>
        ) : q ? (
          <View>
            {hits.length ? (
              hits.map(renderHit)
            ) : (
              <Text style={[styles.noResults, { color: palette.textFaint }]}>{t('No results')}</Text>
            )}
          </View>
        ) : (
          <View>
            {nodes.filter((n) => !n.children).map((n) => renderNode(n, 0))}
            <View style={[styles.separator, { backgroundColor: palette.border }]} />
            {nodes.filter((n) => n.children).map((n) => renderNode(n, 0))}
          </View>
        )}
      </ScrollView>

      {/* Footer — Dark Mode · Settings · user · Collapse (parity with web) */}
      <View style={[styles.footer, { borderTopColor: palette.border, paddingBottom: insets.bottom + 10 }]}>
        {footRow(isDark ? 'sun' : 'moon', isDark ? 'Light Mode' : 'Dark Mode', cycleTheme, 'theme')}
        {footRow('settings', 'Settings', () => router.push('/(drawer)/settings' as never), 'settings')}

        {/* User + logout */}
        <Pressable
          onPress={() => router.push('/(drawer)/settings' as never)}
          onLongPress={() => {
            Alert.alert(t('Sign out'), t('Sign out {{name}}?', { name: user?.username ?? '' }), [
              { text: t('Cancel'), style: 'cancel' },
              { text: t('Sign out'), style: 'destructive', onPress: () => logoutMutation.mutate() },
            ]);
          }}
          style={collapsed ? styles.badgeBlock : styles.userRow}>
          <View style={[styles.avatar, { backgroundColor: roleColor(role) }]}>
            <Text style={styles.avatarText}>{initials}</Text>
          </View>
          {collapsed ? null : (
            <>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text numberOfLines={1} style={[styles.userName, { color: palette.text }]}>
                  {user?.name ?? user?.username ?? t('User')}
                </Text>
                <Mono size={9.5} color={palette.textFaint} letterSpacing={0.3}>
                  {(role ?? 'OPERATOR').toUpperCase()}
                </Mono>
              </View>
              <Pressable
                hitSlop={8}
                accessibilityLabel={t('Sign out')}
                onPress={() => {
                  Alert.alert(t('Sign out'), t('Sign out {{name}}?', { name: user?.username ?? '' }), [
                    { text: t('Cancel'), style: 'cancel' },
                    { text: t('Sign out'), style: 'destructive', onPress: () => logoutMutation.mutate() },
                  ]);
                }}>
                <HeroIcon name="logout" size={16} color={palette.textFaint} />
              </Pressable>
            </>
          )}
        </Pressable>

        {!isTablet ? null : (
        <View style={[styles.collapseDivider, { borderTopColor: palette.border }]}>
          <Pressable
            onPress={toggleSidebar}
            accessibilityRole="button"
            accessibilityLabel={collapsed ? t('Expand sidebar') : t('Collapse sidebar')}
            style={({ pressed }) => [collapsed ? styles.footRail : styles.footRow, { justifyContent: 'center', opacity: pressed ? 0.6 : 1 }]}>
            <View style={collapsed ? { transform: [{ rotate: '180deg' }] } : undefined}>
              <HeroIcon name="chevronDouble" size={18} color={palette.textFaint} />
            </View>
            {collapsed ? null : <Text style={[styles.footLabel, { color: palette.textFaint }]}>{t('Collapse')}</Text>}
          </Pressable>
        </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, paddingHorizontal: 8 },
  scrollInner: { paddingBottom: 12 },
  centerX: { alignItems: 'center' },
  flex1: { flex: 1 },
  logoWrap: { marginBottom: 12 },
  logoRow: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  versionChip: { borderWidth: 1, borderRadius: 4, paddingHorizontal: 5, paddingVertical: 1 },
  logoExpanded: { paddingHorizontal: 8 },
  searchWrap: { marginBottom: 10, paddingHorizontal: 2 },

  // Top-level row (link or group header).
  row: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 10, paddingRight: 10, borderRadius: 8 },
  linkLabel: { fontSize: 13, fontFamily: fonts.sans.native.medium, letterSpacing: -0.1 },

  // Children under a group (indented, left rule).
  childrenWrap: { marginLeft: 20, marginTop: 2, paddingLeft: 12, borderLeftWidth: StyleSheet.hairlineWidth, gap: 1 },
  childRow: { flexDirection: 'row', alignItems: 'center', gap: 9, paddingVertical: 7, paddingRight: 8, borderRadius: 8 },
  childLabel: { fontSize: 13, fontFamily: fonts.sans.native.regular, letterSpacing: -0.1 },
  childDot: { width: 5, height: 5, borderRadius: 3 },

  separator: { height: StyleSheet.hairlineWidth, marginHorizontal: 12, marginVertical: 8 },
  noResults: { fontSize: 13, paddingHorizontal: 14, paddingVertical: 10 },

  // Collapsed rail item.
  itemRail: { paddingVertical: 11, borderRadius: 12, alignItems: 'center', gap: 6 },

  dot: {
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
  dotText: { color: '#fff', fontFamily: MONO, fontSize: 10, fontWeight: '700' },
  // Solid-red "9+" dot on the bell icon (parity with the web NavLink icon badge).
  iconDot: {
    position: 'absolute',
    top: -6,
    right: -7,
    minWidth: 15,
    height: 15,
    paddingHorizontal: 3,
    borderRadius: 7.5,
    backgroundColor: tokens.blocked,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconDotText: { color: '#fff', fontFamily: MONO, fontSize: 9, fontWeight: '700' },
  // Light-red count pill after the label (bg-om-blocked-bg / text-om-blocked).
  countPill: { minWidth: 20, height: 18, paddingHorizontal: 6, borderRadius: 9, alignItems: 'center', justifyContent: 'center' },
  countText: { fontFamily: MONO, fontSize: 10, fontWeight: '700' },

  footer: { paddingTop: 6, borderTopWidth: StyleSheet.hairlineWidth },
  footRail: { alignSelf: 'center', padding: 8, alignItems: 'center' },
  footRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 9, paddingHorizontal: 12, borderRadius: 8 },
  footLabel: { fontSize: 13, fontFamily: fonts.sans.native.medium },
  collapseDivider: { borderTopWidth: StyleSheet.hairlineWidth, marginTop: 4, paddingTop: 4 },

  badgeBlock: { padding: 10, borderRadius: 12, alignItems: 'center', marginTop: 6 },
  userRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8, paddingHorizontal: 12 },
  userName: { fontSize: 13, fontFamily: fonts.sans.native.semibold, letterSpacing: -0.1 },
  avatar: { width: 32, height: 32, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  avatarText: { color: '#fff', fontFamily: MONO, fontSize: 12, fontWeight: '700' },
});
