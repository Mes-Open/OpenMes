/**
 * Area detail — mirrors the web admin areas Show page: breadcrumb, header with
 * name/code/site, Status + Description cards, and the Lines table. Tapping a
 * line opens its detail; the Edit button opens the area editor. Data via the
 * existing useArea hook; navigation targets preserved.
 */
import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useArea } from '@/hooks/queries/useStructureIsa95';

export function AreaDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const numericId = Number(id);
  const { t } = useTranslation();

  const query = useArea(numericId);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} onRetry={query.refetch} />;

  const area = query.data;
  const lines = area.lines ?? [];

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      {/* Breadcrumb */}
      <View style={styles.breadcrumb}>
        <Mono size={10} color={colors.muted} letterSpacing={0.4} numberOfLines={1}>
          {(area.site?.name ?? '—').toUpperCase()}
        </Mono>
        <FontAwesome name="chevron-right" size={9} color={colors.faint} />
        <Mono size={10} color={colors.accent} weight="600" letterSpacing={0.4} numberOfLines={1}>
          {area.name.toUpperCase()}
        </Mono>
      </View>

      {/* Header */}
      <View style={styles.headerRow}>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.h1}>{area.name}</Text>
          <Mono size={11} color={colors.muted} style={{ marginTop: 6 }}>{area.code}</Mono>
        </View>
        <Button title={t('Edit')} size="sm" variant="outline" onPress={() => router.push(`/structure/areas/${area.id}/edit` as never)} />
      </View>

      {/* Status */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Status').toUpperCase()}</Mono>
        <View style={styles.box}>
          <Text style={[styles.statusText, { color: area.is_active ? colors.running : colors.faint }]}>
            {area.is_active ? t('Active') : t('Inactive')}
          </Text>
        </View>
      </View>

      {/* Description */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Description').toUpperCase()}</Mono>
        <View style={styles.box}>
          <Text style={styles.notes}>{area.description || '—'}</Text>
        </View>
      </View>

      {/* Lines */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>
          {`${t('Lines')} · ${lines.length}`.toUpperCase()}
        </Mono>
        {lines.length === 0 ? (
          <Text style={styles.empty}>{t('No lines assigned to this area yet.')}</Text>
        ) : (
          <View style={styles.box}>
            <View style={[styles.row, styles.tableHead]}>
              <HCell w={72}>{t('Code')}</HCell>
              <HCell flex={1}>{t('Name')}</HCell>
            </View>
            {lines.map((line, i, arr) => (
              <Pressable
                key={line.id}
                onPress={() => router.push(`/structure/lines/${line.id}` as never)}
                style={({ pressed }) => [
                  styles.row,
                  i === arr.length - 1 ? null : styles.tableRow,
                  { paddingVertical: 12, opacity: pressed ? 0.6 : 1 },
                ]}>
                <View style={{ width: 72 }}>
                  <Mono size={11} color={colors.muted} numberOfLines={1}>{line.code ?? '—'}</Mono>
                </View>
                <View style={{ flex: 1 }}>
                  <Text numberOfLines={1} style={styles.lineName}>{line.name}</Text>
                </View>
              </Pressable>
            ))}
          </View>
        )}
      </View>
    </ScrollView>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  breadcrumb: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 14, paddingVertical: 4 },
  statusText: { fontSize: 13, fontFamily: fonts.sans.native.semibold, paddingVertical: 8 },
  notes: { fontSize: 13, lineHeight: 20, color: colors.muted, fontFamily: fonts.sans.native.regular, paddingVertical: 8 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  tableHead: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  tableRow: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  lineName: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.accent },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
