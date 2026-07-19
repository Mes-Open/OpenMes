/**
 * Activity logs — mirrors the web admin logs/Activity page (Pages/admin/logs/
 * Activity.jsx) via the shared DataTable. The mobile feed is the audit stream
 * only (no request/navigation source), so the web's combined "What" + "Details"
 * columns are adapted to When / Who / Entity / Action. Keeps the action filter
 * beside the DataTable search. Read via useAuditLogs.
 */
import { format, parseISO } from 'date-fns';
import { useMemo, useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAuditLogs } from '@/hooks/queries/useAuditLogs';
import type { AuditAction, AuditLog } from '@/api/auditLogs';

const ACTIONS: AuditAction[] = ['created', 'updated', 'deleted'];
const humanize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const ACTION_TONE: Record<string, { fg: string; bg: string }> = {
  created: { fg: colors.running, bg: colors.runningBg },
  updated: { fg: colors.accent, bg: colors.chip },
  deleted: { fg: colors.blocked, bg: colors.blockedBg },
};

function ts(iso: string, pattern: string): string {
  try {
    return format(parseISO(iso), pattern);
  } catch {
    return '—';
  }
}

export function ActivityLogsScreen() {
  const { t } = useTranslation();
  const [action, setAction] = useState<AuditAction | ''>('');

  const query = useAuditLogs({ per_page: 100 });
  const all: AuditLog[] = query.data?.data ?? [];
  const rows = useMemo(() => (action ? all.filter((l) => l.action === action) : all), [all, action]);

  const options = useMemo(
    () => [{ value: '', label: t('All actions') }, ...ACTIONS.map((a) => ({ value: a, label: humanize(a) }))],
    [t],
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Activity Logs')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 170 }}>
          <Dropdown value={action} onChange={(v) => setAction(v as AuditAction | '')} placeholder={t('All actions')} options={options} />
        </View>
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <DataTable<AuditLog>
            data={rows}
            searchPlaceholder={t('Search activity…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['entity_type', 'entity_name']}
            emptyText={t('No activity in this period.')}
            columns={[
              { key: 'when', label: t('When'), width: 84, render: (log) => <Mono size={10} color={colors.faint}>{ts(log.created_at, 'HH:mm:ss')}</Mono> },
              { key: 'who', label: t('Who'), width: 96, render: (log) => log.user?.username ?? 'system' },
              {
                key: 'entity',
                label: t('Entity'),
                flex: 1,
                render: (log) => (
                  <View>
                    <Mono size={11} color={colors.ink} numberOfLines={1}>{`${log.entity_type}#${log.entity_id}`}</Mono>
                    {log.entity_name ? <Text numberOfLines={1} style={styles.sub}>{log.entity_name}</Text> : null}
                  </View>
                ),
              },
              {
                key: 'action',
                label: t('Action'),
                width: 96,
                render: (log) => {
                  const tone = ACTION_TONE[log.action] ?? { fg: colors.muted, bg: colors.chip };
                  return (
                    <View style={[styles.pill, { backgroundColor: tone.bg }]}>
                      <Mono size={9} color={tone.fg} letterSpacing={0.5}>{log.action.toUpperCase()}</Mono>
                    </View>
                  );
                },
              },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  sub: { fontSize: 11, color: colors.faint, fontFamily: fonts.sans.native.regular, marginTop: 2 },
  pill: { alignSelf: 'flex-start', paddingVertical: 2, paddingHorizontal: 6, borderRadius: 3 },
});
