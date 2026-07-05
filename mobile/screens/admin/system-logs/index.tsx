/**
 * System logs — mirrors the web admin logs/System screen: three tabs
 * (Application log / Failed jobs / Deployments). App log is level-filterable and
 * live-tailed; failed jobs can be retried by tapping a row. Data + mutations via
 * useSystemLogs.
 */
import { useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';
import { SearchField } from '@openmes/ui/native';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeployments,
  useFailedJobs,
  useLogTail,
  useRetryFailedJob,
} from '@/hooks/queries/useSystemLogs';
import type {
  DeploymentRecord,
  FailedJob,
  LogLevel,
  SystemLogEntry,
} from '@/api/systemLogs';

type Tab = 'app' | 'failed_jobs' | 'deployments';

const LEVELS: LogLevel[] = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];
const humanize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const LEVEL_TONE: Record<string, { fg: string; bg: string }> = {
  debug: { fg: colors.muted, bg: colors.chip },
  info: { fg: colors.accent, bg: colors.chip },
  notice: { fg: colors.accent, bg: colors.chip },
  warning: { fg: colors.downtime, bg: colors.downtimeBg },
  error: { fg: colors.blocked, bg: colors.blockedBg },
  critical: { fg: colors.blocked, bg: colors.blockedBg },
  alert: { fg: colors.blocked, bg: colors.blockedBg },
  emergency: { fg: colors.blocked, bg: colors.blockedBg },
};
const levelTone = (l: string) => LEVEL_TONE[l] ?? { fg: colors.faint, bg: colors.chip };

export function SystemLogsScreen() {
  const { t } = useTranslation();
  const [tab, setTab] = useState<Tab>('app');

  const TABS: { id: Tab; label: string }[] = [
    { id: 'app', label: t('Application log') },
    { id: 'failed_jobs', label: t('Failed jobs') },
    { id: 'deployments', label: t('Deployments') },
  ];

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('System Logs')}</Text>
      </View>

      <View style={styles.tabBar}>
        {TABS.map((tb) => {
          const on = tb.id === tab;
          return (
            <Pressable key={tb.id} onPress={() => setTab(tb.id)} style={[styles.tab, on && styles.tabActive]}>
              <Mono size={11} color={on ? colors.accent : colors.muted} letterSpacing={0.4}>{tb.label.toUpperCase()}</Mono>
            </Pressable>
          );
        })}
      </View>

      {tab === 'app' ? <AppLogTab /> : tab === 'failed_jobs' ? <FailedJobsTab /> : <DeploymentsTab />}
    </View>
  );
}

// ─── App log ────────────────────────────────────────────────────────────────

function AppLogTab() {
  const { t } = useTranslation();
  const [level, setLevel] = useState<LogLevel | undefined>(undefined);
  const [search, setSearch] = useState('');

  const q = useLogTail({ level, search: search.trim() || undefined, limit: 200 });
  const entries = q.data?.data ?? [];

  return (
    <View style={{ flex: 1 }}>
      <View style={styles.filters}>
        <View style={{ flexDirection: 'row', gap: 10 }}>
          <View style={{ width: 150 }}>
            <Dropdown
              value={level ?? ''}
              onChange={(v) => setLevel((v as LogLevel) || undefined)}
              placeholder={t('All levels')}
              options={[{ value: '', label: t('All levels') }, ...LEVELS.map((l) => ({ value: l, label: humanize(l) }))]}
            />
          </View>
          <View style={{ flex: 1 }}>
            <SearchField value={search} onChange={setSearch} placeholder={t('Search message or stack trace')} />
          </View>
        </View>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>
          {t('Live tail · {{n}} entries · polling 5s').replace('{{n}}', String(entries.length))}
        </Mono>
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          contentContainerStyle={styles.list}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          {entries.map((e: SystemLogEntry, i: number) => {
            const tone = levelTone(e.level);
            return (
              <View key={`${e.timestamp}-${i}`} style={[styles.logRow, { borderLeftColor: tone.fg }]}>
                <View style={styles.logHead}>
                  <Mono size={10} color={colors.faint}>{e.timestamp.slice(11, 19)}</Mono>
                  <View style={[styles.pill, { backgroundColor: tone.bg }]}>
                    <Mono size={9} color={tone.fg} letterSpacing={0.5}>{e.level.toUpperCase()}</Mono>
                  </View>
                  <Mono size={9} color={colors.faint}>{e.environment.toUpperCase()}</Mono>
                </View>
                <Text style={styles.logMessage} numberOfLines={4}>{e.message}</Text>
                {e.context ? <Mono size={10} color={colors.muted} style={{ marginTop: 6 }}>{e.context.slice(0, 400)}</Mono> : null}
              </View>
            );
          })}
          {entries.length === 0 ? <Text style={styles.empty}>{t('No log entries match your filters.')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

// ─── Failed jobs ────────────────────────────────────────────────────────────

function FailedJobsTab() {
  const { t } = useTranslation();
  const q = useFailedJobs({ per_page: 25 });
  const retry = useRetryFailedJob();

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  const jobs = q.data?.data ?? [];
  if (q.data?.meta.missing) {
    return <Notice text={t('Failed jobs table is missing.')} />;
  }

  const onRetry = (j: FailedJob) =>
    Alert.alert(t('Retry failed job'), j.uuid.slice(0, 8) + '…', [
      { text: t('Cancel'), style: 'cancel' },
      { text: t('Retry'), onPress: () => retry.mutate(j.uuid, { onError: (e: Error) => Alert.alert(t('Could not retry'), e.message) }) },
    ]);

  return (
    <ScrollView
      contentContainerStyle={styles.list}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      {jobs.map((j) => (
        <Pressable key={j.id} onPress={() => onRetry(j)} style={({ pressed }) => [styles.jobRow, { opacity: pressed ? 0.6 : 1 }]}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Mono size={11} color={colors.ink}>{`${j.queue} · ${j.connection}`}</Mono>
            <Mono size={10} color={colors.faint} style={{ marginTop: 4 }}>{j.failed_at}</Mono>
            <Text numberOfLines={2} style={styles.exception}>{firstLine(j.exception)}</Text>
          </View>
          <View style={[styles.pill, { backgroundColor: colors.chip }]}>
            <Mono size={9} color={colors.accent} letterSpacing={0.5}>{t('Retry').toUpperCase()}</Mono>
          </View>
        </Pressable>
      ))}
      {jobs.length === 0 ? <Text style={styles.empty}>{t('No failed jobs.')}</Text> : null}
    </ScrollView>
  );
}

// ─── Deployments ────────────────────────────────────────────────────────────

function DeploymentsTab() {
  const { t } = useTranslation();
  const q = useDeployments({ per_page: 25 });

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  const items = q.data?.data ?? [];
  if (q.data?.meta.missing) {
    return <Notice text={t('Deployment audit log is not available on this build.')} />;
  }

  const stateColor = (s: string) =>
    s === 'completed' ? colors.running : s === 'failed' || s === 'rolled_back' ? colors.blocked : colors.downtime;

  return (
    <ScrollView
      contentContainerStyle={styles.list}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      {items.map((d: DeploymentRecord) => (
        <View key={d.id} style={[styles.jobRow, { borderLeftWidth: 3, borderLeftColor: stateColor(d.status) }]}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Mono size={11} color={colors.ink}>{`${d.from_version ?? '?'} → ${d.to_version ?? '?'}`}</Mono>
            <Mono size={10} color={colors.faint} style={{ marginTop: 4 }}>
              {d.started_at}{d.finished_at ? ` · ${t('finished')} ${d.finished_at}` : ''}
            </Mono>
            {d.error ? <Text numberOfLines={2} style={styles.exception}>{firstLine(d.error)}</Text> : null}
          </View>
          <Mono size={9} color={colors.muted} letterSpacing={0.5}>{d.status.toUpperCase()}</Mono>
        </View>
      ))}
      {items.length === 0 ? <Text style={styles.empty}>{t('No deployments recorded.')}</Text> : null}
    </ScrollView>
  );
}

function Notice({ text }: { text: string }) {
  return (
    <View style={styles.list}>
      <View style={styles.notice}>
        <Mono size={11} color={colors.muted}>{text}</Mono>
      </View>
    </View>
  );
}

function firstLine(s: string): string {
  const idx = s.indexOf('\n');
  return idx === -1 ? s : s.slice(0, idx);
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16, paddingBottom: 8 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tabBar: { flexDirection: 'row', gap: 4, paddingHorizontal: 18, borderBottomWidth: 1, borderBottomColor: colors.line },
  tab: { paddingVertical: 10, paddingHorizontal: 6, marginRight: 8, borderBottomWidth: 2, borderBottomColor: 'transparent' },
  tabActive: { borderBottomColor: colors.accent },
  filters: { paddingHorizontal: 18, paddingVertical: 12, gap: 10 },
  list: { paddingHorizontal: 18, paddingBottom: 24, gap: 8 },
  logRow: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderLeftWidth: 3,
    borderRadius: radius.md,
    padding: 12,
  },
  logHead: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  logMessage: { fontSize: 13, color: colors.ink, fontFamily: fonts.sans.native.medium, marginTop: 6 },
  jobRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 14,
  },
  exception: { fontSize: 12, color: colors.blocked, fontFamily: fonts.mono.native.regular, marginTop: 6 },
  pill: { alignSelf: 'flex-start', paddingVertical: 2, paddingHorizontal: 6, borderRadius: 3 },
  notice: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
