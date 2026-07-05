/**
 * Audit logs — mirrors the web admin AuditLogs page (Pages/admin/AuditLogs.jsx)
 * via the shared DataTable: the web's column set (Timestamp / User / Entity /
 * Action). The web's Details (before/after diff) column is omitted — the mobile
 * feed doesn't carry state snapshots. The entity-type filter, action filter and
 * CSV export stay as toolbar controls beside the DataTable search. Immutable
 * change feed, read via useAuditLogs.
 */
import { format, parseISO } from 'date-fns';
import { useState } from 'react';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import * as WebBrowser from 'expo-web-browser';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { SearchField } from '@openmes/ui/native';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useAuditLogs } from '@/hooks/queries/useAuditLogs';
import { auditLogsExportUrl, type AuditAction, type AuditLog } from '@/api/auditLogs';

const ACTIONS: AuditAction[] = ['created', 'updated', 'deleted'];
const humanize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

const ACTION_TONE: Record<string, { fg: string; bg: string }> = {
  created: { fg: colors.running, bg: colors.runningBg },
  updated: { fg: colors.accent, bg: colors.chip },
  deleted: { fg: colors.blocked, bg: colors.blockedBg },
};

export function AuditLogsScreen() {
  const { t } = useTranslation();
  const [action, setAction] = useState<AuditAction | ''>('');
  const [entityType, setEntityType] = useState('');

  const query = useAuditLogs({
    action: action || undefined,
    entity_type: entityType.trim() || undefined,
    per_page: 50,
  });
  const rows: AuditLog[] = query.data?.data ?? [];

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Audit Logs')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 160 }}>
          <Dropdown
            value={action}
            onChange={(v) => setAction(v as AuditAction | '')}
            placeholder={t('All actions')}
            options={[{ value: '', label: t('All actions') }, ...ACTIONS.map((a) => ({ value: a, label: humanize(a) }))]}
          />
        </View>
        <Button
          title={t('Export CSV')}
          variant="outline"
          size="sm"
          onPress={() =>
            WebBrowser.openBrowserAsync(
              auditLogsExportUrl({ action: action || undefined, entity_type: entityType.trim() || undefined }),
            )
          }
        />
      </View>

      <View style={styles.filters}>
        <SearchField value={entityType} onChange={setEntityType} placeholder={t('Filter by entity type')} />
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
            searchPlaceholder={t('Search audit logs…')}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            searchKeys={['entity_type', 'entity_name']}
            emptyText={t('No audit logs found')}
            columns={[
              {
                key: 'timestamp',
                label: t('Timestamp'),
                width: 140,
                render: (log) => {
                  try {
                    return <Mono size={10} color={colors.faint}>{format(parseISO(log.created_at), 'yyyy-MM-dd HH:mm:ss')}</Mono>;
                  } catch {
                    return '—';
                  }
                },
              },
              { key: 'user', label: t('User'), width: 100, render: (log) => log.user?.username ?? 'system' },
              {
                key: 'entity',
                label: t('Entity'),
                flex: 1,
                render: (log) => (
                  <View>
                    <Text numberOfLines={1} style={styles.name}>{`${log.entity_name ?? log.entity_type} #${log.entity_id}`}</Text>
                    {log.ip_address ? <Mono size={9} color={colors.faint} style={{ marginTop: 2 }}>{log.ip_address}</Mono> : null}
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
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { paddingHorizontal: 18, paddingVertical: 12 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  pill: { alignSelf: 'flex-start', paddingVertical: 2, paddingHorizontal: 6, borderRadius: 3 },
});
