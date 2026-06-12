// Light-only v1: Colors[scheme] + forced dark surface dropped — `dark` is accepted but ignored.
import { FontAwesome } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';

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
   * When true the icon tile is filled with the accent orange. Use
   * sparingly — typically the "primary" tile in each hub.
   */
  accent?: boolean;
  /** Render in dark mode — kept for API compatibility, ignored in light-only v1. */
  dark?: boolean;
  onPress?: () => void;
}

/** Square-ish launcher tile: icon (top-left) + label + sub, optional count. */
export function HubTile({ icon, label, sub, count, accent, onPress }: Props) {
  const inner = (
    <View style={styles.tile}>
      <View style={[styles.iconWrap, { backgroundColor: accent ? colors.accent : colors.chip }]}>
        <FontAwesome name={icon} size={18} color={accent ? '#FFFFFF' : colors.ink} />
      </View>
      <View style={{ flex: 1 }}>
        <Text style={styles.label} numberOfLines={2}>
          {label}
        </Text>
        {sub ? (
          <Mono size={10} color={colors.faint} letterSpacing={0.6} style={{ marginTop: 4 }}>
            {sub.toUpperCase()}
          </Mono>
        ) : null}
      </View>
      {count != null ? (
        <View style={styles.countPill}>
          <Mono size={11} color={colors.muted} weight="500">
            {String(count)}
          </Mono>
        </View>
      ) : null}
    </View>
  );

  if (onPress) {
    return (
      <Pressable
        accessibilityRole="button"
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
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line2,
    backgroundColor: colors.card,
    gap: 12,
    position: 'relative',
  },
  iconWrap: {
    width: 40,
    height: 40,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
  },
  label: {
    fontSize: 14,
    fontFamily: fonts.sans.native.semibold,
    color: colors.ink,
    lineHeight: 18,
  },
  countPill: {
    position: 'absolute',
    top: 12,
    right: 12,
    paddingVertical: 2,
    paddingHorizontal: 8,
    borderRadius: 999,
    backgroundColor: colors.chip,
  },
});
