// Light-only v1: Colors[scheme] switching dropped — styling mirrors omTabBarOptions()
// from '@openmes/ui/native' (64px card bar, line2 top hairline, ink/faint tints, 9.5px labels).
import { FontAwesome } from '@expo/vector-icons';
import type { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors, fonts } from '@openmes/ui';

import { useIssues } from '@/hooks/queries/useIssues';

interface TabConfig {
  /** Route name in the (tabs) folder. */
  name: string;
  /** Display label below the icon. */
  label: string;
  /** FontAwesome icon name. */
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  /** When true, this tab is the raised accent FAB-style center button. */
  primary?: boolean;
  /** Show a red badge with this count if > 0. */
  badge?: number;
}

const TABS: Omit<TabConfig, 'badge'>[] = [
  { name: 'index', label: 'Today', icon: 'home' },
  { name: 'orders', label: 'Orders', icon: 'list-alt' },
  { name: 'scan', label: 'Scan', icon: 'qrcode', primary: true },
  { name: 'issues', label: 'Issues', icon: 'bell' },
  { name: 'profile', label: 'Profile', icon: 'user' },
];

export function BottomTabBar({ state, descriptors, navigation }: BottomTabBarProps) {
  const insets = useSafeAreaInsets();

  const openIssues = useIssues({ status: 'OPEN' });
  const alertCount = openIssues.data?.length ?? 0;

  return (
    <View style={[styles.bar, { paddingBottom: Math.max(insets.bottom, 6) }]}>
      {TABS.map((tab) => {
        // Only render tabs that exist in the route state (skip if undefined to avoid crashes).
        const route = state.routes.find((r) => r.name === tab.name);
        if (!route) return <View key={tab.name} style={styles.cell} />;
        const isFocused = state.routes[state.index]?.name === route.name;
        const badge = tab.name === 'issues' ? alertCount : 0;

        const onPress = () => {
          const event = navigation.emit({
            type: 'tabPress',
            target: route.key,
            canPreventDefault: true,
          });
          if (!isFocused && !event.defaultPrevented) {
            navigation.navigate(route.name);
          }
        };

        if (tab.primary) {
          return (
            <View key={tab.name} style={styles.cell}>
              <Pressable
                accessibilityRole="button"
                onPress={onPress}
                style={({ pressed }) => [styles.primaryBtn, { opacity: pressed ? 0.85 : 1 }]}>
                <FontAwesome name={tab.icon} size={26} color="#FFFFFF" />
              </Pressable>
            </View>
          );
        }

        const tint = isFocused ? colors.ink : colors.faint;
        return (
          <Pressable
            key={tab.name}
            accessibilityRole="button"
            accessibilityState={{ selected: isFocused }}
            onPress={onPress}
            hitSlop={6}
            style={({ pressed }) => [styles.cell, { opacity: pressed ? 0.7 : 1 }]}>
            {isFocused ? <View style={styles.activeAccent} /> : null}
            <View style={styles.iconWrap}>
              <FontAwesome name={tab.icon} size={22} color={tint} />
              {badge > 0 ? (
                <View style={styles.badge}>
                  <Text style={styles.badgeText}>{badge > 9 ? '9+' : badge}</Text>
                </View>
              ) : null}
            </View>
            <Text style={[styles.label, { color: tint }]}>{tab.label.toUpperCase()}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    minHeight: 64,
    paddingTop: 8,
    paddingHorizontal: 8,
    backgroundColor: colors.card,
    borderTopWidth: 1,
    borderTopColor: colors.line2,
  },
  cell: { flex: 1, alignItems: 'center', justifyContent: 'flex-start', minHeight: 56 },
  iconWrap: { position: 'relative', alignItems: 'center', justifyContent: 'center' },
  badge: {
    position: 'absolute',
    top: -6,
    right: -10,
    minWidth: 18,
    height: 18,
    borderRadius: 9,
    paddingHorizontal: 4,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1.5,
    borderColor: colors.card,
    backgroundColor: colors.blocked,
  },
  badgeText: { color: '#FFFFFF', fontSize: 10, fontFamily: fonts.mono.native.semibold },
  label: {
    fontFamily: fonts.sans.native.semibold,
    fontSize: 9.5,
    marginTop: 4,
    letterSpacing: 0.5,
  },
  activeAccent: {
    position: 'absolute',
    top: 0,
    width: 28,
    height: 2,
    backgroundColor: colors.accent,
    borderRadius: 1,
  },
  primaryBtn: {
    width: 56,
    height: 56,
    borderRadius: 16,
    backgroundColor: colors.accent,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: -22,
    boxShadow: '0px 6px 12px rgba(234, 90, 43, 0.35)',
    elevation: 8,
  },
});
