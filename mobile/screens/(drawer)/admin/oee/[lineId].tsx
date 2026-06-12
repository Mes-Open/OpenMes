import { format, parseISO } from 'date-fns';
import { useLocalSearchParams } from 'expo-router';
import { useMemo } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useDowntimes } from '@/hooks/queries/useDowntime';
import { useLineDetail } from '@/hooks/queries/useLines';
import { useOeeForLine } from '@/hooks/queries/useOee';
import type { ProductionDowntime } from '@/api/downtime';
import type { OeeRecord } from '@/api/oee';

function num(v: number | string | null | undefined): number {
  if (v == null) return 0;
  return typeof v === 'string' ? parseFloat(v) : v;
}

function avg(vals: number[]): number | null {
  const filtered = vals.filter((v) => !Number.isNaN(v) && v > 0);
  if (filtered.length === 0) return null;
  return filtered.reduce((a, b) => a + b, 0) / filtered.length;
}

function bandColor(v: number | null | undefined, palette: typeof Colors.light): string {
  if (v == null) return palette.textFaint;
  if (v >= 85) return palette.success;
  if (v >= 60) return BRAND.amber;
  return palette.danger;
}

interface ReasonBucket {
  name: string;
  planned: boolean;
  min: number;
  count: number;
}

export function OeeLineScreen() {
  const { lineId } = useLocalSearchParams<{ lineId: string }>();
  const numericId = Number(lineId);
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const lineQ = useLineDetail(numericId);
  const oeeQ = useOeeForLine(numericId, 7);
  // Pull last 7 days of downtimes for this line for the "by reason" panel.
  const downtimesQ = useDowntimes({ line_id: numericId });

  const records: OeeRecord[] = oeeQ.data ?? [];

  const summary = useMemo(() => {
    if (records.length === 0) {
      return { oee: null, avail: null, perf: null, qual: null, totalProduced: 0, totalScrap: 0, totalDowntime: 0 };
    }
    return {
      oee: avg(records.map((r) => num(r.oee_pct))),
      avail: avg(records.map((r) => num(r.availability_pct))),
      perf: avg(records.map((r) => num(r.performance_pct))),
      qual: avg(records.map((r) => num(r.quality_pct))),
      totalProduced: records.reduce((s, r) => s + num(r.total_produced), 0),
      totalScrap: records.reduce((s, r) => s + num(r.scrap_qty), 0),
      totalDowntime: records.reduce((s, r) => s + r.downtime_minutes, 0),
    };
  }, [records]);

  const reasonBuckets = useMemo<ReasonBucket[]>(() => {
    const events: ProductionDowntime[] = downtimesQ.data ?? [];
    const map = new Map<string, ReasonBucket>();
    for (const e of events) {
      const key = e.reason?.name ?? 'Unknown';
      const planned = e.reason?.kind === 'planned';
      const minutes = e.duration_minutes ?? 0;
      const existing = map.get(key);
      if (existing) {
        existing.min += minutes;
        existing.count += 1;
      } else {
        map.set(key, { name: key, planned, min: minutes, count: 1 });
      }
    }
    return Array.from(map.values()).sort((a, b) => b.min - a.min);
  }, [downtimesQ.data]);

  const maxReasonMin = Math.max(1, ...reasonBuckets.map((r) => r.min));

  if (oeeQ.isLoading || lineQ.isLoading) return <LoadingState />;
  if (oeeQ.isError) return <ErrorState error={oeeQ.error} onRetry={oeeQ.refetch} />;

  const lineName = lineQ.data?.name ?? 'Line';
  const lineCode = lineQ.data?.code ?? `L-${String(numericId).padStart(2, '0')}`;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader
        back
        title={`${lineCode} · ${lineName}`}
        subtitle="OEE DETAIL · LAST 7 DAYS"
      />
      <ScrollView contentContainerStyle={styles.scroll}>
        {/* Hero */}
        <View style={styles.hero}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Mono size={11} color="#9a9aa2" letterSpacing={0.8}>CURRENT OEE</Mono>
            <Pressable
              onPress={() =>
                Alert.alert(
                  'OEE = A × P × Q',
                  'A — Availability: actual run time vs planned (downtime impact)\n' +
                    'P — Performance: actual speed vs ideal (slow cycles impact)\n' +
                    'Q — Quality: good units vs total produced (defects impact)\n\n' +
                    'Target: >85% world-class, 60–85% typical, <60% needs improvement.',
                )
              }
              hitSlop={8}
              style={styles.oeeHelpBtn}>
              <Mono size={11} color="#9a9aa2" weight="700">?</Mono>
            </Pressable>
          </View>
          <Text
            style={[
              styles.heroValue,
              { color: bandColor(summary.oee, palette), fontFamily: MONO },
            ]}>
            {summary.oee != null ? summary.oee.toFixed(1) : '—'}
            <Text style={styles.heroUnit}>%</Text>
          </Text>
          <View style={styles.heroStats}>
            {[
              { l: 'AVAILABILITY', v: summary.avail, sub: `${Math.round(records.reduce((s, r) => s + r.operating_minutes, 0))}/${Math.round(records.reduce((s, r) => s + r.planned_minutes, 0))}m` },
              { l: 'PERFORMANCE', v: summary.perf, sub: 'cycle calc' },
              { l: 'QUALITY', v: summary.qual, sub: `good ${Math.round(summary.totalProduced - summary.totalScrap)}` },
            ].map((s) => (
              <View key={s.l} style={styles.heroStatTile}>
                <Mono size={9.5} color="#9a9aa2" letterSpacing={0.6}>{s.l}</Mono>
                <Text style={[styles.heroStatValue, { fontFamily: MONO }]}>
                  {s.v != null ? `${s.v.toFixed(1)}%` : '—'}
                </Text>
                <Mono size={9.5} color="#9a9aa2" style={{ marginTop: 2 }}>{s.sub}</Mono>
              </View>
            ))}
          </View>
        </View>

        {/* Downtime by reason */}
        <SectionLabel>Downtime by reason · 7d</SectionLabel>
        <Card style={{ gap: 10 }}>
          {reasonBuckets.length === 0 ? (
            <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 8 }}>
              NO DOWNTIME RECORDED
            </Mono>
          ) : (
            reasonBuckets.map((r) => (
              <View key={r.name}>
                <View style={styles.reasonHeader}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flex: 1 }}>
                    <View
                      style={[
                        styles.reasonDot,
                        { backgroundColor: r.planned ? palette.info : palette.danger },
                      ]}
                    />
                    <Text style={[styles.reasonName, { color: palette.text }]} numberOfLines={1}>
                      {r.name}
                    </Text>
                    {r.planned ? (
                      <View style={[styles.plannedTag, { backgroundColor: palette.infoSoft }]}>
                        <Mono size={9} color={palette.info} weight="700" letterSpacing={0.5}>
                          PLANNED
                        </Mono>
                      </View>
                    ) : null}
                  </View>
                  <Mono size={11} color={palette.textMuted}>
                    <Text style={{ fontWeight: '600' }}>{r.min}m</Text>{' '}
                    <Text style={{ color: palette.textFaint }}>· {r.count}×</Text>
                  </Mono>
                </View>
                <View style={[styles.reasonBar, { backgroundColor: palette.surfaceAlt }]}>
                  <View
                    style={{
                      height: '100%',
                      width: `${(r.min / maxReasonMin) * 100}%`,
                      backgroundColor: r.planned ? palette.info : palette.danger,
                      borderRadius: 3,
                    }}
                  />
                </View>
              </View>
            ))
          )}
        </Card>

        {/* Daily records */}
        <SectionLabel>Daily records</SectionLabel>
        <Card style={{ padding: 0, overflow: 'hidden' }}>
          <View style={[styles.tableHeader, { backgroundColor: palette.surfaceAlt }]}>
            <Mono size={9.5} color={palette.textFaint} weight="600" style={{ flex: 60 }}>DATE</Mono>
            <Mono size={9.5} color={palette.textFaint} weight="600" style={{ width: 28 }}>SH</Mono>
            <Mono
              size={9.5}
              color={palette.textFaint}
              weight="600"
              style={{ flex: 1, textAlign: 'right' }}>
              OP/DT
            </Mono>
            <Mono
              size={9.5}
              color={palette.textFaint}
              weight="600"
              style={{ flex: 1, textAlign: 'right' }}>
              A/P/Q
            </Mono>
            <Mono
              size={9.5}
              color={palette.textFaint}
              weight="600"
              style={{ flex: 1, textAlign: 'right' }}>
              PROD/SC
            </Mono>
            <Mono
              size={9.5}
              color={palette.textFaint}
              weight="600"
              style={{ width: 50, textAlign: 'right' }}>
              OEE
            </Mono>
          </View>
          {records.length === 0 ? (
            <Mono size={11} color={palette.textFaint} style={{ padding: 14, textAlign: 'center' }}>
              NO RECORDS YET
            </Mono>
          ) : (
            records.map((r, i) => {
              const oee = num(r.oee_pct);
              const oeeColor = bandColor(oee, palette);
              const dateLabel = (() => {
                try {
                  return format(parseISO(r.record_date), 'EEE dd').toUpperCase();
                } catch {
                  return r.record_date;
                }
              })();
              const scrap = Math.round(num(r.scrap_qty));
              return (
                <View
                  key={r.id}
                  style={[
                    styles.tableRow,
                    i < records.length - 1 ? { borderBottomColor: palette.border, borderBottomWidth: StyleSheet.hairlineWidth } : null,
                  ]}>
                  <Mono size={10.5} color={palette.textMuted} style={{ flex: 60 }}>{dateLabel}</Mono>
                  <Mono size={10.5} color={palette.text} style={{ width: 28 }}>{r.shift?.name?.[0] ?? '—'}</Mono>
                  <Text style={{ flex: 1, textAlign: 'right', fontFamily: MONO, fontSize: 10.5, color: palette.text }}>
                    {r.operating_minutes}/<Text style={{ color: palette.danger }}>{r.downtime_minutes}</Text>
                  </Text>
                  <Mono size={10.5} color={palette.textMuted} style={{ flex: 1, textAlign: 'right' }}>
                    {Math.round(num(r.availability_pct))}/{Math.round(num(r.performance_pct))}/{Math.round(num(r.quality_pct))}
                  </Mono>
                  <Text style={{ flex: 1, textAlign: 'right', fontFamily: MONO, fontSize: 10.5, color: palette.text }}>
                    {Math.round(num(r.total_produced))}/<Text style={{ color: scrap > 6 ? palette.danger : palette.textMuted }}>{scrap}</Text>
                  </Text>
                  <Mono
                    size={10.5}
                    color={oeeColor}
                    weight="700"
                    style={{ width: 50, textAlign: 'right' }}>
                    {oee > 0 ? oee.toFixed(1) : '—'}
                  </Mono>
                </View>
              );
            })
          )}
        </Card>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  hero: { backgroundColor: '#0e0e10', borderRadius: 14, padding: 18 },
  oeeHelpBtn: {
    width: 18,
    height: 18,
    borderRadius: 9,
    backgroundColor: '#26262d',
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroValue: { fontSize: 56, fontWeight: '600', letterSpacing: -1.5, lineHeight: 56, marginTop: 4 },
  heroUnit: { fontSize: 24, color: '#9a9aa2' },
  heroStats: { flexDirection: 'row', gap: 8, marginTop: 14 },
  heroStatTile: {
    flex: 1,
    backgroundColor: 'rgba(255,255,255,0.06)',
    borderRadius: 8,
    padding: 10,
  },
  heroStatValue: { fontSize: 15, fontWeight: '600', color: '#fff', marginTop: 4 },
  reasonHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
  reasonDot: { width: 6, height: 6, borderRadius: 3 },
  reasonName: { fontSize: 12.5, fontWeight: '500', flexShrink: 1 },
  plannedTag: { paddingVertical: 1, paddingHorizontal: 4, borderRadius: 3 },
  reasonBar: { height: 6, borderRadius: 3, overflow: 'hidden' },
  tableHeader: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, paddingHorizontal: 12, gap: 6 },
  tableRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, paddingHorizontal: 12, gap: 6 },
});
