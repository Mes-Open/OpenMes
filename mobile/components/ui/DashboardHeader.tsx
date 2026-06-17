// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import { FontAwesome } from '@expo/vector-icons';
import { DrawerActions, useNavigation } from '@react-navigation/native';
import { Pressable, StyleSheet, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { colors } from '@openmes/ui';

import { BrandLogo } from '@/components/ui/Brand';
import { Mono } from '@/components/ui/Mono';

interface Props {
  /** Role label for the right-side pill (e.g. "Admin"). */
  role?: string | null;
  /**
   * When true, renders a small 28px "more" affordance after the role pill so
   * users can still reach the drawer from the dashboard. The trick: it sits
   * AFTER the role pill, not before the logo, so the brand logo stays at the
   * standard 18px page edge — flush-left with the title and the KPI grid.
   * Drawer is also reachable via left-edge swipe.
   */
  showMenu?: boolean;
}

export function DashboardHeader({ role, showMenu = true }: Props) {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();

  return (
    <View style={[styles.bar, { paddingTop: insets.top + 10 }]}>
      <View style={{ flex: 1 }}>
        <BrandLogo size={16} color={colors.ink} />
      </View>

      {role ? (
        <View style={styles.rolePill}>
          <FontAwesome name="shield" size={11} color={colors.accent} />
          <Mono size={10} color={colors.muted} weight="600" letterSpacing={0.8}>
            {role.toUpperCase()}
          </Mono>
        </View>
      ) : null}

      {showMenu ? (
        <Pressable
          accessibilityRole="button"
          onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
          hitSlop={10}
          style={({ pressed }) => [styles.menuBtn, { opacity: pressed ? 0.5 : 1 }]}>
          <FontAwesome name="ellipsis-v" size={16} color={colors.muted} />
        </Pressable>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 18,
    paddingBottom: 14,
    gap: 10,
    backgroundColor: colors.bg,
  },
  rolePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  menuBtn: {
    width: 28,
    height: 28,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
