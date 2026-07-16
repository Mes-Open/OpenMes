/**
 * Crew break windows — 1:1 with the web admin crew-break-windows page
 * (Pages/admin/crew-break-windows/Index.jsx): the shared DataTable (search +
 * Columns menu + pagination) with the web's column set (Crew / Name / Time /
 * Days / Status) and the Delete row action. No update endpoint exists yet
 * (mobile and web both only expose create + delete for break windows), so
 * there is no Edit action.
 */
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCrewBreakWindows, useDeleteCrewBreakWindow } from '@/hooks/queries/useCrewBreakWindows';
import type { CrewBreakWindow } from '@/api/crewBreakWindows';

const DAY_LETTERS = ['', 'M', 'T', 'W', 'T', 'F', 'S', 'S']; // 1=Mon..7=Sun (ISO)

function daysLabel(days: number[]): string {
  const sorted = [...days].sort((a, b) => a - b);
  const key = sorted.join(',');
  if (key === '1,2,3,4,5') return 'Mon–Fri';
  if (key === '1,2,3,4,5,6,7') return 'Every day';
  return sorted.map((d) => DAY_LETTERS[d] ?? '?').join(' ');
}

export default function CrewBreakWindowsPage() {
  const { t } = useTranslation();
  const router = useRouter();
  const q = useCrewBreakWindows();
  const del = useDeleteCrewBreakWindow();
  const rows = q.data ?? [];

  const onDelete = (w: CrewBreakWindow) =>
    Alert.alert(t('Delete break window'), t('Delete "{{name}}"?', { name: w.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(w.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
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
        <Text style={styles.h1}>{t('Break windows')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Break Window')} size="sm" onPress={() => router.push('/hr/break-windows/new' as never)} />
      </View>

      <DataTable<CrewBreakWindow>
        data={rows}
        searchPlaceholder={t('Search…')}
        columnsLabel={t('Columns')}
        columnsMenuLabel={t('Toggle columns')}
        searchKeys={['crew_name', 'name']}
        emptyText={t('No break windows defined yet.')}
        columns={[
          {
            key: 'crew',
            label: t('Crew'),
            flex: 1,
            render: (w) => (
              <Text numberOfLines={1} style={styles.name}>
                {w.crew_name ?? t('Crew #{{id}}', { id: w.crew_id })}
              </Text>
            ),
          },
          {
            key: 'name',
            label: t('Name'),
            flex: 1.2,
            render: (w) => <Text numberOfLines={1} style={styles.name}>{w.name}</Text>,
          },
          {
            key: 'time',
            label: t('Time'),
            width: 100,
            render: (w) => <Mono size={11} color={colors.muted}>{`${w.start_time}–${w.end_time}`}</Mono>,
          },
          {
            key: 'days',
            label: t('Days'),
            flex: 1,
            render: (w) => <Mono size={11} color={colors.muted}>{t(daysLabel(w.days_of_week))}</Mono>,
          },
          {
            key: 'is_active',
            label: t('Status'),
            width: 90,
            render: (w) => (
              <StatusPill status={w.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={t(w.is_active ? 'Active' : 'Inactive')} />
            ),
          },
        ]}
        actions={(w) => [{ label: t('Delete'), icon: 'delete', variant: 'danger', onPress: () => onDelete(w) }]}
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
