import { FontAwesome } from '@expo/vector-icons';
import { DrawerActions, useNavigation } from '@react-navigation/native';
import { useEffect, useRef } from 'react';
import { Animated, Easing, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { TabletStatusStripLive } from '@/components/tablet/TabletStatusStripLive';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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

export function ScreenHeader({ title, subtitle, subtitleColor, rightAction, rightSlot, back, onBack, variant }: Props) {
  const scheme = useColorScheme();
  const palette = variant === 'dark' ? Colors.dark : Colors[scheme];
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
      {/* Status strip follows the active scheme so dark-mode toggles the
          whole top bar, not just the body. Operator screens that force
          variant="dark" stay dark regardless of system scheme. */}
      {isTablet ? (
        <TabletStatusStripLive dark={variant === 'dark' || scheme === 'dark'} />
      ) : null}
    <View
      style={[
        styles.bar,
        {
          paddingTop: barTopPadding,
          backgroundColor: palette.background,
          borderBottomColor: palette.border,
        },
      ]}>
      {showBack ? (
        <Pressable
          onPress={onBack ?? (() => navigation.goBack())}
          hitSlop={8}
          style={({ pressed }) => [
            styles.iconBtn,
            { borderColor: palette.border, opacity: pressed ? 0.6 : 1 },
          ]}>
          <FontAwesome name="chevron-left" size={16} color={palette.text} />
        </Pressable>
      ) : isTablet ? (
        // Permanent sidebar already provides nav — no hamburger needed.
        null
      ) : (
        <Pressable
          onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
          hitSlop={8}
          style={({ pressed }) => [
            styles.iconBtn,
            { borderColor: palette.border, opacity: pressed ? 0.6 : 1 },
          ]}>
          <FontAwesome name="bars" size={18} color={palette.text} />
        </Pressable>
      )}
      <View style={styles.titleWrap}>
        {subtitle ? (
          <View style={styles.subtitleRow}>
            {subtitleColor ? <PulsingDot color={subtitleColor} /> : null}
            <Text
              style={[
                styles.subtitle,
                {
                  color: subtitleColor ?? palette.textMuted,
                  fontWeight: subtitleColor ? '700' : '400',
                },
              ]}
              numberOfLines={1}>
              {t(subtitle).toUpperCase()}
            </Text>
          </View>
        ) : null}
        {title ? (
          <Text style={[styles.title, { color: palette.text }]} numberOfLines={1}>
            {t(title)}
          </Text>
        ) : null}
      </View>
      {rightSlot ? (
        rightSlot
      ) : rightAction ? (
        <Pressable
          onPress={rightAction.onPress}
          hitSlop={8}
          style={({ pressed }) => [
            styles.iconBtn,
            { borderColor: palette.border, opacity: pressed ? 0.6 : 1 },
          ]}>
          <FontAwesome name={rightAction.icon} size={16} color={palette.text} />
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
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  iconBtn: {
    width: 36,
    height: 36,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: 10,
    borderWidth: 1,
  },
  titleWrap: { flex: 1, gap: 2 },
  title: { fontSize: 18, fontWeight: '700', letterSpacing: -0.2 },
  subtitle: { fontSize: 11, fontFamily: MONO, letterSpacing: 0.6 },
  subtitleRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  subtitleDot: { width: 7, height: 7, borderRadius: 3.5 },
  dotWrap: { width: 7, height: 7, alignItems: 'center', justifyContent: 'center' },
  dotHalo: { position: 'absolute' },
});
