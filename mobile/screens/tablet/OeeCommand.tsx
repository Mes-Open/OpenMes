import { format, formatDistanceToNowStrict, parseISO, subDays } from 'date-fns';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useActiveDowntime, useDowntimes } from '@/hooks/queries/useDowntime';
import { useLines } from '@/hooks/queries/useUsers';
import { useOee, useOeeForLine } from '@/hooks/queries/useOee';
import type { OeeRecord } from '@/api/oee';
import type { ProductionDowntime } from '@/api/downtime';
import type { Line } from '@/types/api';

function num(v: number | string | null | undefined): number {
  if (v == null) return 0;
  return typeof v === 'string' ? parseFloat(v) : v;
}

function bandColor(v: number | null | undefined, palette: typeof Colors.light): string {
  if (v == null || v <= 0) return palette.textFaint;
  if (v >= 85) return palette.success;
  if (v >= 60) return BRAND.amber;
  return palette.danger;
}

function avg(vals: number[]): number | null {
  const filtered = vals.filter((v) => v > 0 && !Number.isNaN(v));
  if (filtered.length === 0) return null;
  return filtered.reduce((a, b) => a + b, 0) / filtered.length;
}

export function TabletOeeCommand() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const linesQ = useLines();
  const lines: Line[] = linesQ.data ?? [];

  const [selectedLineId, setSelectedLineId] = useState<number | null>(null);
  const selectedId = selectedLineId ?? lines[0]?.id ?? null;

  // Last 7 days plant-wide.
  const dateFrom = format(subDays(new Date(), 7), 'yyyy-MM-dd');
  const dateTo = format(new Date(), 'yyyy-MM-dd');
  const plantOeeQ = useOee({ date_from: dateFrom, date_to: dateTo });

  // Per-line latest record map (real API data only — mock fallback handled below).
  const realLatestByLine = useMemo(() => {
    const map = new Map<number, number>();
    const recs = plantOeeQ.data ?? [];
    for (const r of recs) {
      const v = num(r.oee_pct);
      if (v <= 0) continue;
      const prev = map.get(r.line_id);
      if (prev == null) map.set(r.line_id, v);
      // Latest by ISO compare on the record (records sorted desc already).
    }
    return map;
  }, [plantOeeQ.data]);

  // Effective per-line OEE: real value when available, position-indexed mock
  const effectiveOeeForLine = (lineId: number): number | null => {
    return realLatestByLine.get(lineId) ?? null;
  };

  // Plant aggregate (avg of effective OEE per line).
  const plantOee = useMemo(() => {
    const vals = lines.map((l) => effectiveOeeForLine(l.id)).filter((v): v is number => v != null);
    return avg(vals);
  }, [lines, realLatestByLine]);

  // Drill-down: selected line's 7-day records.
  const lineRecordsQ = useOeeForLine(selectedId && selectedId > 0 ? selectedId : undefined, 7);
  const lineRecords: OeeRecord[] = lineRecordsQ.data ?? [];

  const lineSummary = useMemo(() => {
    if (lineRecords.length === 0) return { oee: null, a: null, p: null, q: null };
    const latest = lineRecords[0];
    return {
      oee: num(latest.oee_pct),
      a: num(latest.availability_pct),
      p: num(latest.performance_pct),
      q: num(latest.quality_pct),
    };
  }, [lineRecords]);

  // 7-day OEE trend chart values.
  const trendPoints = useMemo(() => {
    const arr: { day: string; v: number | null }[] = [];
    for (let i = 6; i >= 0; i--) {
      const dayStr = format(subDays(new Date(), i), 'yyyy-MM-dd');
      const rec = lineRecordsQ.data?.find((r) => r.record_date.startsWith(dayStr));
      const v = rec ? num(rec.oee_pct) : null;
      arr.push({ day: format(subDays(new Date(), i), 'EEE').toUpperCase(), v });
    }
    return arr;
  }, [lineRecordsQ.data]);
  const trendAvg = avg(trendPoints.map((p) => p.v).filter((v): v is number => v != null));

  // Downtime data.
  const downtimesQ = useDowntimes({ line_id: selectedId && selectedId > 0 ? selectedId : undefined });
  const downtimes: ProductionDowntime[] = downtimesQ.data ?? [];
  const activeDowntimeQ = useActiveDowntime(selectedId && selectedId > 0 ? selectedId : undefined);
  const activeDowntime = activeDowntimeQ.data ?? null;

  // Downtime by reason (last 7 days).
  const downtimeBuckets = useMemo(() => {
    const map = new Map<string, { name: string; m: number; planned: boolean }>();
    for (const e of downtimesQ.data ?? []) {
      const key = e.reason?.name ?? 'Unknown';
      const existing = map.get(key);
      const m = e.duration_minutes ?? 0;
      if (existing) existing.m += m;
      else map.set(key, { name: key, m, planned: e.reason?.kind === 'planned' });
    }
    return Array.from(map.values()).sort((a, b) => b.m - a.m).slice(0, 5);
  }, [downtimesQ.data]);
  const maxBucketMin = Math.max(1, ...downtimeBuckets.map((b) => b.m));

  // Vs-yesterday delta for the hero subtitle.
  const yesterdayOee = useMemo(() => {
    if (lineRecords.length < 2) return null;
    return num(lineRecords[1].oee_pct);
  }, [lineRecords]);
  const oeeDelta =
    lineSummary.oee != null && yesterdayOee != null
      ? lineSummary.oee - yesterdayOee
      : null;

  const selectedLine = lines.find((l) => l.id === selectedId);

  return (
    <TabletShell
      eyebrow={[
        t('OEE COMMAND').toUpperCase(),
        selectedLine ? `${(selectedLine.code ?? 'L?').toUpperCase()} · ${selectedLine.name.toUpperCase()}` : t('No line selected').toUpperCase(),
      ].join(' · ')}
      title={selectedLine ? `${selectedLine.code ?? ''} · ${selectedLine.name}` : t('OEE Command')}
      right={
        <>
          <View style={[styles.rangePill, { backgroundColor: palette.surfaceAlt }]}>
            <Mono size={11} color={palette.textMuted} letterSpacing={0.4}>{t('SHIFT').toUpperCase()} · 7D · 30D</Mono>
          </View>
          <Pressable
            onPress={() => {}}
            style={({ pressed }) => [
              styles.exportBtn,
              { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
            ]}>
            <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
              {t('Export report').toUpperCase()}
            </Mono>
          </Pressable>
        </>
      }>
      <View style={styles.grid3}>
        {/* LEFT — lines list + plant aggregate */}
        <View style={[styles.panel, { backgroundColor: palette.surface, borderColor: palette.border }]}>
          <View style={{ padding: 14 }}>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>{t('LINES').toUpperCase()} · A-SHIFT</Mono>
          </View>
          <ScrollView style={{ flex: 1 }} contentContainerStyle={{ padding: 14, paddingTop: 0, gap: 8 }}>
            {lines.map((line) => {
              const oee = effectiveOeeForLine(line.id);
              const active = line.id === selectedId;
              return (
                <Pressable
                  key={line.id}
                  onPress={() => setSelectedLineId(line.id)}
                  style={({ pressed }) => [
                    styles.lineRow,
                    {
                      backgroundColor: active ? '#F1EFEA' : palette.surfaceAlt,
                      borderColor: active ? '#F1EFEA' : palette.border,
                      opacity: pressed ? 0.9 : 1,
                    },
                  ]}>
                  <View
                    style={[
                      styles.lineBadge,
                      {
                        backgroundColor: active ? BRAND.amber : palette.surfaceAlt,
                        borderColor: palette.border,
                      },
                    ]}>
                    <Mono
                      size={11}
                      color={active ? '#1a1208' : palette.text}
                      weight="700">
                      {(line.code ?? `L${line.id}`).replace('L-', '')}
                    </Mono>
                  </View>
                  <View style={{ flex: 1, minWidth: 0 }}>
                    <Text
                      style={[
                        styles.lineName,
                        { color: active ? '#fff' : palette.text },
                      ]}
                      numberOfLines={1}>
                      {line.name}
                    </Text>
                    <Mono
                      size={10}
                      color={active ? '#6F6C66' : palette.textFaint}
                      letterSpacing={0.4}>
                      {line.code ?? ''}
                    </Mono>
                  </View>
                  <Mono
                    size={18}
                    weight="700"
                    color={bandColor(oee, palette)}
                    letterSpacing={-0.3}>
                    {oee != null ? oee.toFixed(1) : '—'}
                  </Mono>
                </Pressable>
              );
            })}
          </ScrollView>
          <View style={[styles.plantAggregate, { backgroundColor: palette.surfaceAlt }]}>
            <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>{t('Plant aggregate').toUpperCase()}</Mono>
            <Mono size={26} color={palette.text} weight="700" letterSpacing={-0.5}>
              {plantOee != null ? plantOee.toFixed(1) : '—'}
              <Text style={{ fontSize: 14, color: palette.textFaint, fontWeight: '500' }}>{' %'}</Text>
            </Mono>
            <Mono size={9.5} color={palette.success} letterSpacing={0.4}>↑ 2.1 {t('vs yesterday').toUpperCase()}</Mono>
          </View>
        </View>

        {/* CENTER — hero + APQ + trend + downtime + records */}
        <View style={[styles.centerCol]}>
          {/* Hero + APQ row */}
          <View style={styles.heroRow}>
            <View style={styles.heroBig}>
              <Mono size={10.5} color="#6F6C66" letterSpacing={0.8}>OEE · A-SHIFT</Mono>{/* shift label stays code-style */}
              <Mono
                size={68}
                weight="600"
                color={bandColor(lineSummary.oee, palette)}
                letterSpacing={-2.5}
                style={{ marginTop: 4, lineHeight: 70 }}>
                {lineSummary.oee != null ? lineSummary.oee.toFixed(1) : '—'}
                <Text style={{ fontSize: 28, color: '#6F6C66', fontWeight: '500' }}>{' %'}</Text>
              </Mono>
              <Mono
                size={10.5}
                color={oeeDelta != null && oeeDelta < 0 ? palette.danger : palette.success}
                letterSpacing={0.4}
                style={{ marginTop: 6 }}>
                {oeeDelta != null
                  ? `${oeeDelta >= 0 ? '↑' : '↓'} ${Math.abs(oeeDelta).toFixed(1)} ${t('vs yesterday').toUpperCase()} · ${t('TARGET').toUpperCase()} 85.0`
                  : trendAvg != null
                  ? `7D ${t('AVG').toUpperCase()} ${trendAvg.toFixed(1)} · ${t('TARGET').toUpperCase()} 85.0`
                  : `${t('TARGET').toUpperCase()} 85.0`}
              </Mono>
            </View>
            <ApqTile
              label={t('AVAIL').toUpperCase()}
              v={lineSummary.a}
              sub={
                lineRecords[0]
                  ? `${lineRecords[0].operating_minutes ?? 0}m / ${lineRecords[0].planned_minutes ?? 0}m ${t('PRODUCTIVE').toUpperCase()}`
                  : 'OPERATING / PLANNED'
              }
              palette={palette}
            />
            <ApqTile label={t('PERF').toUpperCase()} v={lineSummary.p} sub={`${t('CYCLE').toUpperCase()} 02:42`} palette={palette} />
            <ApqTile
              label={t('QUAL').toUpperCase()}
              v={lineSummary.q}
              sub={
                lineRecords[0]
                  ? `${Math.round(num(lineRecords[0].scrap_qty))} ${t('SCR').toUpperCase()} / ${Math.round(num(lineRecords[0].total_produced))}`
                  : 'SCRAP / TOTAL'
              }
              palette={palette}
            />
          </View>

          {/* 7d trend */}
          <View style={[styles.panel, { padding: 16, backgroundColor: palette.surface, borderColor: palette.border, flexGrow: 0 }]}>
            <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>{t('7-day OEE trend').toUpperCase()}</Mono>
              <Mono size={10.5} color={palette.textFaint}>
                AVG{' '}
                <Text style={{ color: palette.text, fontWeight: '700' }}>
                  {trendAvg != null ? `${trendAvg.toFixed(1)}%` : '—'}
                </Text>
              </Mono>
            </View>
            <View style={styles.trendRow}>
              {trendPoints.map((p, i) => (
                <View key={i} style={{ flex: 1, alignItems: 'center', gap: 4 }}>
                  <Mono size={9.5} color={palette.textFaint}>{p.v != null ? p.v.toFixed(0) : '—'}</Mono>
                  <View
                    style={{
                      width: '100%',
                      height: `${p.v ?? 2}%`,
                      backgroundColor: bandColor(p.v, palette),
                      borderTopLeftRadius: 3,
                      borderTopRightRadius: 3,
                      opacity: p.v == null ? 0.2 : 1,
                    }}
                  />
                  <Mono size={9.5} color={palette.textFaint} letterSpacing={0.4}>{p.day}</Mono>
                </View>
              ))}
            </View>
          </View>

          {/* Downtime by reason + Records table */}
          <View style={styles.twoColRow}>
            <View style={[styles.panel, { padding: 16, backgroundColor: palette.surface, borderColor: palette.border }]}>
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>{t('DOWNTIME').toUpperCase()} · 7D</Mono>
              <View style={{ marginTop: 12, gap: 10 }}>
                {downtimeBuckets.length === 0 ? (
                  <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 16 }}>
                    {t('No downtime recorded').toUpperCase()}
                  </Mono>
                ) : (
                  downtimeBuckets.map((b) => (
                    <View key={b.name}>
                      <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
                        <Text style={{ fontSize: 12, color: palette.text }} numberOfLines={1}>
                          {b.name}
                          {b.planned ? `  (${t('planned')})` : ''}
                        </Text>
                        <Mono size={11} color={palette.text} weight="600">{b.m} m</Mono>
                      </View>
                      <View style={[styles.bar, { backgroundColor: palette.surfaceAlt }]}>
                        <View
                          style={{
                            width: `${(b.m / maxBucketMin) * 100}%`,
                            height: '100%',
                            backgroundColor: b.planned ? palette.info : palette.danger,
                            borderRadius: 3,
                          }}
                        />
                      </View>
                    </View>
                  ))
                )}
              </View>
            </View>

            <View style={[styles.panel, { padding: 16, backgroundColor: palette.surface, borderColor: palette.border }]}>
              <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>{t('OEE records').toUpperCase()} · 7D</Mono>
              <View style={[styles.tableHead, { borderBottomColor: palette.border }]}>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ width: 70 }}>{t('Date').toUpperCase()}</Mono>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ flex: 1, textAlign: 'right' }}>A</Mono>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ flex: 1, textAlign: 'right' }}>P</Mono>
                <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ flex: 1, textAlign: 'right' }}>Q</Mono>
                <Mono size={10} color={palette.text} weight="700" letterSpacing={0.4} style={{ flex: 1, textAlign: 'right' }}>OEE</Mono>
              </View>
              <ScrollView style={{ flex: 1 }}>
                {lineRecords.length === 0 ? (
                  <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 14 }}>
                    NO RECORDS
                  </Mono>
                ) : (
                  lineRecords.slice(0, 8).map((r) => {
                    const oee = num(r.oee_pct);
                    return (
                      <View key={r.id} style={[styles.tableRow, { borderBottomColor: palette.surfaceAlt }]}>
                        <Mono size={11} color={palette.textMuted} style={{ width: 70 }}>
                          {r.record_date.slice(5)}
                        </Mono>
                        <Mono size={11} color={palette.text} style={{ flex: 1, textAlign: 'right' }}>
                          {num(r.availability_pct).toFixed(0)}
                        </Mono>
                        <Mono size={11} color={palette.text} style={{ flex: 1, textAlign: 'right' }}>
                          {num(r.performance_pct).toFixed(0)}
                        </Mono>
                        <Mono size={11} color={palette.text} style={{ flex: 1, textAlign: 'right' }}>
                          {num(r.quality_pct).toFixed(0)}
                        </Mono>
                        <Mono
                          size={11}
                          color={bandColor(oee, palette)}
                          weight="700"
                          style={{ flex: 1, textAlign: 'right' }}>
                          {oee.toFixed(1)}
                        </Mono>
                      </View>
                    );
                  })
                )}
              </ScrollView>
            </View>
          </View>
        </View>

        {/* RIGHT — Active + recent downtime */}
        <View style={[styles.panel, { backgroundColor: palette.surface, borderColor: palette.border, padding: 14 }]}>
          <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
            <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>{t('Active downtime').toUpperCase()}</Mono>
            <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: palette.danger }} />
          </View>

          {activeDowntime ? (
            <View
              style={[
                styles.activeDowntime,
                { backgroundColor: palette.dangerSoft, borderColor: palette.danger },
              ]}>
              <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                <View style={[styles.livePulse, { backgroundColor: palette.danger }]} />
                <Mono size={10.5} color={palette.danger} weight="700" letterSpacing={0.6}>
                  {(selectedLine?.code ?? '—').toUpperCase()}
                  {selectedLine?.name ? ` · ${selectedLine.name.toUpperCase()}` : ''}
                </Mono>
              </View>
              <Text style={[styles.activeTitle, { color: '#7f1d1d' }]} numberOfLines={2}>
                {activeDowntime.reason?.name ?? t('Unknown')}
              </Text>
              <Mono size={24} color={palette.danger} weight="600" letterSpacing={-0.5} style={{ marginTop: 8 }}>
                {(() => {
                  try {
                    const minutes = Math.floor(
                      (Date.now() - new Date(activeDowntime.started_at).getTime()) / 60000,
                    );
                    const h = Math.floor(minutes / 60);
                    const m = minutes % 60;
                    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:00`;
                  } catch {
                    return '—';
                  }
                })()}
              </Mono>
              <Mono size={10} color={'#7f1d1d'} letterSpacing={0.4} style={{ marginTop: 4 }}>
                {t('STARTED').toUpperCase()}{' '}
                {(() => {
                  try {
                    return format(parseISO(activeDowntime.started_at), 'HH:mm');
                  } catch {
                    return '—';
                  }
                })()}
                {activeDowntime.reported_by_user?.username
                  ? ` · ${t('BY').toUpperCase()} ${activeDowntime.reported_by_user.username.toUpperCase()}`
                  : ''}
              </Mono>
            </View>
          ) : (
            <View
              style={[
                styles.activeDowntime,
                { backgroundColor: palette.successSoft, borderColor: palette.success },
              ]}>
              <Mono size={10.5} color={palette.success} weight="700" letterSpacing={0.6}>
                {t('Line running — no active downtime').toUpperCase()}
              </Mono>
            </View>
          )}

          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8} style={{ marginTop: 16 }}>
            {t('RECENT').toUpperCase()} · {t('LAST 4H').toUpperCase()}
          </Mono>
          <ScrollView style={{ flex: 1, marginTop: 10 }} contentContainerStyle={{ gap: 8 }}>
            {downtimes
              .filter((d) => d.ended_at)
              .slice(0, 6)
              .map((e) => {
                const ago = (() => {
                  try {
                    return formatDistanceToNowStrict(parseISO(e.started_at));
                  } catch {
                    return '';
                  }
                })();
                const planned = e.reason?.kind === 'planned';
                return (
                  <View
                    key={e.id}
                    style={[styles.eventRow, { borderBottomColor: palette.surfaceAlt }]}>
                    <View
                      style={[
                        styles.eventRail,
                        { backgroundColor: planned ? palette.info : palette.danger },
                      ]}
                    />
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text style={{ fontSize: 12, fontWeight: '600', color: palette.text }} numberOfLines={1}>
                        {e.reason?.name ?? t('Unknown')}
                      </Text>
                      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 2 }}>
                        {e.line?.name ?? ''} · {ago}
                      </Mono>
                    </View>
                    <Mono size={11} color={palette.text} weight="600">
                      {(e.duration_minutes ?? 0)}m
                    </Mono>
                  </View>
                );
              })}
            {downtimes.filter((d) => d.ended_at).length === 0 ? (
              <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 16 }}>
                {t('No recent downtime').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
          <Pressable
            onPress={() => {}}
            style={({ pressed }) => [
              styles.viewHistoryBtn,
              { borderColor: palette.border, opacity: pressed ? 0.85 : 1 },
            ]}>
            <Mono size={11} color={palette.text} weight="700" letterSpacing={0.6}>
              {t('View full history').toUpperCase()}
            </Mono>
          </Pressable>
        </View>
      </View>
    </TabletShell>
  );
}

function ApqTile({
  label,
  v,
  sub,
  palette,
}: {
  label: string;
  v: number | null | undefined;
  sub: string;
  palette: typeof Colors.light;
}) {
  return (
    <View style={[styles.apqTile, { backgroundColor: palette.surface, borderColor: palette.border }]}>
      <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>{label}</Mono>
      <Mono
        size={36}
        weight="600"
        color={palette.text}
        letterSpacing={-1}
        style={{ marginTop: 4, lineHeight: 38 }}>
        {v != null ? v.toFixed(1) : '—'}
        <Text style={{ fontSize: 16, color: palette.textFaint, fontWeight: '500' }}>{' %'}</Text>
      </Mono>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 8 }}>
        {sub}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  rangePill: { paddingVertical: 6, paddingHorizontal: 10, borderRadius: 6 },
  exportBtn: {
    height: 38,
    paddingHorizontal: 14,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  viewHistoryBtn: {
    marginTop: 12,
    height: 44,
    borderRadius: 10,
    borderWidth: 1,
    backgroundColor: 'transparent',
    alignItems: 'center',
    justifyContent: 'center',
  },
  grid3: { flex: 1, flexDirection: 'row', gap: 14, minHeight: 0 },
  panel: { borderRadius: 16, borderWidth: 1, overflow: 'hidden', flex: 1 },
  centerCol: { flex: 2, gap: 12 },

  lineRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  lineBadge: {
    width: 38,
    height: 38,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
  },
  lineName: { fontSize: 13, fontWeight: '600' },
  plantAggregate: { padding: 12, margin: 12, marginTop: 0, borderRadius: 10 },

  heroRow: { flexDirection: 'row', gap: 10 },
  heroBig: {
    flex: 1.4,
    backgroundColor: '#F6F5F1',
    borderRadius: 16,
    padding: 18,
  },
  apqTile: {
    flex: 1,
    borderRadius: 16,
    borderWidth: 1,
    padding: 16,
  },

  trendRow: { flexDirection: 'row', alignItems: 'flex-end', marginTop: 14, height: 100, gap: 8 },
  twoColRow: { flex: 1, flexDirection: 'row', gap: 12, minHeight: 0 },
  bar: { height: 6, borderRadius: 3, marginTop: 4, overflow: 'hidden' },

  tableHead: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    marginTop: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 6,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 6,
  },

  activeDowntime: { marginTop: 12, padding: 14, borderRadius: 12, borderWidth: 1 },
  livePulse: { width: 10, height: 10, borderRadius: 5 },
  activeTitle: { fontSize: 14, fontWeight: '700', marginTop: 8 },

  eventRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 8,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  eventRail: { width: 4, height: 30, borderRadius: 2 },
});
