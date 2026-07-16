/**
 * Workstations — the stations that make up one line, mirroring the web admin
 * workstations page (Code / Name / Type / Steps / Workers / Status) with an
 * active/inactive filter. Rows open the workstation detail. Data via REST.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useWorkstations } from '@/hooks/queries/useLines';

export function WorkstationsList() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const lineId = Number(id);
  const router = useRouter();
  const [scope, setScope] = useState('');

  const includeInactive = scope === 'all';
  const q = useWorkstations(lineId, includeInactive);
  const rows = q.data ?? [];

  const options = useMemo(
    () => [
      { value: '', label: t('Active only') },
      { value: 'all', label: t('All stations') },
    ],
    [t],
  );

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Workstations')}</Text>
        <View style={{ flex: 1 }} />
        <View style={{ width: 150 }}>
          <Dropdown value={scope} onChange={(v) => setScope(v as string)} placeholder={t('Active only')} options={options} />
        </View>
        <Button title={t('New workstation')} size="sm" onPress={() => router.push(`/structure/lines/${lineId}/workstations/new` as never)} />
      </View>

      {q.isLoading && !q.data ? (
        <LoadingState />
      ) : q.isError && !q.data ? (
        <ErrorState error={q.error} onRetry={q.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.tableWrap}
          refreshControl={<RefreshControl refreshing={q.isFetching} onRefresh={q.refetch} tintColor={colors.accent} />}>
          <View style={[styles.row, styles.headerRow]}>
            <HCell w={96}>{t('Code')}</HCell>
            <HCell flex={1.4}>{t('Name')}</HCell>
            <HCell flex={1}>{t('Type')}</HCell>
            <HCell w={60}>{t('Steps')}</HCell>
            <HCell w={72}>{t('Workers')}</HCell>
            <HCell w={92}>{t('Status')}</HCell>
          </View>
          {rows.map((w) => (
            <WorkstationRow
              key={w.id}
              ws={w}
              onPress={() => router.push(`/structure/lines/${lineId}/workstations/${w.id}` as never)}
            />
          ))}
          {rows.length === 0 ? <Text style={styles.empty}>{t('No workstations yet')}</Text> : null}
        </ScrollView>
      )}
    </View>
  );
}

function WorkstationRow({ ws, onPress }: { ws: any; onPress: () => void }) {
  const { t } = useTranslation();
  return (
    <Pressable onPress={onPress} style={({ pressed }) => [styles.row, styles.dataRow, { opacity: pressed ? 0.6 : 1 }]}>
      <View style={{ width: 96 }}>
        <Mono size={11} color={colors.muted}>{ws.code}</Mono>
      </View>
      <View style={{ flex: 1.4 }}>
        <Text numberOfLines={1} style={styles.title}>{ws.name}</Text>
      </View>
      <View style={{ flex: 1 }}>
        <Text numberOfLines={1} style={styles.cellText}>{ws.workstation_type ?? '—'}</Text>
      </View>
      <View style={{ width: 60 }}>
        <Mono size={11} color={colors.muted}>{String(ws.template_steps_count ?? 0)}</Mono>
      </View>
      <View style={{ width: 72 }}>
        <Mono size={11} color={colors.muted}>{String(ws.workers_count ?? 0)}</Mono>
      </View>
      <View style={{ width: 92 }}>
        <StatusPill
          status={ws.is_active ? 'IN_PROGRESS' : 'CANCELLED'}
          label={ws.is_active ? t('Active') : t('Inactive')}
        />
      </View>
    </Pressable>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  headerRow: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  dataRow: { paddingVertical: 12, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  title: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
});
