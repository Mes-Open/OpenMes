/**
 * Worker absences — 1:1 with the web admin worker-absences page (Pages/admin/
 * worker-absences/Index.jsx): the shared DataTable (search + Columns menu +
 * pagination) with the web's column set (Worker / Type / Dates / Span /
 * Status) and the Delete row action. No update endpoint exists yet (mobile
 * and web both only expose create + delete for absences), so there is no
 * Edit action.
 */
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { format, isValid, parseISO } from 'date-fns';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useDeleteWorkerAbsence, useWorkerAbsences } from '@/hooks/queries/useWorkerAbsences';
import type { AbsenceType, WorkerAbsence } from '@/api/workerAbsences';

const TYPE_COLOR: Record<AbsenceType, string> = {
  vacation: colors.accent,
  sick: colors.blocked,
  personal: colors.downtime,
  training: colors.running,
  other: colors.faint,
};

const TYPE_LABEL: Record<AbsenceType, string> = {
  vacation: 'Vacation',
  sick: 'Sick',
  personal: 'Personal',
  training: 'Training',
  other: 'Other',
};

const STATUS_LABEL: Record<WorkerAbsence['status'], string> = {
  approved: 'Approved',
  pending: 'Pending',
  rejected: 'Rejected',
};

function fmtDate(d: string | null | undefined): string {
  if (!d) return '—';
  const parsed = parseISO(d);
  return isValid(parsed) ? format(parsed, 'MMM d') : '—';
}

export default function WorkerAbsencesPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useWorkerAbsences();
  const del = useDeleteWorkerAbsence();
  const rows = q.data ?? [];

  const onDelete = (a: WorkerAbsence) =>
    Alert.alert(t('Delete absence'), t('Delete this absence?'), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(a.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  if (q.isLoading && !q.data) return <LoadingState />;
  if (q.isError && !q.data) return <ErrorState error={q.error} onRetry={q.refetch} />;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Absences')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Absence')} size="sm" onPress={() => router.push('/hr/absences/new' as never)} />
      </View>

      <DataTable<WorkerAbsence>
        data={rows}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['worker_name', 'type', 'status']}
        emptyText={t('No absences recorded yet.')}
        columns={[
          {
            key: 'worker',
            label: t('Worker'),
            flex: 1.4,
            render: (a) => (
              <Text numberOfLines={1} style={styles.name}>
                {a.worker_name ?? t('Worker #{{id}}', { id: a.worker_id })}
              </Text>
            ),
          },
          {
            key: 'type',
            label: t('Type'),
            width: 100,
            render: (a) => <Mono size={11} color={TYPE_COLOR[a.type] ?? colors.muted}>{t(TYPE_LABEL[a.type] ?? a.type)}</Mono>,
          },
          {
            key: 'range',
            label: t('Dates'),
            flex: 1,
            render: (a) => (
              <Mono size={11} color={colors.muted}>
                {a.starts_on === a.ends_on ? fmtDate(a.starts_on) : `${fmtDate(a.starts_on)} → ${fmtDate(a.ends_on)}`}
              </Mono>
            ),
          },
          {
            key: 'span',
            label: t('Span'),
            width: 90,
            render: (a) => <Mono size={11} color={colors.muted}>{a.all_day ? t('All day') : '—'}</Mono>,
          },
          {
            key: 'status',
            label: t('Status'),
            width: 90,
            render: (a) => (
              <Mono size={11} color={a.status === 'rejected' ? colors.blocked : colors.muted}>
                {t(STATUS_LABEL[a.status] ?? a.status)}
              </Mono>
            ),
          },
        ]}
        actions={(a) => [{ label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(a) }]}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 14 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
});
