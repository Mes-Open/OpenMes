import { FontAwesome } from '@expo/vector-icons';
import {
  DrawerContentScrollView,
  type DrawerContentComponentProps,
} from '@react-navigation/drawer';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { usePathname, useRouter } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { Alert, Pressable, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { logout } from '@/api/auth';
import { BrandLogo } from '@/components/ui/Brand';
import { Mono } from '@/components/ui/Mono';
import { RoleBadge, roleColor } from '@/components/ui/RoleBadge';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAlertCounts } from '@/hooks/queries/useSystem';
import { getRole, isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import { useSettingsStore } from '@/stores/settingsStore';

interface MenuItem {
  key: string;
  label: string;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  route?: string;
  /** Match against the current pathname to highlight as active. */
  match?: string;
  adminOnly?: boolean;
  supervisorOrAdmin?: boolean;
}

interface MenuSection {
  title: string;
  items: MenuItem[];
}

// Drawer is grouped by role: every section is scoped to what a given role
// can do. Higher-privilege roles also see the lower-privilege sections
// (operators see only OPERATOR; supervisors see OPERATOR + SUPERVISOR;
// admins see all three). The role hub at the top of each section is the
// landing route the role lands on after login.

const OPERATOR_SECTION: MenuSection = {
  title: 'Operator',
  items: [
    { key: 'operator-hub', label: 'Hub', icon: 'th-large', route: '/operator', match: '/operator' },
    { key: 'today', label: 'Today', icon: 'home', route: '/(drawer)/(tabs)', match: '/(tabs)' },
    { key: 'orders', label: 'Orders', icon: 'list-alt', route: '/(drawer)/(tabs)/orders', match: 'orders' },
    { key: 'scan', label: 'Scan', icon: 'qrcode', route: '/(drawer)/(tabs)/scan', match: 'scan' },
    { key: 'alerts', label: 'Alerts', icon: 'bell', route: '/(drawer)/(tabs)/issues', match: 'issues' },
    { key: 'downtime', label: 'Downtime', icon: 'ban', route: '/(drawer)/production/downtime', match: 'downtime' },
    { key: 'packaging', label: 'Packaging', icon: 'qrcode', route: '/(drawer)/pakowanie/' as string, match: 'pakowanie' },
  ],
};

const SUPERVISOR_SECTION: MenuSection = {
  title: 'Supervisor',
  items: [
    { key: 'supervisor-hub', label: 'Hub', icon: 'th-large', route: '/supervisor', match: '/supervisor' },
    { key: 'schedule', label: 'Schedule', icon: 'calendar', route: '/(drawer)/schedule', match: 'schedule', supervisorOrAdmin: true },
    { key: 'employee-schedule', label: 'Employees', icon: 'users', route: '/(drawer)/admin/employee-schedule', match: '/admin/employee-schedule', supervisorOrAdmin: true },
    { key: 'wall', label: 'Plant wall', icon: 'desktop', route: '/(drawer)/admin/wall', match: 'wall', supervisorOrAdmin: true },
    { key: 'analytics', label: 'Analytics', icon: 'bar-chart', route: '/supervisor/analytics/overview', match: 'analytics', supervisorOrAdmin: true },
    { key: 'reports', label: 'Reports', icon: 'file-text-o', route: '/(drawer)/admin/reports', match: 'reports', supervisorOrAdmin: true },
    { key: 'maintenance', label: 'Maintenance', icon: 'wrench', route: '/(drawer)/maintenance/' as string, match: 'maintenance', supervisorOrAdmin: true },
  ],
};

const ADMIN_SECTION: MenuSection = {
  title: 'Admin',
  items: [
    { key: 'admin-hub', label: 'Hub', icon: 'th-large', route: '/admin', match: '/admin', adminOnly: true },
    { key: 'users', label: 'Users', icon: 'users', route: '/(drawer)/admin/users', match: 'users', adminOnly: true },
    { key: 'structure', label: 'Plant structure', icon: 'sitemap', route: '/(drawer)/structure', match: 'structure', adminOnly: true },
    { key: 'hr', label: 'Workers & crews', icon: 'users', route: '/(drawer)/hr/' as string, match: '/hr', adminOnly: true },
    { key: 'production', label: 'Production catalog', icon: 'flask', route: '/(drawer)/production/' as string, match: 'production', adminOnly: true },
    { key: 'connectivity', label: 'Connectivity', icon: 'wifi', route: '/(drawer)/connectivity/' as string, match: 'connectivity', adminOnly: true },
    { key: 'modules', label: 'Modules', icon: 'cube', route: '/(drawer)/admin/modules', match: 'modules', adminOnly: true },
    { key: 'settings', label: 'System settings', icon: 'cog', route: '/(drawer)/admin/system-settings', match: 'system-settings', adminOnly: true },
    { key: 'audit', label: 'Audit logs', icon: 'history', route: '/(drawer)/admin/audit-logs', match: 'audit-logs', adminOnly: true },
  ],
};

function sectionsForRole(role: 'Admin' | 'Supervisor' | 'Operator' | null): MenuSection[] {
  if (role === 'Admin') return [OPERATOR_SECTION, SUPERVISOR_SECTION, ADMIN_SECTION];
  if (role === 'Supervisor') return [OPERATOR_SECTION, SUPERVISOR_SECTION];
  return [OPERATOR_SECTION];
}

export function DrawerContent(props: DrawerContentComponentProps) {
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const router = useRouter();
  const pathname = usePathname();
  const qc = useQueryClient();

  const user = useAuthStore((s) => s.user);
  const role = getRole(user);
  const activeLineId = useAuthStore((s) => s.activeLineId);
  const activeLine = user?.lines?.find((l) => l.id === activeLineId);
  const clear = useAuthStore((s) => s.clear);

  // Badge mirrors the web AlertController total — overdue/blocked WOs +
  // blocking issues + machine offline + maintenance pending.
  const alertCountsQ = useAlertCounts();
  const alertsTotal = alertCountsQ.data?.total ?? 0;

  const logoutMutation = useMutation({
    mutationFn: () => logout().catch(() => undefined),
    onSettled: () => {
      qc.clear();
      clear();
    },
  });

  const canSeeAdmin = role === 'Admin';
  const canSeeSupervisor = isSupervisorOrAdmin(user);

  const visible = (item: MenuItem) => {
    if (item.adminOnly && !canSeeAdmin) return false;
    if (item.supervisorOrAdmin && !canSeeSupervisor) return false;
    return true;
  };

  const isActive = (item: MenuItem) => {
    if (!item.match) return false;
    return pathname.includes(item.match);
  };

  const onSelect = (item: MenuItem) => {
    if (item.route) router.push(item.route as never);
    props.navigation.closeDrawer();
  };

  const initials = (() => {
    const name = user?.name ?? user?.username ?? '';
    if (!name) return '?';
    const parts = name.split(/\s+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
  })();

  const lineCode = activeLine?.code ?? activeLine?.name?.slice(0, 4).toUpperCase() ?? 'L?';
  const lineName = activeLine?.name ?? '';

  const switchLine = () =>
    Alert.alert('Switch active line', 'Pick a line on the Profile tab.');

  return (
    <DrawerContentScrollView
      {...props}
      contentContainerStyle={[styles.scroll, { backgroundColor: palette.surface }]}>
      <View style={[styles.header, { borderBottomColor: palette.border }]}>
        <View style={styles.headerTopRow}>
          <BrandLogo size={18} />
          <RoleBadge role={role} />
        </View>
        <Mono size={10} color={palette.textFaint} letterSpacing={0.5} style={{ marginTop: 8 }}>
          v1.0 · CONNECTED
        </Mono>
      </View>

      {activeLine ? (
        <View style={styles.lineCardWrap}>
          <Pressable
            onPress={switchLine}
            style={({ pressed }) => [styles.lineCard, { opacity: pressed ? 0.85 : 1 }]}>
            <View style={styles.lineBadge}>
              <Text style={styles.lineBadgeText}>{lineCode}</Text>
            </View>
            <View style={{ flex: 1 }}>
              <Mono size={9.5} color="#9a9aa2" letterSpacing={0.6}>ACTIVE LINE</Mono>
              <Text style={styles.lineName} numberOfLines={1}>
                {lineName}
              </Text>
            </View>
            <Mono size={10} color={BRAND.amber} weight="700" letterSpacing={0.5}>SWITCH</Mono>
          </Pressable>
        </View>
      ) : null}

      {sectionsForRole(role).map((section, i) => {
        const items = section.items.filter(visible);
        if (items.length === 0) return null;
        return (
          <NavSection
            key={i}
            section={section}
            visible={visible}
            isActive={isActive}
            onSelect={onSelect}
            badgeFor={(item) => (item.key === 'alerts' && alertsTotal > 0 ? String(alertsTotal) : null)}
          />
        );
      })}

      <View style={[styles.footer, { borderTopColor: palette.border }]}>
        <View style={[styles.footerAvatar, { backgroundColor: roleColor(role) }]}>
          <Text style={styles.footerAvatarText}>{initials}</Text>
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.footerName, { color: palette.text }]} numberOfLines={1}>
            {user?.name ?? user?.username ?? '—'}
          </Text>
          <Mono size={9.5} color={palette.textFaint} letterSpacing={0.5}>
            {(role ?? 'Operator').toUpperCase()}
            {activeLine ? ` · ${activeLine.code ?? activeLine.name}` : ''}
          </Mono>
        </View>
        <Pressable
          onPress={() => {
            const serverUrl = useSettingsStore.getState().serverUrl;
            WebBrowser.openBrowserAsync(`${serverUrl}/docs/api`).catch(() => undefined);
          }}
          style={({ pressed }) => [styles.footerIcon, { opacity: pressed ? 0.6 : 1 }]}>
          <FontAwesome name="book" size={16} color={palette.textMuted} />
        </Pressable>
        <Pressable
          onPress={() => {
            Alert.alert('Log out', 'Are you sure?', [
              { text: 'Cancel', style: 'cancel' },
              { text: 'Log out', style: 'destructive', onPress: () => logoutMutation.mutate() },
            ]);
          }}
          style={({ pressed }) => [styles.footerIcon, { opacity: pressed ? 0.6 : 1 }]}>
          <FontAwesome name="sign-out" size={18} color={palette.textMuted} />
        </Pressable>
      </View>
    </DrawerContentScrollView>
  );
}

function NavSection({
  section,
  visible,
  isActive,
  onSelect,
  badgeFor,
}: {
  section: MenuSection;
  visible: (item: MenuItem) => boolean;
  isActive: (item: MenuItem) => boolean;
  onSelect: (item: MenuItem) => void;
  badgeFor?: (item: MenuItem) => string | null;
}) {
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const items = section.items.filter(visible);
  if (items.length === 0) return null;

  return (
    <View style={styles.section}>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.8} style={styles.sectionTitle}>
        {t(section.title).toUpperCase()}
      </Mono>
      {items.map((item) => (
        <DrawerItem
          key={item.key}
          item={item}
          active={isActive(item)}
          badge={badgeFor?.(item) ?? null}
          onPress={() => onSelect(item)}
        />
      ))}
    </View>
  );
}

function DrawerItem({
  item,
  active,
  badge,
  onPress,
}: {
  item: MenuItem;
  active: boolean;
  badge: string | null;
  onPress: () => void;
}) {
  const scheme = useColorScheme();
  const palette = Colors[scheme];
  const { t } = useTranslation();

  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.item,
        active && { backgroundColor: '#1d1d22' },
        !active && pressed && { backgroundColor: palette.surfaceAlt },
      ]}>
      <FontAwesome
        name={item.icon}
        size={15}
        color={active ? BRAND.amber : palette.textMuted}
        style={{ width: 20, textAlign: 'center' }}
      />
      <Text
        style={[
          styles.itemLabel,
          {
            color: active ? '#ffffff' : palette.text,
            fontWeight: active ? '600' : '500',
          },
        ]}>
        {t(item.label)}
      </Text>
      {item.adminOnly ? (
        <View
          style={[
            styles.tag,
            {
              backgroundColor: active ? '#3a3a44' : palette.surfaceAlt,
            },
          ]}>
          <Text
            style={{
              fontFamily: MONO,
              fontSize: 8.5,
              fontWeight: '700',
              letterSpacing: 0.5,
              color: active ? '#ffffff' : palette.textFaint,
            }}>
            ADMIN
          </Text>
        </View>
      ) : null}
      {badge ? (
        <View style={styles.badge}>
          <Text style={styles.badgeText}>{badge}</Text>
        </View>
      ) : null}
    </Pressable>
  );
}

const styles = StyleSheet.create({
  scroll: { flexGrow: 1, paddingTop: 14 },
  header: {
    paddingHorizontal: 18,
    paddingBottom: 14,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  headerTopRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
  },
  lineCardWrap: { padding: 14 },
  lineCard: {
    backgroundColor: '#1d1d22',
    borderRadius: 12,
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
  lineBadge: {
    width: 36,
    height: 36,
    borderRadius: 8,
    backgroundColor: BRAND.amber,
    alignItems: 'center',
    justifyContent: 'center',
  },
  lineBadgeText: {
    color: '#1a1208',
    fontFamily: MONO,
    fontSize: 11,
    fontWeight: '700',
  },
  lineName: { color: '#fff', fontSize: 13, fontWeight: '600', marginTop: 2 },
  section: { paddingHorizontal: 8, paddingBottom: 4 },
  sectionTitle: { paddingHorizontal: 10, paddingTop: 10, paddingBottom: 4 },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 9,
    paddingHorizontal: 10,
    borderRadius: 8,
  },
  itemLabel: { flex: 1, fontSize: 13 },
  tag: {
    paddingVertical: 1,
    paddingHorizontal: 5,
    borderRadius: 3,
  },
  badge: {
    minWidth: 18,
    paddingHorizontal: 5,
    height: 18,
    borderRadius: 9,
    backgroundColor: '#c0392b',
    alignItems: 'center',
    justifyContent: 'center',
  },
  badgeText: { color: '#fff', fontFamily: MONO, fontSize: 10, fontWeight: '700' },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    marginTop: 'auto',
    borderTopWidth: StyleSheet.hairlineWidth,
  },
  footerAvatar: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
  },
  footerAvatarText: {
    color: '#fff',
    fontFamily: MONO,
    fontSize: 12,
    fontWeight: '700',
  },
  footerName: { fontSize: 12.5, fontWeight: '600' },
  footerIcon: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
