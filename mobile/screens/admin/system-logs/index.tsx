import { FontAwesome } from '@expo/vector-icons';
import { LegendList } from '@legendapp/list';
import { useState } from 'react';
import { Alert, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { EmptyState, ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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

/** Admin-only system logs viewer — three tabs, live-tailed app log. */
export function SystemLogsScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [tab, setTab] = useState<Tab>('app');

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader title={t('System logs')} subtitle="ADMIN · OBSERVABILITY" />
      <View style={styles.tabBar}>
        {(['app', 'failed_jobs', 'deployments'] as Tab[]).map((id) => {
          const on = id === tab;
          return (
            <Pressable
              key={id}
              onPress={() => setTab(id)}
              style={[
                styles.tab,
                {
                  backgroundColor: on ? palette.surface : palette.surfaceAlt,
                  borderColor: on ? palette.border : 'transparent',
                },
              ]}>
              <Mono
                size={11}
                weight="700"
                letterSpacing={0.5}
                color={on ? palette.text : palette.textMuted}>
                {id === 'app'
                  ? t('APP LOG')
                  : id === 'failed_jobs'
                    ? t('FAILED JOBS')
                    : t('DEPLOYMENTS')}
              </Mono>
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const [level, setLevel] = useState<LogLevel | undefined>(undefined);
  const [search, setSearch] = useState('');

  const q = useLogTail({ level, search: search.trim() || undefined, limit: 200 });

  if (q.isLoading) return <LoadingState />;
  if (q.isError) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const entries = q.data?.data ?? [];

  return (
    <View style={{ flex: 1 }}>
      <View style={styles.filters}>
        <View style={styles.chipRow}>
          <LevelChip
            label={t('ALL')}
            active={level == null}
            onPress={() => setLevel(undefined)}
            palette={palette}
          />
          {LEVELS.map((l) => (
            <LevelChip
              key={l}
              label={l.toUpperCase()}
              active={level === l}
              onPress={() => setLevel(l)}
              palette={palette}
              accent={LEVEL_COLOR[l] ?? palette.textMuted}
            />
          ))}
        </View>
        <Field
          label="Search"
          value={search}
          onChangeText={setSearch}
          autoCapitalize="none"
          autoCorrect={false}
          placeholder={t('Filter by substring')}
        />
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.5}>
          {t('LIVE TAIL · {{n}} ENTRIES · POLLING 5S').replace('{{n}}', String(entries.length))}
        </Mono>
      </View>

      <LegendList
        data={entries}
        keyExtractor={(e: SystemLogEntry, i: number) => `${e.timestamp}-${i}`}
        contentContainerStyle={styles.list}
        refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}
        ItemSeparatorComponent={() => <View style={{ height: 6 }} />}
        ListEmptyComponent={
          <EmptyState
            title={t('No log entries')}
            subtitle={t('No matching log lines in today’s file.')}
          />
        }
        renderItem={({ item: e }) => (
          <View
            style={[
              styles.logRow,
              {
                backgroundColor: palette.surface,
                borderColor: palette.border,
                borderLeftColor: LEVEL_COLOR[e.level as LogLevel] ?? palette.textFaint,
              },
            ]}>
            <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
              <Mono size={10.5} color={palette.textFaint}>
                {e.timestamp.slice(11, 19)}
              </Mono>
              <View
                style={[
                  styles.levelPill,
                  { backgroundColor: `${LEVEL_COLOR[e.level as LogLevel] ?? palette.textFaint}22` },
                ]}>
                <Mono
                  size={9}
                  weight="700"
                  letterSpacing={0.6}
                  color={LEVEL_COLOR[e.level as LogLevel] ?? palette.textMuted}>
                  {e.level.toUpperCase()}
                </Mono>
              </View>
              <Mono size={10} color={palette.textFaint}>
                {e.environment.toUpperCase()}
              </Mono>
            </View>
            <Text style={[styles.logMessage, { color: palette.text }]} numberOfLines={4}>
              {e.message}
            </Text>
            {e.context ? (
              <Mono size={10.5} color={palette.textMuted} style={{ marginTop: 6 }}>
                {e.context.slice(0, 400)}
              </Mono>
            ) : null}
          </View>
        )}
      />
    </View>
  );
}

function LevelChip({
  label,
  active,
  onPress,
  palette,
  accent,
}: {
  label: string;
  active: boolean;
  onPress: () => void;
  palette: typeof Colors.light;
  accent?: string;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={({ pressed }) => [
        styles.levelChip,
        {
          backgroundColor: active ? palette.text : palette.surface,
          borderColor: active ? palette.text : palette.border,
          opacity: pressed ? 0.85 : 1,
        },
      ]}>
      <Mono
        size={10}
        weight="700"
        letterSpacing={0.6}
        color={active ? palette.background : accent ?? palette.text}>
        {label}
      </Mono>
    </Pressable>
  );
}

const LEVEL_COLOR: Partial<Record<LogLevel, string>> = {
  debug: '#6F6C66',
  info: '#EA5A2B',
  notice: '#EA5A2B',
  warning: '#EA5A2B',
  error: '#D6442F',
  critical: '#D6442F',
  alert: '#D6442F',
  emergency: '#D6442F',
};

// ─── Failed jobs ────────────────────────────────────────────────────────────

function FailedJobsTab() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const q = useFailedJobs({ per_page: 25 });
  const retry = useRetryFailedJob();

  if (q.isLoading) return <LoadingState />;
  if (q.isError) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const jobs = q.data?.data ?? [];
  const missing = q.data?.meta.missing;

  if (missing) {
    return (
      <View style={[styles.banner, { backgroundColor: palette.surfaceAlt }]}>
        <FontAwesome name="info-circle" size={14} color={palette.textMuted} />
        <Mono size={11.5} color={palette.textMuted} style={{ flex: 1 }}>
          {t('The failed_jobs table is not present on this install.')}
        </Mono>
      </View>
    );
  }

  const onRetry = (j: FailedJob) => {
    Alert.alert(t('Retry failed job'), j.uuid.slice(0, 8) + '…', [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Retry'),
        onPress: () =>
          retry.mutate(j.uuid, {
            onError: (e: Error) => Alert.alert(t('Could not retry'), e.message),
          }),
      },
    ]);
  };

  return (
    <LegendList
      data={jobs}
      keyExtractor={(j: FailedJob) => String(j.id)}
      contentContainerStyle={styles.list}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}
      ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
      ListEmptyComponent={<EmptyState title={t('No failed jobs')} />}
      renderItem={({ item: j }) => (
        <Pressable
          onPress={() => onRetry(j)}
          style={({ pressed }) => [
            styles.jobRow,
            {
              backgroundColor: palette.surface,
              borderColor: palette.border,
              opacity: pressed ? 0.85 : 1,
            },
          ]}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Mono size={11} color={palette.text} weight="700">
              {j.queue} · {j.connection}
            </Mono>
            <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 4 }}>
              {j.failed_at}
            </Mono>
            <Text
              numberOfLines={2}
              style={{ color: palette.danger, marginTop: 6, fontSize: 12, fontFamily: MONO }}>
              {firstLine(j.exception)}
            </Text>
          </View>
          <View style={[styles.retryBadge, { borderColor: BRAND.amber }]}>
            <FontAwesome name="refresh" size={12} color={BRAND.amber} />
            <Mono size={10} color={BRAND.amber} weight="700">
              {t('RETRY')}
            </Mono>
          </View>
        </Pressable>
      )}
    />
  );
}

// ─── Deployments ────────────────────────────────────────────────────────────

function DeploymentsTab() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const q = useDeployments({ per_page: 25 });

  if (q.isLoading) return <LoadingState />;
  if (q.isError) return <ErrorState error={q.error} onRetry={q.refetch} />;
  const items = q.data?.data ?? [];
  const missing = q.data?.meta.missing;

  if (missing) {
    return (
      <View style={[styles.banner, { backgroundColor: palette.surfaceAlt }]}>
        <FontAwesome name="info-circle" size={14} color={palette.textMuted} />
        <Mono size={11.5} color={palette.textMuted} style={{ flex: 1 }}>
          {t('The system_updates table will land with updater v0.12+. Deployment history will appear here once installed.')}
        </Mono>
      </View>
    );
  }

  return (
    <LegendList
      data={items}
      keyExtractor={(d: DeploymentRecord) => String(d.id)}
      contentContainerStyle={styles.list}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} />}
      ItemSeparatorComponent={() => <View style={{ height: 8 }} />}
      ListEmptyComponent={<EmptyState title={t('No deployments yet')} />}
      renderItem={({ item: d }) => (
        <View
          style={[
            styles.jobRow,
            {
              backgroundColor: palette.surface,
              borderColor: palette.border,
              borderLeftColor:
                d.status === 'completed'
                  ? palette.success
                  : d.status === 'failed'
                    ? palette.danger
                    : BRAND.amber,
            },
          ]}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Mono size={11} color={palette.text} weight="700">
              {(d.from_version ?? '?')} → {d.to_version ?? '?'}
            </Mono>
            <Mono size={10.5} color={palette.textFaint} style={{ marginTop: 4 }}>
              {d.started_at}
              {d.finished_at ? `  ·  ${t('FINISHED').toUpperCase()} ${d.finished_at}` : ''}
            </Mono>
            {d.error ? (
              <Text
                numberOfLines={2}
                style={{ color: palette.danger, marginTop: 6, fontSize: 12, fontFamily: MONO }}>
                {firstLine(d.error)}
              </Text>
            ) : null}
          </View>
          <Mono size={10} color={palette.textMuted} weight="700" letterSpacing={0.6}>
            {d.status.toUpperCase()}
          </Mono>
        </View>
      )}
    />
  );
}

function firstLine(s: string): string {
  const idx = s.indexOf('\n');
  return idx === -1 ? s : s.slice(0, idx);
}

const styles = StyleSheet.create({
  tabBar: { flexDirection: 'row', gap: 6, paddingHorizontal: 18, paddingTop: 4 },
  tab: {
    flex: 1,
    paddingVertical: 8,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: 'center',
  },
  filters: { paddingHorizontal: 18, paddingTop: 12, gap: 10 },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  levelChip: {
    paddingVertical: 5,
    paddingHorizontal: 9,
    borderRadius: 4,
    borderWidth: 1,
  },
  list: { padding: 18, paddingBottom: 32 },
  logRow: {
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
    borderLeftWidth: 3,
  },
  levelPill: { paddingVertical: 2, paddingHorizontal: 5, borderRadius: 3 },
  logMessage: { fontSize: 13, fontWeight: '600', marginTop: 6 },
  jobRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderLeftWidth: 3,
  },
  retryBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 8,
    borderWidth: 1,
  },
  banner: {
    margin: 18,
    padding: 14,
    borderRadius: 10,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
  },
});
