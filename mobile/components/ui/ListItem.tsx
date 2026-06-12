import { FontAwesome } from '@expo/vector-icons';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

interface Props {
  /** Optional 2-3 char mono badge shown on the left (codes/IDs/initials). */
  badge?: string;
  /** Or a FontAwesome icon as the leading visual. */
  icon?: React.ComponentProps<typeof FontAwesome>['name'];
  /** Tints the icon background + icon stroke; defaults to brand amber. */
  iconColor?: string;
  /** Mono uppercase line above the title (e.g. an ID or category). */
  eyebrow?: string;
  /** Main title. */
  title: string;
  /** Optional subtitle (mono, uppercase). */
  subtitle?: string;
  /** Inline pill rendered next to the title (e.g. severity tag MINOR/MAJOR/SCRAP). */
  inlineBadge?: { label: string; color: string };
  /** Status pill or other element on the trailing edge. */
  trailing?: React.ReactNode;
  /** When set, shows a chevron + handles tap. */
  onPress?: () => void;
  /** Disable the row visually. */
  disabled?: boolean;
  /** A thin colored stripe on the top of the card. */
  accent?: string;
  /** Whether to show the trailing chevron when onPress is set. Defaults to true. */
  chevron?: boolean;
}

export function ListItem({
  badge,
  icon,
  iconColor,
  eyebrow,
  title,
  subtitle,
  inlineBadge,
  trailing,
  onPress,
  disabled,
  accent,
  chevron = true,
}: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  // Icon tint — design uses severity-colored icon backgrounds (`color22`
  // for the surface, full color for the stroke). Defaults preserve the
  // existing amber/cream look.
  const iconC = iconColor ?? BRAND.amber;
  const iconBg = iconColor ? `${iconColor}22` : '#fbe9c8';

  const inner = (
    <View
      style={[
        styles.row,
        { backgroundColor: palette.surface, borderColor: palette.border, opacity: disabled ? 0.55 : 1 },
      ]}>
      {accent ? <View style={[styles.accent, { backgroundColor: accent }]} /> : null}
      {badge ? (
        <View style={[styles.badge, { backgroundColor: palette.surfaceAlt }]}>
          <Mono size={11} color={palette.text} weight="700">{badge.slice(0, 4).toUpperCase()}</Mono>
        </View>
      ) : icon ? (
        <View style={[styles.iconWrap, { backgroundColor: iconBg }]}>
          <FontAwesome name={icon} size={15} color={iconC} />
        </View>
      ) : null}
      <View style={{ flex: 1, minWidth: 0 }}>
        {eyebrow ? (
          <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>
            {eyebrow.toUpperCase()}
          </Mono>
        ) : null}
        <View style={styles.titleRow}>
          <Text style={[styles.title, { color: palette.text }]} numberOfLines={1}>
            {title}
          </Text>
          {inlineBadge ? (
            <View style={[styles.inlineBadge, { backgroundColor: `${inlineBadge.color}22` }]}>
              <Mono size={9} color={inlineBadge.color} weight="700" letterSpacing={0.5}>
                {inlineBadge.label.toUpperCase()}
              </Mono>
            </View>
          ) : null}
        </View>
        {subtitle ? (
          <Mono size={11} color={palette.textFaint} style={{ marginTop: 2 }}>
            {subtitle.toUpperCase()}
          </Mono>
        ) : null}
      </View>
      {trailing}
      {onPress && chevron ? (
        <FontAwesome name="chevron-right" size={11} color={palette.textFaint} />
      ) : null}
    </View>
  );

  if (onPress && !disabled) {
    return (
      <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
        {inner}
      </Pressable>
    );
  }
  return inner;
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 14,
    borderWidth: 1,
    overflow: 'hidden',
  },
  accent: { position: 'absolute', top: 0, left: 0, right: 0, height: 2 },
  badge: { width: 44, height: 44, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  iconWrap: { width: 38, height: 38, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: 6, flexWrap: 'wrap' },
  title: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2, marginTop: 2, flexShrink: 1 },
  inlineBadge: { paddingVertical: 2, paddingHorizontal: 5, borderRadius: 3 },
});
