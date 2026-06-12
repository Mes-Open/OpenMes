import { FontAwesome } from '@expo/vector-icons';
import type { BottomTabBarProps } from '@react-navigation/bottom-tabs';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useIssues } from '@/hooks/queries/useIssues';

interface TabConfig {
  /** Route name in the (tabs) folder. */
  name: string;
  /** Display label below the icon. */
  label: string;
  /** FontAwesome icon name. */
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  /** When true, this tab is the raised amber FAB-style center button. */
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const insets = useSafeAreaInsets();

  const openIssues = useIssues({ status: 'OPEN' });
  const alertCount = openIssues.data?.length ?? 0;

  return (
    <View
      style={[
        styles.bar,
        {
          backgroundColor: palette.surface,
          borderTopColor: palette.border,
          paddingBottom: Math.max(insets.bottom, 6),
        },
      ]}>
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
                onPress={onPress}
                style={({ pressed }) => [styles.primaryBtn, { opacity: pressed ? 0.85 : 1 }]}>
                <FontAwesome name={tab.icon} size={26} color="#1a1208" />
              </Pressable>
            </View>
          );
        }

        const iconTint = isFocused ? palette.text : palette.textFaint;
        const labelTint = isFocused ? palette.text : palette.textFaint;
        return (
          <Pressable
            key={tab.name}
            onPress={onPress}
            hitSlop={6}
            style={({ pressed }) => [styles.cell, { opacity: pressed ? 0.7 : 1 }]}>
            {isFocused ? <View style={styles.activeAccent} /> : null}
            <View style={styles.iconWrap}>
              <FontAwesome name={tab.icon} size={22} color={iconTint} />
              {badge > 0 ? (
                <View style={[styles.badge, { backgroundColor: palette.danger }]}>
                  <Text style={[styles.badgeText, { fontFamily: MONO }]}>
                    {badge > 9 ? '9+' : badge}
                  </Text>
                </View>
              ) : null}
            </View>
            <Text
              style={[
                styles.label,
                {
                  color: labelTint,
                  fontWeight: isFocused ? '700' : '600',
                },
              ]}>
              {tab.label.toUpperCase()}
            </Text>
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
    paddingTop: 8,
    paddingHorizontal: 8,
    borderTopWidth: StyleSheet.hairlineWidth,
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
    borderColor: '#ffffff',
  },
  badgeText: { color: '#fff', fontSize: 10, fontWeight: '700' },
  label: { fontFamily: MONO, fontSize: 9.5, marginTop: 4, letterSpacing: 0.5 },
  activeAccent: {
    position: 'absolute',
    top: 0,
    width: 28,
    height: 2,
    backgroundColor: BRAND.amber,
    borderRadius: 1,
  },
  primaryBtn: {
    width: 56,
    height: 56,
    borderRadius: 16,
    backgroundColor: BRAND.amber,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: -22,
    boxShadow: '0px 6px 12px rgba(245, 144, 33, 0.4)',
    elevation: 8,
  },
});
