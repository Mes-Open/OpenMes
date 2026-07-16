/**
 * Issues (admin) — 1:1 with the web admin issues table (Pages/shared/issues/Index.jsx):
 * the shared DataTable with the web's core column set (Issue / Type / Work Order /
 * Reported by / Reported / Status) plus a status filter. Rows open the issue
 * detail, where acknowledge/resolve/close and the disposition/actions modals
 * live, so no inline row actions here. Data via REST useIssues.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { SearchField } from '@openmes/ui/native';

import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useIssues } from '@/hooks/queries/useIssues';
import type { Issue, IssueStatus } from '@/types/api';

const STATUSES: IssueStatus[] = ['OPEN', 'ACKNOWLEDGED', 'RESOLVED', 'CLOSED'];

export function AdminIssuesScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const [status, setStatus] = useState('');
  const [q, setQ] = useState('');

  const filters = useMemo(() => (status ? { status: status as IssueStatus } : {}), [status]);
  const query = useIssues(filters);
  const all = query.data ?? [];

  const rows = useMemo(() => {
    const needle = q.trim().toLowerCase();
    if (!needle) return all;
    return all.filter(
      (i) =>
        (i.description ?? '').toLowerCase().includes(needle) ||
        (i.issue_type?.name ?? '').toLowerCase().includes(needle),
    );
  }, [all, q]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Issues')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 180 }}>
          <Dropdown
            value={status}
            onChange={(v) => setStatus(v as string)}
            placeholder={t('All statuses')}
            options={[{ value: '', label: t('All statuses') }, ...STATUSES.map((s) => ({ value: s, label: s }))]}
          />
        </View>
      </View>

      <View style={styles.filters}>
        <SearchField value={q} onChange={setQ} placeholder={t('Search issues...')} />
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <DataTable<Issue>
            data={rows as Issue[]}
            searchable={false}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            emptyText={t('No issues.')}
            onRowPress={(i) => router.push(`/issues/${i.id}` as never)}
            columns={[
              { key: 'title', label: t('Issue'), flex: 1.6, render: (i) => <Text numberOfLines={1} style={styles.title}>{i.description ?? i.issue_type?.name ?? t('Issue')}</Text> },
              { key: 'type', label: t('Type'), flex: 1, render: (i) => i.issue_type?.name ?? '—' },
              { key: 'wo', label: t('Work Order'), width: 110, render: (i) => <Mono size={11} color={colors.accent}>{i.work_order?.order_no ?? '—'}</Mono> },
              { key: 'reporter', label: t('Reported by'), flex: 1, render: (i) => i.reported_by?.name ?? '—' },
              { key: 'reported', label: t('Reported'), width: 120, render: (i) => <Mono size={10} color={colors.muted}>{i.created_at ? String(i.created_at).slice(0, 16).replace('T', ' ') : '—'}</Mono> },
              { key: 'status', label: t('Status'), width: 108, render: (i) => <StatusPill status={i.status} /> },
            ]}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { paddingHorizontal: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { paddingHorizontal: 18, paddingVertical: 12 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
