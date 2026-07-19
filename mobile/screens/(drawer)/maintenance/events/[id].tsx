/**
 * Maintenance event detail — the web has no Show page for events, so this
 * mirrors the detail pattern: title header + status pill, a bordered key/value
 * info box, notes, and the tool history as a table. Start / complete / cancel /
 * delete wiring is preserved from the old design.
 */
import { format, parseISO } from 'date-fns';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useCancelMaintenanceEvent,
  useCompleteMaintenanceEvent,
  useDeleteMaintenanceEvent,
  useMaintenanceEvent,
  useMaintenanceEvents,
  useStartMaintenanceEvent,
} from '@/hooks/queries/useMaintenance';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';

const humanize = (s: string) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

export function MaintenanceEventDetailScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useMaintenanceEvent(numericId);
  const startMutation = useStartMaintenanceEvent();
  const completeMutation = useCompleteMaintenanceEvent();
  const cancelMutation = useCancelMaintenanceEvent();
  const deleteMutation = useDeleteMaintenanceEvent();

  const user = useAuthStore((s) => s.user);
  const isAdminOrSup = isSupervisorOrAdmin(user);

  const [resolutionNotes, setResolutionNotes] = useState('');
  const [actualCost, setActualCost] = useState('');
  const [currency, setCurrency] = useState('EUR');

  // Tool history — completed events scoped to the same tool. Keeps the list
  // small (5 most recent) so the screen stays glanceable.
  const toolId = query.data?.tool?.id ?? query.data?.tool_id;
  const historyQ = useMaintenanceEvents(toolId ? { tool_id: toolId, status: 'completed' } : {});

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const e = query.data;

  const canTransition = isAdminOrSup || e.assigned_to_id === user?.id;
  const canDelete = user?.roles?.some((r) => r.name === 'Admin') ?? false;

  const target = e.tool?.name ?? e.line?.name ?? e.workstation?.name ?? '—';
  const scheduled = e.scheduled_at ? String(e.scheduled_at).slice(0, 16).replace('T', ' ') : '—';
  const startedTime = e.started_at ? fmtTime(e.started_at) : '—';
  const history = (historyQ.data?.data ?? []).filter((h) => h.id !== e.id).slice(0, 5);

  const onStart = () =>
    startMutation.mutate(e.id, { onError: (err: Error) => Alert.alert(t('Failed'), err.message) });
  const onComplete = () =>
    completeMutation.mutate(
      {
        id: e.id,
        resolution_notes: resolutionNotes || undefined,
        actual_cost: actualCost ? Number(actualCost) : undefined,
        currency: currency || undefined,
      },
      { onError: (err: Error) => Alert.alert(t('Failed'), err.message) },
    );
  const onCancel = () =>
    cancelMutation.mutate(e.id, { onError: (err: Error) => Alert.alert(t('Failed'), err.message) });
  const onDelete = () =>
    Alert.alert(t('Delete event'), t('Delete "{{title}}"?').replace('{{title}}', e.title), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(e.id, {
            onSuccess: () => router.back(),
            onError: (err: Error) => Alert.alert(t('Failed'), err.message),
          }),
      },
    ]);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.headerRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{e.title}</Text>
          <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 6 }}>
            {[humanize(e.event_type), target].filter(Boolean).join(' · ').toUpperCase()}
          </Mono>
        </View>
        <StatusPill status={e.status} />
      </View>

      {/* Action bar */}
      {canTransition && e.status === 'pending' ? (
        <Button title={t('Start')} variant="success" loading={startMutation.isPending} onPress={onStart} />
      ) : null}
      {canTransition && e.status === 'in_progress' ? (
        <View style={styles.actionBar}>
          <Button
            title={t('Complete event')}
            variant="success"
            style={{ flex: 2 }}
            loading={completeMutation.isPending}
            onPress={onComplete}
          />
          <Button
            title={t('Cancel')}
            variant="outline"
            style={{ flex: 1 }}
            loading={cancelMutation.isPending}
            onPress={() =>
              Alert.alert(t('Cancel event'), t('Mark this event as cancelled?'), [
                { text: t('Back'), style: 'cancel' },
                { text: t('Cancel event'), style: 'destructive', onPress: onCancel },
              ])
            }
          />
        </View>
      ) : null}

      {/* Completion form */}
      {canTransition && e.status === 'in_progress' ? (
        <View style={{ gap: 12 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Completion details').toUpperCase()}</Mono>
          <Field
            label="Resolution notes"
            value={resolutionNotes}
            onChangeText={setResolutionNotes}
            multiline
            numberOfLines={3}
            style={{ minHeight: 80, textAlignVertical: 'top' }}
          />
          <Field label="Actual cost (optional)" value={actualCost} onChangeText={setActualCost} keyboardType="decimal-pad" mono />
          <Field label="Currency" value={currency} onChangeText={setCurrency} autoCapitalize="characters" mono />
        </View>
      ) : null}

      {/* Info box */}
      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Details').toUpperCase()}</Mono>
        <View style={styles.box}>
          <KVRow label={t('Type')} value={humanize(e.event_type)} />
          <KVRow label={t('Target')} value={target} />
          <KVRow label={t('Tool')} value={e.tool?.code ?? '—'} mono />
          <KVRow label={t('Assigned')} value={e.assigned_to_id != null ? `#${e.assigned_to_id}` : '—'} mono />
          <KVRow label={t('Scheduled')} value={scheduled} mono />
          <KVRow label={t('Started')} value={startedTime} mono last />
        </View>
      </View>

      {/* Notes */}
      {e.description ? (
        <View style={{ gap: 8 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Notes').toUpperCase()}</Mono>
          <View style={styles.box}>
            <Text style={styles.notes}>{e.description}</Text>
          </View>
        </View>
      ) : null}

      {/* Tool history */}
      {e.tool ? (
        <View style={{ gap: 8 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>
            {`${t('History')} · ${e.tool.code}`.toUpperCase()}
          </Mono>
          {history.length === 0 ? (
            <Text style={styles.empty}>{t('No previous events.')}</Text>
          ) : (
            <View>
              <View style={[styles.row, styles.tableHead]}>
                <HCell w={90}>{t('Date')}</HCell>
                <HCell flex={1}>{t('Event')}</HCell>
                <HCell w={64}>{t('By')}</HCell>
              </View>
              {history.map((h) => (
                <View key={h.id} style={[styles.row, styles.tableRow]}>
                  <View style={{ width: 90 }}>
                    <Mono size={10} color={colors.faint}>{(h.completed_at ?? h.scheduled_at ?? '').slice(0, 10)}</Mono>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text numberOfLines={1} style={styles.cellText}>{`${humanize(h.event_type)} · ${h.title}`}</Text>
                  </View>
                  <View style={{ width: 64 }}>
                    <Mono size={10} color={colors.muted}>{h.assigned_to_id != null ? `#${h.assigned_to_id}` : '—'}</Mono>
                  </View>
                </View>
              ))}
            </View>
          )}
        </View>
      ) : null}

      {canDelete && e.status === 'pending' ? (
        <View style={styles.actions}>
          <View style={{ flex: 1 }} />
          <Button title={t('Delete event')} variant="danger" loading={deleteMutation.isPending} onPress={onDelete} />
        </View>
      ) : null}
    </ScrollView>
  );
}

function KVRow({ label, value, mono, last }: { label: string; value: string; mono?: boolean; last?: boolean }) {
  return (
    <View style={[styles.kvRow, last ? null : styles.kvBorder]}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      {mono ? (
        <Mono size={11} color={colors.ink}>{value}</Mono>
      ) : (
        <Text style={styles.kvValue} numberOfLines={1}>{value}</Text>
      )}
    </View>
  );
}

function HCell({ children, w, flex }: { children: React.ReactNode; w?: number; flex?: number }) {
  return (
    <View style={{ width: w, flex }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{String(children).toUpperCase()}</Mono>
    </View>
  );
}

function fmtTime(iso: string): string {
  try {
    return format(parseISO(iso), 'HH:mm');
  } catch {
    return iso.slice(11, 16);
  }
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  actionBar: { flexDirection: 'row', gap: 8 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 14 },
  kvRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingVertical: 11 },
  kvBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  kvValue: { fontSize: 12.5, color: colors.ink, fontFamily: fonts.sans.native.medium },
  notes: { fontSize: 13, lineHeight: 20, color: colors.muted, fontFamily: fonts.sans.native.regular, paddingVertical: 12 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingHorizontal: 6 },
  tableHead: { paddingVertical: 8, borderBottomWidth: 1, borderBottomColor: colors.line },
  tableRow: { paddingVertical: 11, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 16 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
