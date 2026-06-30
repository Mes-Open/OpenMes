import { FontAwesome } from '@expo/vector-icons';
import { formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useIssues } from '@/hooks/queries/useIssues';
import { useLines } from '@/hooks/queries/useUsers';
import { useOee } from '@/hooks/queries/useOee';
import { useWorkOrders } from '@/hooks/queries/useWorkOrders';
import type { Issue, Line, WorkOrder } from '@/types/api';

interface LineSummary {
  line: Line;
  activeWo: WorkOrder | null;
  pct: number;
  status: 'running' | 'paused' | 'blocked' | 'idle';
}

function statusFromWo(wo: WorkOrder | null): LineSummary['status'] {
  if (!wo) return 'idle';
  if (wo.status === 'IN_PROGRESS') return 'running';
  if (wo.status === 'BLOCKED') return 'blocked';
  if (wo.status === 'PAUSED') return 'paused';
  return 'idle';
}

export function TabletSupervisorWarRoom() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const linesQ = useLines();
  const workOrdersQ = useWorkOrders({ status: ['IN_PROGRESS', 'PAUSED', 'BLOCKED', 'ACCEPTED'] });
  const issuesQ = useIssues({ status: 'OPEN' });
  const oeeQ = useOee({});

  const lines: Line[] = linesQ.data ?? [];
  const [selectedLineId, setSelectedLineId] = useState<number | null>(null);
  const selectedId = selectedLineId ?? lines[0]?.id ?? null;

  const summaries: LineSummary[] = useMemo(() => {
    const wos = workOrdersQ.data ?? [];
    return lines.map((line) => {
      const lineWos = wos.filter((w) => w.line_id === line.id);
      const active =
        lineWos.find((w) => w.status === 'IN_PROGRESS') ??
        lineWos.find((w) => w.status === 'BLOCKED' || w.status === 'PAUSED') ??
        lineWos[0] ??
        null;
      const planned = Number(active?.planned_qty ?? 0);
      const produced = Number(active?.produced_qty ?? 0);
      const pct = planned > 0 ? Math.min(100, Math.round((produced / planned) * 100)) : 0;
      return { line, activeWo: active, pct, status: statusFromWo(active) };
    });
  }, [lines, workOrdersQ.data]);

  const selectedSummary = summaries.find((s) => s.line.id === selectedId) ?? summaries[0];

  // Plant OEE — average of latest OEE per line.
  const plantOee = useMemo(() => {
    const records = oeeQ.data ?? [];
    if (records.length === 0) return null;
    const vals = records
      .map((r) => (typeof r.oee_pct === 'string' ? parseFloat(r.oee_pct) : r.oee_pct ?? 0))
      .filter((v) => v > 0);
    if (vals.length === 0) return null;
    return vals.reduce((a, b) => a + b, 0) / vals.length;
  }, [oeeQ.data]);

  const issues: Issue[] = issuesQ.data ?? [];

  // Issue counts for summary.
  const operatorCount = lines.length; // rough proxy — each line has 1+ operator
  const issuesCount = issues.length;

  return (
    <TabletShell
      eyebrow={`${t('SUPERVISOR').toUpperCase()} · ${t('WAR ROOM').toUpperCase()}`}
      title={t('Floor')}
      right={
        <>
          {plantOee != null ? (
            <View style={[styles.oeeBlock, { backgroundColor: palette.surfaceInverse }]}>
              <Mono size={11} color="#6F6C66" letterSpacing={0.6}>OEE</Mono>
              <Mono size={22} color="#fff" weight="600" letterSpacing={-0.3}>
                {plantOee.toFixed(0)}
                <Text style={{ color: '#6F6C66', fontSize: 12 }}>%</Text>
              </Mono>
            </View>
          ) : null}
          <Mono size={11} color={palette.textFaint}>
            {lines.length} {t('LINES').toUpperCase()} · {operatorCount} {t('OPERATORS').toUpperCase()} · {issuesCount} {t('ISSUES').toUpperCase()}
          </Mono>
          <Pressable
            onPress={() => router.push('/(drawer)/orders/work-orders' as never)}
            style={({ pressed }) => [
              styles.newBtn,
              { backgroundColor: palette.surfaceInverse, opacity: pressed ? 0.85 : 1 },
            ]}>
            <FontAwesome name="plus" size={14} color={scheme === 'dark' ? '#1A1917' : '#fff'} />
            <Mono
              size={12}
              color={scheme === 'dark' ? '#1A1917' : '#fff'}
              weight="600"
              letterSpacing={0.4}>
              {t('NEW WORK ORDER').toUpperCase()}
            </Mono>
          </Pressable>
        </>
      }>
      <View style={styles.grid3}>
        {/* LEFT — Lines list */}
        <View style={[styles.panel, { borderColor: palette.border, backgroundColor: palette.surface }]}>
          <View style={[styles.panelHeader, { borderBottomColor: palette.border }]}>
            <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>{t('PRODUCTION LINES').toUpperCase()}</Mono>
            <Text style={[styles.panelTitle, { color: palette.text }]}>
              {summaries.filter((s) => s.status === 'running').length} {t('active')}
            </Text>
          </View>
          <ScrollView contentContainerStyle={{ padding: 12, gap: 10 }}>
            {summaries.map((s) => (
              <LineRow
                key={s.line.id}
                summary={s}
                selected={s.line.id === selectedId}
                onSelect={() => setSelectedLineId(s.line.id)}
              />
            ))}
            {summaries.length === 0 ? (
              <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 16 }}>
                {t('No lines configured').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
        </View>

        {/* CENTER — Selected line detail */}
        <View style={styles.centerCol}>
          {selectedSummary ? (
            <SelectedLineCard summary={selectedSummary} />
          ) : (
            <View
              style={[
                styles.panel,
                { borderColor: palette.border, backgroundColor: palette.surface, padding: 24 },
              ]}>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.6} style={{ textAlign: 'center' }}>
                {t('Select a line to view details').toUpperCase()}
              </Mono>
            </View>
          )}
          <CycleChartCard />
        </View>

        {/* RIGHT — Live alerts */}
        <View style={[styles.panel, { borderColor: palette.border, backgroundColor: palette.surface }]}>
          <View style={[styles.panelHeader, { borderBottomColor: palette.border }]}>
            <View style={{ flex: 1 }}>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>{t('LIVE FEED').toUpperCase()}</Mono>
              <Text style={[styles.panelTitle, { color: palette.text }]}>{t('Alerts')}</Text>
            </View>
            <View style={styles.livePill}>
              <View style={[styles.liveDot, { backgroundColor: palette.success }]} />
              <Mono size={10} color={palette.success} weight="600" letterSpacing={0.4}>{t('LIVE').toUpperCase()}</Mono>
            </View>
          </View>
          <ScrollView contentContainerStyle={{ padding: 12, gap: 8 }}>
            {issues.length === 0 ? (
              <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 18 }}>
                {t('No open issues').toUpperCase()}
              </Mono>
            ) : (
              issues.map((i) => <AlertCard key={i.id} issue={i} onOpen={() => router.push(`/issues/${i.id}` as never)} />)
            )}
          </ScrollView>
        </View>
      </View>
    </TabletShell>
  );
}

function LineRow({
  summary,
  selected,
  onSelect,
}: {
  summary: LineSummary;
  selected: boolean;
  onSelect: () => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const status = summary.status;
  const barColor =
    status === 'blocked' ? palette.danger : status === 'paused' ? BRAND.amber : palette.success;

  return (
    <Pressable
      onPress={onSelect}
      style={({ pressed }) => [
        styles.lineRow,
        {
          backgroundColor: selected ? '#241a08' : palette.surfaceAlt,
          borderColor: selected ? BRAND.amber : 'transparent',
          opacity: pressed ? 0.85 : 1,
        },
      ]}>
      <View style={styles.lineHead}>
        <View
          style={[
            styles.lineBadge,
            {
              backgroundColor: selected ? BRAND.amber : palette.text,
            },
          ]}>
          <Mono
            size={11}
            color={selected ? '#1a1208' : '#fff'}
            weight="700"
            letterSpacing={0.3}>
            {summary.line.code ?? `L-${String(summary.line.id).padStart(2, '0')}`}
          </Mono>
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text
            style={[
              styles.lineName,
              { color: selected ? '#1A1917' : palette.text },
            ]}
            numberOfLines={1}>
            {summary.line.name}
          </Text>
          <Mono
            size={11}
            color={selected ? '#6F6C66' : palette.textMuted}
            style={{ marginTop: 2 }}>
            {summary.activeWo?.order_no ?? '—'}
          </Mono>
        </View>
        <StatusPill status={summary.activeWo?.status ?? 'PENDING'} dark={selected} />
      </View>
      <View style={styles.linePctRow}>
        <View style={[styles.lineBar, { backgroundColor: selected ? '#F6F5F1' : palette.surface }]}>
          <View
            style={{
              width: `${summary.pct}%`,
              height: '100%',
              backgroundColor: barColor,
            }}
          />
        </View>
        <Mono size={11} color={selected ? '#1A1917' : palette.text} weight="600">
          {summary.pct}%
        </Mono>
      </View>
    </Pressable>
  );
}

function SelectedLineCard({ summary }: { summary: LineSummary }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const wo = summary.activeWo;
  const planned = Number(wo?.planned_qty ?? 0);
  const produced = Number(wo?.produced_qty ?? 0);
  const pct = planned > 0 ? Math.min(100, Math.round((produced / planned) * 100)) : 0;

  return (
    <View
      style={[
        styles.panel,
        { borderColor: palette.border, backgroundColor: palette.surface, padding: 18 },
      ]}>
      <View style={styles.detailHead}>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
            {(summary.line.code ?? '').toUpperCase()} · {summary.line.name.toUpperCase()}
          </Mono>
          <Text style={[styles.detailWo, { color: palette.text }]} numberOfLines={1}>
            {wo?.order_no ?? '—'}
          </Text>
          <Mono size={12} color={palette.textMuted} style={{ marginTop: 4 }}>
            {planned} {t('PCS').toUpperCase()}{wo?.due_date ? ` · ${t('DUE').toUpperCase()} ${wo.due_date.slice(5)}` : ''}
          </Mono>
        </View>
        {wo ? <StatusPill status={wo.status} /> : null}
      </View>

      <View style={{ marginTop: 18 }}>
        <View style={styles.progressHead}>
          <Mono size={11} color={palette.textMuted}>
            {wo?.product_type?.name ? wo.product_type.name.toUpperCase() : t('PROGRESS').toUpperCase()}
          </Mono>
          <Mono size={11} color={palette.textMuted}>{pct}% {t('complete')}</Mono>
        </View>
        <View style={[styles.progressTrack, { backgroundColor: palette.surfaceAlt }]}>
          <View
            style={{
              width: `${pct}%`,
              height: '100%',
              backgroundColor:
                summary.status === 'blocked'
                  ? palette.danger
                  : summary.status === 'paused'
                  ? BRAND.amber
                  : palette.success,
              borderRadius: 3,
            }}
          />
        </View>
      </View>

      <View style={styles.statGrid}>
        <DetailStat label={t('Produced').toUpperCase()} value={String(produced)} />
        <DetailStat
          label={t('Remaining').toUpperCase()}
          value={String(Math.max(0, planned - produced))}
        />
        <DetailStat
          label={t('Status').toUpperCase()}
          value={t(summary.status).toUpperCase()}
          color={
            summary.status === 'blocked'
              ? palette.danger
              : summary.status === 'paused'
              ? BRAND.amber
              : palette.success
          }
        />
        <DetailStat label={t('DUE').toUpperCase()} value={wo?.due_date?.slice(5, 10) ?? '—'} />
      </View>
    </View>
  );
}

function DetailStat({ label, value, color }: { label: string; value: string; color?: string }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View style={[styles.detailStat, { backgroundColor: palette.surfaceAlt }]}>
      <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>{label}</Mono>
      <Mono
        size={22}
        color={color ?? palette.text}
        weight="600"
        letterSpacing={-0.3}
        style={{ marginTop: 2 }}>
        {value}
      </Mono>
    </View>
  );
}

function CycleChartCard() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  // No backend endpoint for per-step cycle history yet — surface a placeholder.
  // TODO(api/cycle-time): swap for real data when the analytics endpoint lands.
  const data = [180, 165, 175, 168, 158, 172, 162, 155, 160, 154, 175, 168, 152, 144, 158, 168, 172, 155, 148, 162];
  const min = Math.min(...data);
  const max = Math.max(...data);
  const span = Math.max(1, max - min);
  return (
    <View
      style={[
        styles.panel,
        { borderColor: palette.border, backgroundColor: palette.surface, padding: 18, flex: 1 },
      ]}>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
        <View>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
            {t('Cycle time').toUpperCase()} · {t('LAST 30 MIN').toUpperCase()}
          </Mono>
          <Mono size={22} color={palette.text} weight="600" style={{ marginTop: 4 }}>
            02:54
            <Text style={{ fontSize: 13, color: palette.textFaint }}>{' '}{t('avg')}</Text>
          </Mono>
        </View>
        <View style={[styles.trendPill, { backgroundColor: '#E6F4EA' }]}>
          <FontAwesome name="line-chart" size={11} color="#1C9A55" />
          <Mono size={11} color="#1C9A55">9% {t('under target')}</Mono>
        </View>
      </View>
      <View style={{ flex: 1, marginTop: 14, flexDirection: 'row', alignItems: 'flex-end', gap: 4 }}>
        {data.map((v, i) => {
          const h = ((v - min) / span) * 100;
          return (
            <View
              key={i}
              style={{
                flex: 1,
                height: `${20 + h * 0.6}%`,
                backgroundColor: palette.success,
                borderRadius: 2,
              }}
            />
          );
        })}
      </View>
    </View>
  );
}

function AlertCard({ issue, onOpen }: { issue: Issue; onOpen: () => void }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const blocking = !!issue.issue_type?.is_blocking;
  const railColor = blocking ? palette.danger : BRAND.amber;
  const ago = (() => {
    try {
      return issue.created_at
        ? formatDistanceToNowStrict(parseISO(issue.created_at))
        : '';
    } catch {
      return '';
    }
  })();

  return (
    <Pressable
      onPress={onOpen}
      style={({ pressed }) => [
        styles.alertCard,
        {
          backgroundColor: blocking ? palette.dangerSoft : palette.surfaceAlt,
          borderColor: blocking ? palette.danger : 'transparent',
          opacity: pressed ? 0.92 : 1,
        },
      ]}>
      <View style={[styles.alertRail, { backgroundColor: railColor }]} />
      <View style={{ flex: 1, padding: 12 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
          <Mono size={10} color={railColor} weight="700" letterSpacing={0.6}>
            {(blocking ? t('Block') : t('Major')).toUpperCase()} · {(issue.line?.name ?? '—').toUpperCase()}
          </Mono>
          {ago ? (
            <Mono size={11} color={palette.textFaint}>{ago}</Mono>
          ) : null}
        </View>
        <Text style={[styles.alertDesc, { color: palette.text }]} numberOfLines={2}>
          {issue.description ?? issue.issue_type?.name ?? t('Issue')}
        </Text>
        {issue.acknowledged_at ? (
          <Mono size={9.5} color={palette.info} letterSpacing={0.4} style={{ marginTop: 6 }}>
            ● {t('Acknowledged').toUpperCase()}
          </Mono>
        ) : null}
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  oeeBlock: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 10,
  },
  newBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 10,
  },
  grid3: { flex: 1, flexDirection: 'row', gap: 16, minHeight: 0 },
  panel: { flex: 1, borderRadius: 16, borderWidth: 1, overflow: 'hidden' },
  panelHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 14,
    paddingHorizontal: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  panelTitle: { fontSize: 15, fontWeight: '600', marginTop: 4 },
  centerCol: { flex: 1.4, gap: 12 },

  lineRow: { padding: 12, borderRadius: 12, borderWidth: 1 },
  lineHead: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  lineBadge: {
    width: 36,
    height: 36,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  lineName: { fontSize: 14, fontWeight: '600' },
  linePctRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 10 },
  lineBar: { flex: 1, height: 4, borderRadius: 2, overflow: 'hidden' },

  detailHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  detailWo: { fontSize: 24, fontWeight: '600', letterSpacing: -0.3, marginTop: 6 },
  progressHead: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: 8 },
  progressTrack: { height: 8, borderRadius: 3, overflow: 'hidden' },
  statGrid: {
    flexDirection: 'row',
    gap: 10,
    marginTop: 18,
  },
  detailStat: { flex: 1, padding: 12, borderRadius: 10 },

  livePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
    backgroundColor: '#E6F4EA',
  },
  liveDot: { width: 6, height: 6, borderRadius: 3 },
  trendPill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
  },

  alertCard: {
    flexDirection: 'row',
    borderRadius: 12,
    borderWidth: 1,
    overflow: 'hidden',
  },
  alertRail: { width: 4 },
  alertDesc: { fontSize: 13, marginTop: 6, lineHeight: 18 },
});
