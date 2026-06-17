import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { DetailScreen } from '@/components/ui/Detail';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useArea } from '@/hooks/queries/useStructureIsa95';

/**
 * Area detail — ISA-95 breadcrumb + summary KPIs + lines in this area.
 * Matches ScreenAreaDetail from gaps.jsx.
 */
export function AreaDetail() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const numericId = Number(id);
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const query = useArea(numericId);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data)
    return <ErrorState error={query.error} onRetry={query.refetch} />;

  const area = query.data;
  const lines = area.lines ?? [];

  return (
    <DetailScreen title={area.name} subtitle={`${area.site?.name ?? '—'} › ${area.code} · ${t('ISA-95 area').toUpperCase()}`}>
      {/* Breadcrumb */}
      <View style={styles.breadcrumb}>
        <Mono size={11} color={palette.textMuted} letterSpacing={0.4}>
          {(area.site?.name ?? '—').toUpperCase()}
        </Mono>
        <FontAwesome name="chevron-right" size={11} color={palette.textFaint} />
        <Mono size={11} color={BRAND.amber} weight="700" letterSpacing={0.4}>
          {area.name.toUpperCase()}
        </Mono>
      </View>

      {/* Summary */}
      <Card>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
          {t('SUMMARY').toUpperCase()}
        </Mono>
        <View style={styles.summaryRow}>
          {[
            { l: 'LINES', v: String(area.lines_count ?? lines.length) },
            { l: 'WORKSTATIONS', v: '—' },
            { l: 'WORKERS ON', v: '—' },
          ].map((s) => (
            <View
              key={s.l}
              style={[styles.summaryTile, { backgroundColor: palette.surfaceAlt }]}>
              <Mono size={9.5} color={palette.textFaint} letterSpacing={0.5}>
                {s.l}
              </Mono>
              <Text style={[styles.summaryValue, { color: palette.text, fontFamily: MONO }]}>
                {s.v}
              </Text>
            </View>
          ))}
        </View>
      </Card>

      {/* Lines */}
      <View style={{ gap: 8 }}>
        <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
          {t('LINES IN AREA').toUpperCase()}
        </Mono>
        <Card style={{ padding: 0 }}>
          {lines.length === 0 ? (
            <View style={{ padding: 16 }}>
              <Mono size={11} color={palette.textFaint}>
                {t('No lines in this area').toUpperCase()}
              </Mono>
            </View>
          ) : (
            lines.map((line, i, arr) => (
              <Pressable
                key={line.id}
                onPress={() =>
                  router.push(`/structure/lines/${line.id}` as never)
                }
                style={({ pressed }) => [
                  styles.lineRow,
                  i === arr.length - 1
                    ? null
                    : {
                        borderBottomWidth: StyleSheet.hairlineWidth,
                        borderBottomColor: palette.border,
                      },
                  { opacity: pressed ? 0.85 : 1 },
                ]}>
                <View
                  style={[
                    styles.codeBadge,
                    { backgroundColor: palette.text },
                  ]}>
                  <Mono size={11} color={palette.background} weight="700">
                    {(line.code ?? line.name.slice(0, 4)).toUpperCase()}
                  </Mono>
                </View>
                <Text
                  style={[styles.lineName, { color: palette.text }]}
                  numberOfLines={1}>
                  {line.name}
                </Text>
                <StatusPill status="—" label={t('Queued')} />
              </Pressable>
            ))
          )}
        </Card>
      </View>
    </DetailScreen>
  );
}

const styles = StyleSheet.create({
  breadcrumb: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  summaryRow: { flexDirection: 'row', gap: 8, marginTop: 10 },
  summaryTile: { flex: 1, paddingVertical: 8, paddingHorizontal: 10, borderRadius: 8 },
  summaryValue: { fontSize: 18, fontWeight: '700', letterSpacing: -0.3, marginTop: 4 },
  lineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 12,
  },
  codeBadge: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  lineName: { flex: 1, fontSize: 13, fontWeight: '600' },
});
