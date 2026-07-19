import { format, parseISO, subDays } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useOee } from '@/hooks/queries/useOee';
import { useLines } from '@/hooks/queries/useUsers';
import type { Line } from '@/types/api';

type RangeId = '7d' | '30d' | '90d';
const RANGE_DAYS: Record<RangeId, number> = { '7d': 7, '30d': 30, '90d': 90 };

interface OeeBand {
  c: string;
  bg: string;
  label: string;
}

function oeeBand(v: number | null | undefined): OeeBand {
  if (v == null) return { c: colors.faint, bg: colors.chip, label: 'No data' };
  if (v >= 85) return { c: colors.running, bg: colors.runningBg, label: 'Good' };
  if (v >= 65) return { c: colors.downtime, bg: colors.downtimeBg, label: 'Watch' };
  return { c: colors.blocked, bg: colors.blockedBg, label: 'Critical' };
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
  const { t } = useTranslation();
  const [range, setRange] = useState<RangeId>('7d');

  const dateFrom = format(subDays(new Date(), RANGE_DAYS[range]), 'yyyy-MM-dd');
  const dateTo = format(new Date(), 'yyyy-MM-dd');

  const linesQ = useLines();
  const oeeQ = useOee({ date_from: dateFrom, date_to: dateTo });

  const { plant, byLine, trend } = useMemo(() => {
    const records = oeeQ.data ?? [];
    const lines = linesQ.data ?? [];

    const byLine: LineSummary[] = lines.map((line: Line) => {
      const lineRecs = records.filter((r) => r.line_id === line.id);
      // Exclude records without an OEE value — don't coerce null→0, which would
      // drag the line's average down as if it had recorded a 0% day.
      const oeeVals = lineRecs.map((r) => (r.oee_pct == null ? null : num(r.oee_pct)));
      return {
        lineId: line.id,
        code: line.code ?? `L-${String(line.id).padStart(2, '0')}`,
        name: line.name,
        oee: avg(oeeVals),
        avail: avg(lineRecs.map((r) => num(r.availability_pct))),
        perf: avg(lineRecs.map((r) => num(r.performance_pct))),
        qual: avg(lineRecs.map((r) => num(r.quality_pct))),
        produced: lineRecs.reduce((s, r) => s + num(r.total_produced), 0),
        scrap: lineRecs.reduce((s, r) => s + num(r.scrap_qty), 0),
        downtime: lineRecs.reduce((s, r) => s + r.downtime_minutes, 0),
        hasData: lineRecs.length > 0,
      };
    });

    const allOee = records.map((r) => num(r.oee_pct)).filter((v) => v > 0);
    const plant = {
      oee: avg(allOee),
      avail: avg(records.map((r) => num(r.availability_pct))),
      perf: avg(records.map((r) => num(r.performance_pct))),
      qual: avg(records.map((r) => num(r.quality_pct))),
    };

    const trend: { date: string; oee: number | null }[] = [];
    for (let i = 6; i >= 0; i--) {
      const d = format(subDays(new Date(), i), 'yyyy-MM-dd');
      const dayRecs = records.filter((r) => r.record_date.startsWith(d));
      trend.push({ date: d, oee: avg(dayRecs.map((r) => num(r.oee_pct))) });
    }

    return { plant, byLine, trend };
  }, [oeeQ.data, linesQ.data]);

  if ((oeeQ.isLoading || linesQ.isLoading) && !oeeQ.data) return <LoadingState />;
  if (oeeQ.isError && !oeeQ.data) return <ErrorState error={oeeQ.error} onRetry={oeeQ.refetch} />;

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('OEE')}</Text>
        <View style={{ flex: 1 }} />
        <View style={styles.rangeRow}>
          {(['7d', '30d', '90d'] as RangeId[]).map((id) => {
            const active = id === range;
            return (
              <Pressable
                key={id}
                onPress={() => setRange(id)}
                style={[styles.rangeChip, active ? styles.rangeChipActive : null]}>
                <Mono size={11} color={active ? '#FFFFFF' : colors.muted} weight="600" letterSpacing={0.5}>
                  {id.toUpperCase()}
                </Mono>
              </Pressable>
            );
          })}
        </View>
      </View>

      <ScrollView style={styles.screen} contentContainerStyle={styles.scroll}>
        {/* Plant aggregate */}
        <View style={styles.box}>
          <Mono size={11} color={colors.faint} letterSpacing={0.8}>{t('Plant aggregate').toUpperCase()}</Mono>
          <View style={styles.heroRow}>
            <Text style={styles.heroValue}>
              {plant.oee != null ? plant.oee.toFixed(1) : '—'}
              <Text style={styles.heroValueUnit}>%</Text>
            </Text>
          </View>
          <View style={styles.heroStats}>
            {[
              { l: t('Avail'), v: plant.avail, c: colors.running },
              { l: t('Perf'), v: plant.perf, c: colors.downtime },
              { l: t('Qual'), v: plant.qual, c: colors.running },
            ].map((s) => (
              <View key={s.l} style={styles.heroStatTile}>
                <Mono size={9.5} color={colors.faint} letterSpacing={0.6}>{s.l.toUpperCase()}</Mono>
                <Mono size={18} color={s.c} weight="600" style={{ marginTop: 4 }}>
                  {s.v != null ? `${s.v.toFixed(1)}%` : '—'}
                </Mono>
              </View>
            ))}
          </View>
        </View>

        {/* Trend */}
        <View style={[styles.box, { gap: 12 }]}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Mono size={11} color={colors.faint} letterSpacing={0.8}>{t('OEE trend · 7 days').toUpperCase()}</Mono>
            <View style={{ flexDirection: 'row', gap: 8 }}>
              {[
                { c: colors.running, l: '≥85' },
                { c: colors.downtime, l: '65–84' },
                { c: colors.blocked, l: '<65' },
              ].map((b) => (
                <View key={b.l} style={{ flexDirection: 'row', alignItems: 'center', gap: 3 }}>
                  <View style={{ width: 8, height: 8, backgroundColor: b.c, borderRadius: 2 }} />
                  <Mono size={9.5} color={colors.faint}>{b.l}</Mono>
                </View>
              ))}
            </View>
          </View>
          <View style={styles.trendRow}>
            {trend.map((d) => {
              const v = d.oee ?? 0;
              const band = oeeBand(d.oee);
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
                  <Mono size={9} color={colors.faint}>{dayLabel}</Mono>
                </View>
              );
            })}
          </View>
        </View>

        <Mono size={9} color={colors.faint} letterSpacing={0.6} style={{ paddingHorizontal: 2 }}>{t('By line').toUpperCase()}</Mono>
        {byLine.length === 0 ? (
          <Mono size={11} color={colors.faint} style={{ textAlign: 'center', padding: 20 }}>{t('No lines configured').toUpperCase()}</Mono>
        ) : (
          byLine.map((l) => <LineCard key={l.lineId} l={l} onPress={() => router.push(`/admin/oee/${l.lineId}` as never)} />)
        )}
      </ScrollView>
    </View>
  );
}

function LineCard({ l, onPress }: { l: LineSummary; onPress: () => void }) {
  const { t } = useTranslation();
  const band = oeeBand(l.oee);

  return (
    <Pressable onPress={onPress} style={({ pressed }) => ({ opacity: pressed ? 0.85 : 1 })}>
      <View style={[styles.lineCard, { borderLeftColor: band.c }]}>
        <View style={styles.lineHeader}>
          <View style={{ flex: 1 }}>
            <Mono size={10.5} color={colors.faint} letterSpacing={0.5}>{l.code}</Mono>
            <Text style={styles.lineName}>{l.name}</Text>
          </View>
          <View style={{ alignItems: 'flex-end' }}>
            <Mono size={24} color={band.c} weight="600">
              {l.oee != null ? l.oee.toFixed(1) : '—'}
              <Mono size={14} color={band.c} weight="600">%</Mono>
            </Mono>
            <Mono size={9.5} color={band.c} weight="700" letterSpacing={0.6}>{t(band.label).toUpperCase()}</Mono>
          </View>
        </View>
        <View style={styles.apqGrid}>
          {[
            { l: 'A%', v: l.avail },
            { l: 'P%', v: l.perf },
            { l: 'Q%', v: l.qual },
          ].map((s) => (
            <View key={s.l} style={styles.apqTile}>
              <Mono size={9.5} color={colors.faint}>{s.l}</Mono>
              <Mono size={13} color={colors.ink} weight="600" style={{ marginTop: 2 }}>
                {s.v != null ? s.v.toFixed(1) : '—'}
              </Mono>
            </View>
          ))}
        </View>
        <View style={styles.lineFooter}>
          <Footer label={t('Prod').toUpperCase()} value={String(Math.round(l.produced))} />
          <Footer label={t('Scrap').toUpperCase()} value={String(Math.round(l.scrap))} danger={l.scrap > 30} />
          <Footer label={t('DT').toUpperCase()} value={`${l.downtime}m`} danger={l.downtime > 60} />
        </View>
      </View>
    </Pressable>
  );
}

function Footer({ label, value, danger }: { label: string; value: string; danger?: boolean }) {
  return (
    <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 4 }}>
      <Mono size={10.5} color={colors.faint}>{label}</Mono>
      <Mono size={10.5} color={danger ? colors.blocked : colors.ink} weight={danger ? '700' : '600'}>{value}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 16 },
  rangeRow: { flexDirection: 'row', gap: 6 },
  rangeChip: { paddingVertical: 6, paddingHorizontal: 10, borderRadius: radius.sm, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.card },
  rangeChipActive: { backgroundColor: colors.ink, borderColor: colors.ink },
  heroRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 14, marginTop: 6 },
  heroValue: { color: colors.ink, fontFamily: fonts.mono.native.semibold, fontSize: 44, letterSpacing: -1, lineHeight: 46 },
  heroValueUnit: { fontSize: 22, color: colors.faint },
  heroStats: { flexDirection: 'row', gap: 8, marginTop: 12 },
  heroStatTile: { flex: 1, backgroundColor: colors.panel, borderRadius: radius.sm, padding: 10 },
  trendRow: { flexDirection: 'row', alignItems: 'flex-end', gap: 6, height: 80 },
  trendCol: { flex: 1, alignItems: 'center', gap: 4 },
  lineCard: {
    borderWidth: 1,
    borderColor: colors.line,
    borderLeftWidth: 4,
    borderRadius: radius.md,
    padding: 14,
    backgroundColor: colors.card,
  },
  lineHeader: { flexDirection: 'row', gap: 10, alignItems: 'flex-start' },
  lineName: { fontSize: 15, fontFamily: fonts.sans.native.semibold, color: colors.ink, marginTop: 3 },
  apqGrid: { flexDirection: 'row', gap: 6, marginTop: 12 },
  apqTile: { flex: 1, padding: 10, borderRadius: radius.sm, backgroundColor: colors.panel },
  lineFooter: { flexDirection: 'row', gap: 14, marginTop: 10 },
});
