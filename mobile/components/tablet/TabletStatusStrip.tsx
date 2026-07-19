import { format } from 'date-fns';
import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { AppState, Pressable, StyleSheet, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Mono } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';

interface Props {
  dark?: boolean;
  /** Override the MQTT live state. When omitted, the strip just shows a neutral indicator. */
  mqttLive?: boolean | null;
}

function shiftFor(hour: number): 'A' | 'B' | 'C' {
  if (hour >= 6 && hour < 14) return 'A';
  if (hour >= 14 && hour < 22) return 'B';
  return 'C';
}

/**
 * Thin status strip rendered above every tablet screen — time / day / shift on
 * the left, MQTT live indicator on the right. The OS status bar already shows
 * wifi/battery, so we don't duplicate them here with fake icons. Tapping the
 * MQTT pill opens the connectivity admin screen.
 */
export function TabletStatusStrip({ dark, mqttLive }: Props) {
  const { t } = useTranslation();
  const palette = dark ? Colors.dark : Colors.light;
  const router = useRouter();
  const [now, setNow] = useState(() => new Date());

  // Tick once per second, aligned to the second boundary so the visible
  // digits flip at the same moment the OS clock does (no mid-second drift).
  // Also pause when the app is backgrounded — there's nothing for a clock
  // to do off-screen, and Android keeps timers alive otherwise.
  useEffect(() => {
    let timeoutId: ReturnType<typeof setTimeout> | null = null;
    let intervalId: ReturnType<typeof setInterval> | null = null;

    const start = () => {
      stop();
      setNow(new Date());
      const msUntilNextSecond = 1000 - (Date.now() % 1000);
      timeoutId = setTimeout(() => {
        setNow(new Date());
        intervalId = setInterval(() => setNow(new Date()), 1000);
      }, msUntilNextSecond);
    };
    const stop = () => {
      if (timeoutId != null) clearTimeout(timeoutId);
      if (intervalId != null) clearInterval(intervalId);
      timeoutId = null;
      intervalId = null;
    };

    start();
    const sub = AppState.addEventListener('change', (state) => {
      if (state === 'active') start();
      else stop();
    });

    return () => {
      stop();
      sub.remove();
    };
  }, []);

  const time = format(now, 'HH:mm:ss');
  const day = format(now, 'EEE dd MMM').toUpperCase();
  const shift = shiftFor(now.getHours());

  const dotColor =
    mqttLive == null
      ? palette.textFaint
      : mqttLive
      ? palette.success
      : palette.danger;

  return (
    <View
      style={[
        styles.strip,
        { borderBottomColor: palette.border, backgroundColor: palette.background },
      ]}>
      <View style={styles.left}>
        <Mono size={11} color={palette.text} weight="600" letterSpacing={0.3}>
          {time}
        </Mono>
        <Mono size={11} color={palette.textFaint} letterSpacing={0.4}>· {day}</Mono>
        <Mono size={11} color={palette.textFaint} letterSpacing={0.4}>· {shift}-{t('Shift').toUpperCase()}</Mono>
      </View>
      <View style={styles.right}>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={t('MQTT connectivity')}
          onPress={() => router.push('/(drawer)/connectivity' as never)}
          hitSlop={8}
          style={({ pressed }) => [styles.mqttBlock, { opacity: pressed ? 0.6 : 1 }]}>
          <Mono size={10.5} color={palette.textMuted} letterSpacing={0.6}>MQTT</Mono>
          <View style={[styles.mqttDot, { backgroundColor: dotColor }]} />
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  strip: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 24,
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  left: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  right: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  mqttBlock: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  mqttDot: { width: 8, height: 8, borderRadius: 4 },
});
