/**
 * Phone bottom navigation — compact companion to the web sidebar for handset
 * screens (the permanent sidebar is tablet-only). Four slots: Dashboard,
 * Alerts (live badge), Orders, and Menu (opens the slide-over drawer with the
 * full role-filtered tree). Items honor the same backend `accessible_tabs`
 * filtering as the sidebar, so a Supervisor sees only what the web allows.
 */
import { DrawerActions, useNavigation } from '@react-navigation/native';
import { usePathname, useRouter } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { HeroIcon, type HeroIconKey } from '@/components/ui/HeroIcon';
import { useAlertCounts } from '@/hooks/queries/useSystem';
import { getRole, useAuthStore } from '@/stores/authStore';

interface Item {
  key: string;
  label: string;
  icon: HeroIconKey;
  route: string;
  /** TabRegistry key — hidden when the backend's accessible_tabs excludes it. */
  tab?: string;
  match: string;
}

const ITEMS: Item[] = [
  { key: 'dashboard', label: 'Dashboard', icon: 'dashboard', route: '/admin/dashboard', tab: 'dashboard', match: '/admin/dashboard' },
  { key: 'alerts', label: 'Alerts', icon: 'bell', route: '/admin/alerts-dashboard', tab: 'alerts', match: '/admin/alerts-dashboard' },
  { key: 'orders', label: 'Orders', icon: 'clipboard', route: '/admin/work-orders', tab: 'orders', match: '/admin/work-orders' },
];

/** Supervisors land on the Supervisor Dashboard (not the tab-gated admin one). */
const SUPERVISOR_DASHBOARD: Item = {
  key: 'dashboard',
  label: 'Dashboard',
  icon: 'dashboard',
  route: '/supervisor',
  match: '/supervisor',
};

export function PhoneBottomNav() {
  const { t } = useTranslation();
  const router = useRouter();
  const navigation = useNavigation();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();
  const tabs = useAuthStore((s) => s.accessibleTabs);
  const alertsQ = useAlertCounts();
  const alertsTotal = alertsQ.data?.total ?? 0;

  const role = useAuthStore((s) => (s.user ? getRole(s.user) : null));
  const visible = ITEMS.filter((i) => !tabs || !i.tab || tabs.includes(i.tab));
  if (role === 'Supervisor' && !visible.some((i) => i.key === 'dashboard')) {
    visible.unshift(SUPERVISOR_DASHBOARD);
  }

  return (
    <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 8) }]}>
      {visible.map((item) => {
        const active = pathname.startsWith(item.match);
        const tint = active ? colors.ink : colors.faint;
        return (
          <Pressable
            key={item.key}
            onPress={() => router.push(item.route as never)}
            style={({ pressed }) => [styles.item, pressed && { opacity: 0.6 }]}>
            <View>
              <HeroIcon name={item.icon} size={22} color={tint} />
              {item.key === 'alerts' && alertsTotal > 0 ? (
                <View style={styles.dot}>
                  <Text style={styles.dotText}>{alertsTotal > 9 ? '9+' : alertsTotal}</Text>
                </View>
              ) : null}
            </View>
            <Text style={[styles.label, { color: tint }, active && styles.labelActive]}>{t(item.label)}</Text>
          </Pressable>
        );
      })}
      <Pressable
        onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
        style={({ pressed }) => [styles.item, pressed && { opacity: 0.6 }]}>
        <HeroIcon name="menu" size={22} color={colors.faint} />
        <Text style={[styles.label, { color: colors.faint }]}>{t('Menu')}</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    backgroundColor: colors.card,
    borderTopWidth: 1,
    borderTopColor: colors.line,
    paddingTop: 8,
  },
  item: { flex: 1, alignItems: 'center', gap: 3 },
  label: { fontSize: 10.5, fontFamily: fonts.sans.native.medium },
  labelActive: { color: colors.ink },
  dot: {
    position: 'absolute',
    top: -4,
    right: -8,
    minWidth: 15,
    height: 15,
    borderRadius: 8,
    backgroundColor: colors.blocked,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: 3,
  },
  dotText: { fontSize: 8.5, color: '#FFFFFF', fontFamily: fonts.sans.native.semibold },
});
