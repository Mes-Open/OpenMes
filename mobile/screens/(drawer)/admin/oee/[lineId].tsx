import { format, parseISO } from 'date-fns';
import { useLocalSearchParams } from 'expo-router';
import { useMemo } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
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

function bandColor(v: number | null | undefined): string {
  if (v == null) return colors.faint;
  if (v >= 85) return colors.running;
  if (v >= 65) return colors.downtime;
  return colors.blocked;
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
  const { t } = useTranslation();

  const lineQ = useLineDetail(numericId);
  const oeeQ = useOeeForLine(numericId, 7);
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

  if ((oeeQ.isLoading || lineQ.isLoading) && !oeeQ.data) return <LoadingState />;
  if (oeeQ.isError && !oeeQ.data) return <ErrorState error={oeeQ.error} onRetry={oeeQ.refetch} />;

  const lineName = lineQ.data?.name ?? t('Line');
  const lineCode = lineQ.data?.code ?? `L-${String(numericId).padStart(2, '0')}`;

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1} numberOfLines={1}>{`${lineCode} · ${lineName}`}</Text>
      </View>
      <ScrollView style={styles.screen} contentContainerStyle={styles.scroll}>
        {/* Hero */}
        <View style={styles.box}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <Mono size={11} color={colors.faint} letterSpacing={0.8}>{t('Current OEE').toUpperCase()}</Mono>
            <Pressable
              onPress={() =>
                Alert.alert(
                  t('OEE = A × P × Q'),
                  t('A — Availability: actual run time vs planned (downtime impact)\nP — Performance: actual speed vs ideal (slow cycles impact)\nQ — Quality: good units vs total produced (defects impact)\n\nTarget: >85% world-class, 65–84% typical, <65% needs improvement.'),
                )
              }
              hitSlop={8}
              style={styles.helpBtn}>
              <Mono size={11} color={colors.muted} weight="700">?</Mono>
            </Pressable>
          </View>
          <Mono size={56} color={bandColor(summary.oee)} weight="600" style={{ marginTop: 4 }}>
            {summary.oee != null ? summary.oee.toFixed(1) : '—'}
            <Mono size={24} color={colors.faint} weight="600">%</Mono>
          </Mono>
          <View style={styles.heroStats}>
            {[
              { l: t('Availability'), v: summary.avail, sub: `${Math.round(records.reduce((s, r) => s + r.operating_minutes, 0))}/${Math.round(records.reduce((s, r) => s + r.planned_minutes, 0))}m` },
              { l: t('Performance'), v: summary.perf, sub: t('cycle calc') },
              { l: t('Quality'), v: summary.qual, sub: `${t('good')} ${Math.round(summary.totalProduced - summary.totalScrap)}` },
            ].map((s) => (
              <View key={s.l} style={styles.heroStatTile}>
                <Mono size={9.5} color={colors.faint} letterSpacing={0.6}>{s.l.toUpperCase()}</Mono>
                <Mono size={15} color={colors.ink} weight="600" style={{ marginTop: 4 }}>
                  {s.v != null ? `${s.v.toFixed(1)}%` : '—'}
                </Mono>
                <Mono size={9.5} color={colors.faint} style={{ marginTop: 2 }}>{s.sub}</Mono>
              </View>
            ))}
          </View>
        </View>

        {/* Downtime by reason */}
        <Mono size={9} color={colors.faint} letterSpacing={0.6} style={{ paddingHorizontal: 2 }}>{t('Downtime by reason · 7d').toUpperCase()}</Mono>
        <View style={[styles.box, { gap: 10 }]}>
          {reasonBuckets.length === 0 ? (
            <Mono size={11} color={colors.faint} style={{ textAlign: 'center', padding: 8 }}>{t('No downtime recorded').toUpperCase()}</Mono>
          ) : (
            reasonBuckets.map((r) => (
              <View key={r.name}>
                <View style={styles.reasonHeader}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6, flex: 1 }}>
                    <View style={[styles.reasonDot, { backgroundColor: r.planned ? colors.pending : colors.blocked }]} />
                    <Text style={styles.reasonName} numberOfLines={1}>{r.name}</Text>
                    {r.planned ? (
                      <View style={styles.plannedTag}>
                        <Mono size={9} color={colors.pending} weight="700" letterSpacing={0.5}>{t('Planned').toUpperCase()}</Mono>
                      </View>
                    ) : null}
                  </View>
                  <Mono size={11} color={colors.muted}>
                    <Mono size={11} color={colors.muted} weight="600">{r.min}m</Mono>{' '}
                    <Mono size={11} color={colors.faint}>· {r.count}×</Mono>
                  </Mono>
                </View>
                <View style={styles.reasonBar}>
                  <View
                    style={{
                      height: '100%',
                      width: `${(r.min / maxReasonMin) * 100}%`,
                      backgroundColor: r.planned ? colors.pending : colors.blocked,
                      borderRadius: 3,
                    }}
                  />
                </View>
              </View>
            ))
          )}
        </View>

        {/* Daily records */}
        <Mono size={9} color={colors.faint} letterSpacing={0.6} style={{ paddingHorizontal: 2 }}>{t('Daily records').toUpperCase()}</Mono>
        <View style={[styles.box, { padding: 0, overflow: 'hidden' }]}>
          <View style={styles.tableHeader}>
            <Mono size={9.5} color={colors.faint} weight="600" style={{ flex: 60 }}>{t('Date').toUpperCase()}</Mono>
            <Mono size={9.5} color={colors.faint} weight="600" style={{ width: 28 }}>{t('Sh').toUpperCase()}</Mono>
            <Mono size={9.5} color={colors.faint} weight="600" style={{ flex: 1, textAlign: 'right' }}>OP/DT</Mono>
            <Mono size={9.5} color={colors.faint} weight="600" style={{ flex: 1, textAlign: 'right' }}>A/P/Q</Mono>
            <Mono size={9.5} color={colors.faint} weight="600" style={{ flex: 1, textAlign: 'right' }}>PROD/SC</Mono>
            <Mono size={9.5} color={colors.faint} weight="600" style={{ width: 50, textAlign: 'right' }}>OEE</Mono>
          </View>
          {records.length === 0 ? (
            <Mono size={11} color={colors.faint} style={{ padding: 14, textAlign: 'center' }}>{t('No records yet').toUpperCase()}</Mono>
          ) : (
            records.map((r, i) => {
              const oee = num(r.oee_pct);
              const oeeColor = bandColor(oee);
              const dateLabel = (() => {
                try {
                  return format(parseISO(r.record_date), 'EEE dd').toUpperCase();
                } catch {
                  return r.record_date;
                }
              })();
              const scrap = Math.round(num(r.scrap_qty));
              return (
                <View key={r.id} style={[styles.tableRow, i < records.length - 1 ? styles.rowBorder : null]}>
                  <Mono size={10.5} color={colors.muted} style={{ flex: 60 }}>{dateLabel}</Mono>
                  <Mono size={10.5} color={colors.ink} style={{ width: 28 }}>{r.shift?.name?.[0] ?? '—'}</Mono>
                  <Text style={{ flex: 1, textAlign: 'right', fontFamily: fonts.mono.native.regular, fontSize: 10.5, color: colors.ink }}>
                    {r.operating_minutes}/<Text style={{ color: colors.blocked }}>{r.downtime_minutes}</Text>
                  </Text>
                  <Mono size={10.5} color={colors.muted} style={{ flex: 1, textAlign: 'right' }}>
                    {Math.round(num(r.availability_pct))}/{Math.round(num(r.performance_pct))}/{Math.round(num(r.quality_pct))}
                  </Mono>
                  <Text style={{ flex: 1, textAlign: 'right', fontFamily: fonts.mono.native.regular, fontSize: 10.5, color: colors.ink }}>
                    {Math.round(num(r.total_produced))}/<Text style={{ color: scrap > 6 ? colors.blocked : colors.muted }}>{scrap}</Text>
                  </Text>
                  <Mono size={10.5} color={oeeColor} weight="700" style={{ width: 50, textAlign: 'right' }}>
                    {oee > 0 ? oee.toFixed(1) : '—'}
                  </Mono>
                </View>
              );
            })
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 16 },
  helpBtn: { width: 18, height: 18, borderRadius: 9, backgroundColor: colors.chip, alignItems: 'center', justifyContent: 'center' },
  heroStats: { flexDirection: 'row', gap: 8, marginTop: 14 },
  heroStatTile: { flex: 1, backgroundColor: colors.panel, borderRadius: radius.sm, padding: 10 },
  reasonHeader: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 4 },
  reasonDot: { width: 6, height: 6, borderRadius: 3 },
  reasonName: { fontSize: 12.5, fontFamily: fonts.sans.native.medium, color: colors.ink, flexShrink: 1 },
  plannedTag: { paddingVertical: 1, paddingHorizontal: 4, borderRadius: radius.sm, backgroundColor: colors.pendingBg },
  reasonBar: { height: 6, borderRadius: 3, overflow: 'hidden', backgroundColor: colors.chip },
  tableHeader: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, paddingHorizontal: 12, gap: 6, borderBottomWidth: 1, borderBottomColor: colors.line },
  tableRow: { flexDirection: 'row', alignItems: 'center', paddingVertical: 8, paddingHorizontal: 12, gap: 6 },
  rowBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
});
