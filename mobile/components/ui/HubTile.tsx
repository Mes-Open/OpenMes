import { FontAwesome } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  /** FontAwesome icon name. */
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  /** Tile label. */
  label: string;
  /** Mono uppercase subline (e.g. "VERSIONED RECIPES"). */
  sub?: string;
  /** Count badge in the top-right (e.g. "14", "8.2K"). */
  count?: string | number;
  /**
   * When true the icon tile is filled with the brand amber accent. Use
   * sparingly — typically the "primary" tile in each hub.
   */
  accent?: boolean;
  /** Render in dark mode (used on the Connectivity hub). */
  dark?: boolean;
  onPress?: () => void;
}

/** Square-ish launcher tile: icon (top-left) + label + sub, optional count. */
export function HubTile({ icon, label, sub, count, accent, dark, onPress }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  // The `dark` flag forces the dark surface even when the global scheme is
  // light (so the Connectivity hub keeps its in-brand dark look).
  const force = dark ?? false;
  const bg = force ? '#16161a' : palette.surface;
  const border = force ? '#26262d' : palette.border;
  const text = force ? '#eaeaea' : palette.text;
  const subText = force ? '#6a6a72' : palette.textFaint;
  const countBg = force ? '#0e0e10' : palette.surfaceAlt;
  const countText = force ? '#9a9aa2' : palette.textMuted;
  const iconBg = accent ? BRAND.amber : force ? '#1f1f24' : palette.surfaceAlt;
  const iconColor = accent ? '#1a1208' : text;

  const inner = (
    <View style={[styles.tile, { backgroundColor: bg, borderColor: border }]}>
      <View style={[styles.iconWrap, { backgroundColor: iconBg }]}>
        <FontAwesome name={icon} size={18} color={iconColor} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={[styles.label, { color: text }]} numberOfLines={2}>
          {label}
        </Text>
        {sub ? (
          <Mono size={10} color={subText} letterSpacing={0.4} style={{ marginTop: 4 }}>
            {sub.toUpperCase()}
          </Mono>
        ) : null}
      </View>
      {count != null ? (
        <View style={[styles.countPill, { backgroundColor: countBg }]}>
          <Mono size={11} color={countText} weight="600">
            {String(count)}
          </Mono>
        </View>
      ) : null}
    </View>
  );

  if (onPress) {
    return (
      <Pressable
        onPress={onPress}
        style={({ pressed }) => [{ flex: 1, opacity: pressed ? 0.85 : 1 }]}>
        {inner}
      </Pressable>
    );
  }
  return <View style={{ flex: 1 }}>{inner}</View>;
}

interface GridProps {
  children: React.ReactNode;
}

/** Responsive 2-col grid wrapper. Each child should be a HubTile. */
export function HubGrid({ children }: GridProps) {
  // Render rows of two tiles to keep equal heights without resorting to a
  // measured grid. Pad the last row if odd-numbered for clean alignment.
  const items = (Array.isArray(children) ? children : [children]).flat();
  const rows: React.ReactNode[][] = [];
  for (let i = 0; i < items.length; i += 2) {
    rows.push(items.slice(i, i + 2));
  }
  return (
    <View style={{ gap: 10 }}>
      {rows.map((row, i) => (
        <View key={i} style={styles.row}>
          {row[0]}
          {row[1] ?? <View style={{ flex: 1 }} />}
        </View>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', gap: 10 },
  tile: {
    minHeight: 132,
    padding: 16,
    borderRadius: 14,
    borderWidth: 1,
    gap: 12,
    position: 'relative',
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  label: { fontSize: 14, fontWeight: '600', lineHeight: 18 },
  countPill: {
    position: 'absolute',
    top: 12,
    right: 12,
    paddingVertical: 2,
    paddingHorizontal: 8,
    borderRadius: 999,
  },
});
