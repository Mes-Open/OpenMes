import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO, subDays } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useOee } from '@/hooks/queries/useOee';
import { useLines } from '@/hooks/queries/useUsers';
import { useDeviceClass } from '@/hooks/useDeviceClass';
import { TabletOeeCommand } from '@/screens/tablet/OeeCommand';
import type { OeeRecord } from '@/api/oee';
import type { Line } from '@/types/api';

type RangeId = '7d' | '30d' | '90d';
const RANGE_DAYS: Record<RangeId, number> = { '7d': 7, '30d': 30, '90d': 90 };

interface OeeBand {
  c: string;
  bg: string;
  label: string;
}

function oeeBand(v: number | null | undefined, palette: typeof Colors.light): OeeBand {
  if (v == null) return { c: palette.textFaint, bg: palette.surfaceAlt, label: 'NO DATA' };
  if (v >= 85) return { c: palette.success, bg: palette.successSoft, label: 'GOOD' };
  if (v >= 60) return { c: BRAND.amber, bg: palette.warningSoft, label: 'WATCH' };
  return { c: palette.danger, bg: palette.dangerSoft, label: 'CRITICAL' };
}

function useColors() {
  const scheme = useColorScheme() ?? 'light';
  return Colors[scheme];
}

function num(v: number | string | null | undefined): number {
  if (v == null) return 0;
  return typeof v === 'string' ? parseFloat(v) : v;
}

interface LineSummary {
  lineId: number;
  code: string;
  name: string;
  oee: number | null;
  avail: number | null;
  perf: number | null;
  qual: number | null;
  produced: number;
  scrap: number;
  downtime: number;
  hasData: boolean;
}

function avg(vals: (number | null)[]): number | null {
  const filtered = vals.filter((v): v is number => v != null && !Number.isNaN(v));
  if (filtered.length === 0) return null;
  return filtered.reduce((a, b) => a + b, 0) / filtered.length;
}

export function OeeDashboard() {
  const router = useRouter();
  const palette = useColors();
  const { useTabletLayout } = useDeviceClass();
  const [range, setRange] = useState<RangeId>('7d');

  // Tablet (landscape) variant: wide multi-pane OEE command center.
  if (useTabletLayout) return <TabletOeeCommand />;

  const dateFrom = format(subDays(new Date(), RANGE_DAYS[range]), 'yyyy-MM-dd');
  const dateTo = format(new Date(), 'yyyy-MM-dd');

  const linesQ = useLines();
  const oeeQ = useOee({ date_from: dateFrom, date_to: dateTo });

  const { plant, byLine, trend } = useMemo(() => {
    const records = oeeQ.data ?? [];
    const lines = linesQ.data ?? [];

    // Per-line aggregate
    const byLine: LineSummary[] = lines.map((line: Line) => {
      const lineRecs = records.filter((r) => r.line_id === line.id);
      const oeeVals = lineRecs.map((r) => num(r.oee_pct));
      return {
        lineId: line.id,
        code: line.code ?? `L-${String(line.id).padStart(2, '0')}`,
        name: line.name,
        oee: avg(oeeVals.length ? oeeVals : [null]),
        avail: avg(lineRecs.map((r) => num(r.availability_pct))),
        perf: avg(lineRecs.map((r) => num(r.performance_pct))),
        qual: avg(lineRecs.map((r) => num(r.quality_pct))),
        produced: lineRecs.reduce((s, r) => s + num(r.total_produced), 0),
        scrap: lineRecs.reduce((s, r) => s + num(r.scrap_qty), 0),
        downtime: lineRecs.reduce((s, r) => s + r.downtime_minutes, 0),
        hasData: lineRecs.length > 0,
      };
    });

    // Plant aggregate
    const allOee = records.map((r) => num(r.oee_pct)).filter((v) => v > 0);
    const plant = {
      oee: avg(allOee),
      avail: avg(records.map((r) => num(r.availability_pct))),
      perf: avg(records.map((r) => num(r.performance_pct))),
      qual: avg(records.map((r) => num(r.quality_pct))),
    };

    // Last 7 days OEE trend (regardless of range — kept short)
    const trend: { date: string; oee: number | null }[] = [];
    for (let i = 6; i >= 0; i--) {
      const d = format(subDays(new Date(), i), 'yyyy-MM-dd');
      const dayRecs = records.filter((r) => r.record_date.startsWith(d));
      trend.push({
        date: d,
        oee: avg(dayRecs.map((r) => num(r.oee_pct))),
      });
    }

    return { plant, byLine, trend };
  }, [oeeQ.data, linesQ.data]);

  if (oeeQ.isLoading || linesQ.isLoading) return <LoadingState />;
  if (oeeQ.isError) return <ErrorState error={oeeQ.error} onRetry={oeeQ.refetch} />;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title="OEE"
        subtitle={`${byLine.length} LINES · LAST ${range.toUpperCase()}`}
      />
      <ScrollView contentContainerStyle={styles.scroll}>
        {/* Range chips */}
        <View style={styles.rangeRow}>
          {(['7d', '30d', '90d'] as RangeId[]).map((id) => {
            const active = id === range;
            return (
              <Pressable
                key={id}
                onPress={() => setRange(id)}
                style={[
                  styles.rangeChip,
                  {
                    backgroundColor: active ? palette.surfaceInverse : palette.surface,
                    borderColor: active ? palette.surfaceInverse : palette.border,
                  },
                ]}>
                <Mono
                  size={11}
                  color={active ? (palette === Colors.dark ? '#1A1917' : '#fff') : palette.textMuted}
                  weight="600"
                  letterSpacing={0.5}>
                  {id.toUpperCase()}
                </Mono>
              </Pressable>
            );
          })}
        </View>

        {/* Plant aggregate */}
        <View style={styles.heroCard}>
          <Mono size={11} color="#6F6C66" letterSpacing={0.8}>PLANT AGGREGATE</Mono>
          <View style={styles.heroRow}>
            <Text style={styles.heroValue}>
              {plant.oee != null ? plant.oee.toFixed(1) : '—'}
              <Text style={styles.heroValueUnit}>%</Text>
            </Text>
          </View>
          <View style={styles.heroStats}>
            {[
              { l: 'AVAIL', v: plant.avail, c: palette.success },
              { l: 'PERF', v: plant.perf, c: BRAND.amber },
              { l: 'QUAL', v: plant.qual, c: palette.success },
            ].map((s) => (
              <View key={s.l} style={styles.heroStatTile}>
                <Mono size={9.5} color="#6F6C66" letterSpacing={0.6}>{s.l}</Mono>
                <Text style={[styles.heroStatValue, { color: s.c, fontFamily: MONO }]}>
                  {s.v != null ? s.v.toFixed(1) : '—'}%
                </Text>
              </View>
            ))}
          </View>
        </View>

        {/* Trend */}
        <Card style={{ gap: 12 }}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>OEE TREND · 7 DAYS</Mono>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {[
                { c: palette.success, l: '≥85' },
                { c: BRAND.amber, l: '60–84' },
                { c: palette.danger, l: '<60' },
              ].map((b) => (
                <View key={b.l} style={{ flexDirection: 'row', alignItems: 'center', gap: 3 }}>
                  <View style={{ width: 8, height: 8, backgroundColor: b.c, borderRadius: 2 }} />
                  <Mono size={9.5} color={palette.textFaint}>{b.l}</Mono>
                </View>
              ))}
            </View>
          </View>
          <View style={styles.trendRow}>
            {trend.map((d, i) => {
              const v = d.oee ?? 0;
              const band = oeeBand(d.oee, palette);
              const dayLabel = ['M', 'T', 'W', 'T', 'F', 'S', 'S'][parseISO(d.date).getDay() === 0 ? 6 : parseISO(d.date).getDay() - 1];
              return (
                <View key={d.date} style={styles.trendCol}>
                  <View
                    style={{
                      width: '100%',
                      height: `${Math.max(2, v)}%`,
                      backgroundColor: band.c,
                      borderRadius: 3,
                      opacity: d.oee == null ? 0.25 : 1,
                    }}
                  />
                  <Mono size={9} color={palette.textFaint}>{dayLabel}</Mono>
                </View>
              );
            })}
          </View>
        </Card>

        <SectionLabel>By line</SectionLabel>
        {byLine.length === 0 ? (
          <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 20 }}>
            NO LINES CONFIGURED
          </Mono>
        ) : (
          byLine.map((l) => <LineCard key={l.lineId} l={l} onPress={() =>
            router.push(`/admin/oee/${l.lineId}` as never)
          } />)
        )}
      </ScrollView>
    </View>
  );
}

function LineCard({ l, onPress }: { l: LineSummary; onPress: () => void }) {
  const palette = useColors();
  const band = oeeBand(l.oee, palette);

  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <View
        style={[
          styles.lineCard,
          {
            backgroundColor: palette.surface,
            borderColor: palette.border,
            borderLeftColor: band.c,
          },
        ]}>
        <View style={styles.lineHeader}>
          <View style={{ flex: 1 }}>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.5}>{l.code}</Mono>
            <Text style={[styles.lineName, { color: palette.text }]}>{l.name}</Text>
          </View>
          <View style={{ alignItems: 'flex-end' }}>
            <Text style={[styles.lineOee, { color: band.c, fontFamily: MONO }]}>
              {l.oee != null ? l.oee.toFixed(1) : '—'}
              <Text style={{ fontSize: 14 }}>%</Text>
            </Text>
            <Mono size={9.5} color={band.c} weight="700" letterSpacing={0.6}>{band.label}</Mono>
          </View>
        </View>
        <View style={styles.apqGrid}>
          {[
            { l: 'A%', v: l.avail },
            { l: 'P%', v: l.perf },
            { l: 'Q%', v: l.qual },
          ].map((s) => (
            <View key={s.l} style={[styles.apqTile, { backgroundColor: palette.surfaceAlt }]}>
              <Mono size={9.5} color={palette.textFaint}>{s.l}</Mono>
              <Mono size={13} color={palette.text} weight="600" style={{ marginTop: 2 }}>
                {s.v != null ? s.v.toFixed(1) : '—'}
              </Mono>
            </View>
          ))}
        </View>
        <View style={styles.lineFooter}>
          <Footer label="PROD" value={String(Math.round(l.produced))} palette={palette} />
          <Footer
            label="SCRAP"
            value={String(Math.round(l.scrap))}
            palette={palette}
            danger={l.scrap > 30}
          />
          <Footer
            label="DT"
            value={`${l.downtime}m`}
            palette={palette}
            danger={l.downtime > 60}
          />
        </View>
      </View>
    </Pressable>
  );
}

function Footer({
  label,
  value,
  palette,
  danger,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
  danger?: boolean;
}) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 4 }}>
      <Mono size={10.5} color={palette.textFaint}>{label}</Mono>
      <Mono
        size={10.5}
        color={danger ? palette.danger : palette.text}
        weight={danger ? '700' : '600'}>
        {value}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  rangeRow: { flexDirection: 'row', gap: 6 },
  rangeChip: {
    flex: 1,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: 'center',
  },
  heroCard: {
    backgroundColor: '#F6F5F1',
    borderRadius: 14,
    padding: 16,
  },
  heroRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 14, marginTop: 6 },
  heroValue: {
    color: '#fff',
    fontFamily: MONO,
    fontSize: 44,
    fontWeight: '600',
    letterSpacing: -1,
    lineHeight: 46,
  },
  heroValueUnit: { fontSize: 22, color: '#6F6C66' },
  heroStats: { flexDirection: 'row', gap: 8, marginTop: 12 },
  heroStatTile: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 8,
    padding: 10,
  },
  heroStatValue: { fontSize: 18, fontWeight: '600', marginTop: 4 },
  trendRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 6, height: 80 },
  trendCol: { flex: 1, alignItems: 'center', gap: 4 },
  lineCard: {
    borderWidth: 1,
    borderLeftWidth: 4,
    borderRadius: 12,
    padding: 14,
    marginBottom: 10,
  },
  lineHeader: { flexDirection: 'row', gap: 10, alignItems: 'flex-start' },
  lineName: { fontSize: 15, fontWeight: '600', marginTop: 3 },
  lineOee: { fontSize: 24, fontWeight: '600', letterSpacing: -0.4, lineHeight: 26 },
  apqGrid: { flexDirection: 'row', gap: 6, marginTop: 12 },
  apqTile: { flex: 1, padding: 10, borderRadius: 8 },
  lineFooter: { flexDirection: 'row', gap: 14, marginTop: 10 },
});
