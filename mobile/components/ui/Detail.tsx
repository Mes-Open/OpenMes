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

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';

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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const navigation = useNavigation();
  const showChrome = title != null || back === true;
  // When the parent Stack can pop, default to back=true so the user always has
  // a back arrow on detail pages without the unstyled Stack header.
  const autoBack = back ?? navigation.canGoBack();

  const content = (
    <ScrollView
      style={{ backgroundColor: palette.background, flex: 1 }}
      contentContainerStyle={[styles.container, contentStyle]}
      keyboardShouldPersistTaps="handled">
      {children}
    </ScrollView>
  );

  if (showChrome) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <ScreenHeader title={title} subtitle={subtitle} back={autoBack} />
        {content}
      </View>
    );
  }
  if (autoBack) {
    // No title given but we want at least a chrome with back, so the Stack
    // header can be turned off without losing navigation.
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  if (variant === 'grid') {
    return (
      <View style={{ gap: 8 }}>
        {title ? <SectionLabel>{title}</SectionLabel> : null}
        <View style={styles.grid}>
          {items.map((it) => (
            <Card key={it.label} style={styles.kpi}>
              <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>
                {it.label.toUpperCase()}
              </Mono>
              <Text style={[styles.kpiValue, { color: it.accent ?? palette.text, fontFamily: MONO }]}>
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
            i < items.length - 1 ? { borderBottomColor: palette.border, borderBottomWidth: StyleSheet.hairlineWidth } : null,
          ]}>
          <Text style={[styles.statLabel, { color: palette.textMuted }]}>{it.label}</Text>
          <Text style={[styles.statValue, { color: it.accent ?? palette.text, fontFamily: MONO }]}>
            {it.value}
          </Text>
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <Card style={styles.linkRow}>
        {icon ? (
          <View style={[styles.iconWrap, { backgroundColor: '#fbe9c8' }]}>
            <FontAwesome name={icon} size={15} color={BRAND.amber} />
          </View>
        ) : null}
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.linkTitle, { color: palette.text }]} numberOfLines={1}>
            {title}
            {count != null ? (
              <Text style={{ color: palette.textFaint, fontWeight: '500' }}> · {count}</Text>
            ) : null}
          </Text>
          {subtitle ? (
            <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
              {subtitle.toUpperCase()}
            </Mono>
          ) : null}
        </View>
        <FontAwesome name="chevron-right" size={12} color={palette.textFaint} />
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
        leftIcon={<FontAwesome name="trash" size={13} color="#fff" />}
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={styles.hero}>
      <View style={{ flex: 1, minWidth: 0 }}>
        {eyebrow ? (
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
            {eyebrow.toUpperCase()}
          </Mono>
        ) : null}
        <Text style={[styles.heroTitle, { color: palette.text }]}>{title}</Text>
        {subtitle ? (
          <Text style={[styles.heroSub, { color: palette.textMuted }]} numberOfLines={2}>
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
  heroTitle: { fontSize: 24, fontWeight: '600', letterSpacing: -0.4, marginTop: 4 },
  heroSub: { fontSize: 13, lineHeight: 19, marginTop: 4 },
  statRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', paddingVertical: 10 },
  statLabel: { fontSize: 13 },
  statValue: { fontSize: 14, fontWeight: '600' },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kpi: { flexBasis: '48%', flexGrow: 1, gap: 6 },
  kpiValue: { fontSize: 22, fontWeight: '600' },
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconWrap: { width: 38, height: 38, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  linkTitle: { fontSize: 15, fontWeight: '600', letterSpacing: -0.2 },
});
