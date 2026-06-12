// Light-only v1: Colors[scheme] switching dropped — variant="dark" is accepted but renders light.
import { FontAwesome } from '@expo/vector-icons';
import { DrawerActions, useNavigation } from '@react-navigation/native';
import { useEffect, useRef } from 'react';
import { Animated, Easing, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';

import { TabletStatusStripLive } from '@/components/tablet/TabletStatusStripLive';
import { useDeviceClass } from '@/hooks/useDeviceClass';

interface Props {
  title?: string;
  subtitle?: string;
  /** When set, the subtitle renders in this color with a leading filled dot —
   * used by triage/alert screens to signal urgency in the eyebrow line. */
  subtitleColor?: string;
  rightAction?: { icon: React.ComponentProps<typeof FontAwesome>['name']; onPress: () => void };
  rightSlot?: React.ReactNode;
  back?: boolean;
  onBack?: () => void;
  variant?: 'menu' | 'back' | 'dark';
}

/**
 * Screen chrome bar — Geist White §11 large-title idiom: mono uppercase
 * context label above a 27px semibold ink title, with back/hamburger and a
 * right slot preserved from the previous API.
 */
export function ScreenHeader({ title, subtitle, subtitleColor, rightAction, rightSlot, back, onBack, variant }: Props) {
  const navigation = useNavigation();
  const insets = useSafeAreaInsets();
  const { useTabletLayout: isTablet } = useDeviceClass();
  // Auto-translate title + subtitle — call sites pass English-as-key.
  const { t } = useTranslation();

  const showBack = variant === 'back' || back;
  // On tablet the strip above the bar already covers the notch area, so we
  // don't double up on safe-area padding.
  const barTopPadding = isTablet ? 10 : insets.top + 10;

  return (
    <View>
      {/* Operator screens that force variant="dark" keep the dark strip;
          everything else renders the light Geist White chrome. */}
      {isTablet ? <TabletStatusStripLive dark={variant === 'dark'} /> : null}
      <View style={[styles.bar, { paddingTop: barTopPadding }]}>
        {showBack ? (
          <Pressable
            accessibilityRole="button"
            onPress={onBack ?? (() => navigation.goBack())}
            hitSlop={8}
            style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
            <FontAwesome name="chevron-left" size={16} color={colors.ink} />
          </Pressable>
        ) : isTablet ? (
          // Permanent sidebar already provides nav — no hamburger needed.
          null
        ) : (
          <Pressable
            accessibilityRole="button"
            onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
            hitSlop={8}
            style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
            <FontAwesome name="bars" size={18} color={colors.ink} />
          </Pressable>
        )}
        <View style={styles.titleWrap}>
          {subtitle ? (
            <View style={styles.subtitleRow}>
              {subtitleColor ? <PulsingDot color={subtitleColor} /> : null}
              <Text
                style={[
                  styles.subtitle,
                  subtitleColor
                    ? { color: subtitleColor, fontFamily: fonts.mono.native.semibold }
                    : null,
                ]}
                numberOfLines={1}>
                {t(subtitle).toUpperCase()}
              </Text>
            </View>
          ) : null}
          {title ? (
            <Text accessibilityRole="header" style={styles.title} numberOfLines={1}>
              {t(title)}
            </Text>
          ) : null}
        </View>
        {rightSlot ? (
          rightSlot
        ) : rightAction ? (
          <Pressable
            accessibilityRole="button"
            onPress={rightAction.onPress}
            hitSlop={8}
            style={({ pressed }) => [styles.iconBtn, { opacity: pressed ? 0.6 : 1 }]}>
            <FontAwesome name={rightAction.icon} size={16} color={colors.ink} />
          </Pressable>
        ) : (
          <View style={{ width: 36 }} />
        )}
      </View>
    </View>
  );
}

// Live pulse on the urgency dot — a halo that expands & fades while the dot
// holds its color. Signals "this is current, not a static badge."
function PulsingDot({ color }: { color: string }) {
  const progress = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    const loop = Animated.loop(
      Animated.timing(progress, {
        toValue: 1,
        duration: 1400,
        easing: Easing.out(Easing.ease),
        useNativeDriver: true,
      }),
    );
    loop.start();
    return () => loop.stop();
  }, [progress]);

  const haloScale = progress.interpolate({ inputRange: [0, 1], outputRange: [1, 2.6] });
  const haloOpacity = progress.interpolate({ inputRange: [0, 1], outputRange: [0.55, 0] });

  return (
    <View style={styles.dotWrap}>
      <Animated.View
        style={[
          styles.subtitleDot,
          styles.dotHalo,
          { backgroundColor: color, opacity: haloOpacity, transform: [{ scale: haloScale }], pointerEvents: 'none' },
        ]}
      />
      <View style={[styles.subtitleDot, { backgroundColor: color }]} />
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 14,
    paddingBottom: 12,
    gap: 12,
    backgroundColor: colors.bg,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.line,
  },
  iconBtn: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  titleWrap: { flex: 1, gap: 2 },
  title: {
    fontSize: 27,
    fontFamily: fonts.sans.native.semibold,
    letterSpacing: -0.675,
    color: colors.ink,
  },
  subtitle: {
    fontSize: 10,
    fontFamily: fonts.mono.native.regular,
    letterSpacing: 1.2,
    textTransform: 'uppercase',
    color: colors.faint,
  },
  subtitleRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  subtitleDot: { width: 7, height: 7, borderRadius: 3.5 },
  dotWrap: { width: 7, height: 7, alignItems: 'center', justifyContent: 'center' },
  dotHalo: { position: 'absolute' },
});
