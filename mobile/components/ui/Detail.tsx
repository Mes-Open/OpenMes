// Light-only v1: Colors[scheme] switching dropped — Geist White tokens.
import { FontAwesome } from '@expo/vector-icons';
import { useNavigation } from '@react-navigation/native';
import {
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  type ViewStyle,
} from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';

interface DetailScreenProps {
  children: React.ReactNode;
  contentStyle?: ViewStyle;
  /** When set, renders a chrome bar (back arrow + title/subtitle) above the scroll. */
  title?: string;
  subtitle?: string;
  /** Show a back arrow even when there's no title. Defaults to true if a Stack
   * parent can pop the screen. */
  back?: boolean;
}

/** Standard ScrollView wrapper for show/edit pages with consistent padding + spacing. */
export function DetailScreen({ children, contentStyle, title, subtitle, back }: DetailScreenProps) {
  const navigation = useNavigation();
  const showChrome = title != null || back === true;
  // When the parent Stack can pop, default to back=true so the user always has
  // a back arrow on detail pages without the unstyled Stack header.
  const autoBack = back ?? navigation.canGoBack();

  const content = (
    <ScrollView
      style={{ backgroundColor: colors.bg, flex: 1 }}
      contentContainerStyle={[styles.container, contentStyle]}
      keyboardShouldPersistTaps="handled">
      {children}
    </ScrollView>
  );

  if (showChrome) {
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <ScreenHeader title={title} subtitle={subtitle} back={autoBack} />
        {content}
      </View>
    );
  }
  if (autoBack) {
    // No title given but we want at least a chrome with back, so the Stack
    // header can be turned off without losing navigation.
    return (
      <View style={{ flex: 1, backgroundColor: colors.bg }}>
        <ScreenHeader back />
        {content}
      </View>
    );
  }
  return content;
}

interface StatItem {
  label: string;
  value: number | string;
  accent?: string;
}

interface StatPanelProps {
  title?: string;
  items: StatItem[];
  /** "row" packs values to the right; "grid" lays them out as 2-col KPI tiles. */
  variant?: 'row' | 'grid';
}

/** Stat list (key/value rows or KPI grid) commonly shown on detail pages. */
export function StatPanel({ title, items, variant = 'row' }: StatPanelProps) {
  if (variant === 'grid') {
    return (
      <View style={{ gap: 8 }}>
        {title ? <SectionLabel>{title}</SectionLabel> : null}
        <View style={styles.grid}>
          {items.map((it) => (
            <Card key={it.label} style={styles.kpi}>
              <Mono size={10} color={colors.faint} letterSpacing={1.2}>
                {it.label.toUpperCase()}
              </Mono>
              <Text style={[styles.kpiValue, { color: it.accent ?? colors.ink }]}>
                {it.value}
              </Text>
            </Card>
          ))}
        </View>
      </View>
    );
  }

  return (
    <Card style={{ gap: 4 }}>
      {title ? <SectionLabel>{title}</SectionLabel> : null}
      {items.map((it, i) => (
        <View
          key={it.label}
          style={[
            styles.statRow,
            i < items.length - 1
              ? { borderBottomColor: colors.line2, borderBottomWidth: StyleSheet.hairlineWidth }
              : null,
          ]}>
          <Text style={styles.statLabel}>{it.label}</Text>
          <Text style={[styles.statValue, { color: it.accent ?? colors.ink }]}>{it.value}</Text>
        </View>
      ))}
    </Card>
  );
}

interface LinkRowProps {
  icon?: React.ComponentProps<typeof FontAwesome>['name'];
  title: string;
  subtitle?: string;
  count?: number | string;
  onPress: () => void;
}

/** Navigation row to a nested resource (e.g. "Workstations (3) ›"). */
export function LinkRowCard({ icon, title, subtitle, count, onPress }: LinkRowProps) {
  return (
    <Pressable accessibilityRole="button" onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <Card style={styles.linkRow}>
        {icon ? (
          <View style={styles.iconWrap}>
            <FontAwesome name={icon} size={15} color={colors.accent} />
          </View>
        ) : null}
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.linkTitle} numberOfLines={1}>
            {title}
            {count != null ? <Text style={styles.linkCount}> · {count}</Text> : null}
          </Text>
          {subtitle ? (
            <Mono size={10} color={colors.faint} letterSpacing={0.6} style={{ marginTop: 3 }}>
              {subtitle.toUpperCase()}
            </Mono>
          ) : null}
        </View>
        <FontAwesome name="chevron-right" size={12} color={colors.faintest} />
      </Card>
    </Pressable>
  );
}

interface DangerZoneProps {
  /** "Deactivate" / "Activate" — toggle button shown above delete. */
  toggleLabel?: string;
  onToggle?: () => void;
  toggleLoading?: boolean;
  /** Delete button text and confirmation. */
  deleteLabel?: string;
  deleteConfirmTitle?: string;
  deleteConfirmMessage?: string;
  onDelete: () => void;
  deleteLoading?: boolean;
}

/** Standard activate-toggle + destructive delete pair shown at the bottom of detail pages. */
export function DangerZone({
  toggleLabel,
  onToggle,
  toggleLoading,
  deleteLabel = 'Delete',
  deleteConfirmTitle,
  deleteConfirmMessage,
  onDelete,
  deleteLoading,
}: DangerZoneProps) {
  const handleDelete = () => {
    if (deleteConfirmTitle) {
      Alert.alert(deleteConfirmTitle, deleteConfirmMessage ?? '', [
        { text: 'Cancel', style: 'cancel' },
        { text: deleteLabel, style: 'destructive', onPress: onDelete },
      ]);
    } else {
      onDelete();
    }
  };

  return (
    <View style={{ gap: 10 }}>
      {toggleLabel && onToggle ? (
        <Button
          title={toggleLabel}
          variant="outline"
          loading={!!toggleLoading}
          onPress={onToggle}
        />
      ) : null}
      <Button
        title={deleteLabel}
        variant="danger"
        leftIcon={<FontAwesome name="trash" size={13} color={colors.blocked} />}
        loading={!!deleteLoading}
        onPress={handleDelete}
      />
    </View>
  );
}

interface DetailHeroProps {
  eyebrow?: string;
  title: string;
  subtitle?: string;
  trailing?: React.ReactNode;
}

/** Hero block for show pages — eyebrow ID, big title, subtitle, optional trailing pill. */
export function DetailHero({ eyebrow, title, subtitle, trailing }: DetailHeroProps) {
  return (
    <View style={styles.hero}>
      <View style={{ flex: 1, minWidth: 0 }}>
        {eyebrow ? (
          <Mono size={10} color={colors.faint} letterSpacing={1.2}>
            {eyebrow.toUpperCase()}
          </Mono>
        ) : null}
        <Text style={styles.heroTitle}>{title}</Text>
        {subtitle ? (
          <Text style={styles.heroSub} numberOfLines={2}>
            {subtitle}
          </Text>
        ) : null}
      </View>
      {trailing}
    </View>
  );
}

const styles = StyleSheet.create({
  container: { padding: 18, gap: 14 },
  hero: { flexDirection: 'row', alignItems: 'flex-start', gap: 10 },
  heroTitle: {
    fontSize: 24,
    fontFamily: fonts.sans.native.semibold,
    letterSpacing: -0.5,
    color: colors.ink,
    marginTop: 4,
  },
  heroSub: {
    fontSize: 13,
    lineHeight: 19,
    marginTop: 4,
    color: colors.muted,
    fontFamily: fonts.sans.native.regular,
  },
  statRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 10 },
  statLabel: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  statValue: { fontSize: 14, fontFamily: fonts.mono.native.medium },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kpi: { flexBasis: '48%', flexGrow: 1, gap: 6 },
  kpiValue: { fontSize: 22, fontFamily: fonts.mono.native.medium },
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconWrap: {
    width: 38,
    height: 38,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: `${colors.accent}1A`,
  },
  linkTitle: { fontSize: 14, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  linkCount: { color: colors.faint, fontFamily: fonts.sans.native.medium },
});
