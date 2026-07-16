/**
 * Quality tasks (GET /api/v1/quality-control-tasks) — the outstanding quality-control
 * queue (due + in_progress), oldest-due first. 1:1 with the web shared/quality-tasks
 * table (Pages/shared/quality-tasks/Index.jsx): the shared DataTable with the web's
 * column set (Control / Reason / Work order / Batch / Line / Due since) and per-row
 * actions (Perform when tied to a batch, Skip). Tasks are performed (records the
 * control's samples + notes and completes the task) via the Perform sheet; a roaming
 * check can be raised ad-hoc from the header. Geist White, light-only v1.
 */
import { useMemo, useState } from 'react';
import { Alert, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, SegmentedControl } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { ActionSheet, BottomSheet } from '@openmes/ui/native';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  usePerformQualityTask,
  useQualityTasks,
  useRaiseRoamingTask,
  useSkipQualityTask,
} from '@/hooks/queries/useQualityTasks';
import type { QcPallet, QualityTask } from '@/api/qualityTasks';

// ISO8601 → "YYYY-MM-DD HH:mm" (web parity: slice(0,16).replace('T',' ')).
const dueSince = (iso?: string | null) => (iso ? String(iso).slice(0, 16).replace('T', ' ') : '—');

interface PerformRow {
  parameter_name: string;
  parameter_type: 'measurement' | 'pass_fail';
  unit?: string | null;
  value_numeric: string;
  is_passed: boolean;
}

function initialRows(task: QualityTask, resultLabel: string): PerformRow[] {
  const params = task.parameters ?? [];
  if (!params.length) {
    return [{ parameter_name: resultLabel, parameter_type: 'pass_fail', value_numeric: '', is_passed: true }];
  }
  return params.map((p) => ({
    parameter_name: p.name,
    parameter_type: p.type === 'measurement' ? 'measurement' : 'pass_fail',
    unit: p.unit,
    value_numeric: '',
    is_passed: true,
  }));
}

export default function QualityTasksPage() {
  const { t } = useTranslation();
  const q = useQualityTasks();
  const skip = useSkipQualityTask();
  const perform = usePerformQualityTask();
  const raiseRoaming = useRaiseRoamingTask();

  const roamingTriggers = q.data?.roamingTriggers ?? [];
  const activeBatches = q.data?.activeBatches ?? [];
  const pallets = q.data?.pallets ?? [];

  // Oldest-due first (#9).
  const items = useMemo(() => {
    const list = [...(q.data?.tasks ?? [])];
    list.sort((a, b) => String(a.fired_at ?? '').localeCompare(String(b.fired_at ?? '')));
    return list;
  }, [q.data?.tasks]);

  // ── Perform sheet ────────────────────────────────────────────────────────
  const [performFor, setPerformFor] = useState<QualityTask | null>(null);
  const [rows, setRows] = useState<PerformRow[]>([]);
  const [notes, setNotes] = useState('');
  const [palletId, setPalletId] = useState<number | null>(null);
  const [palletPicker, setPalletPicker] = useState(false);

  const palletOptions = useMemo<QcPallet[]>(
    () =>
      performFor
        ? pallets.filter((p) => !performFor.work_order_id || p.work_order_id === performFor.work_order_id)
        : [],
    [performFor, pallets],
  );

  const openPerform = (task: QualityTask) => {
    setRows(initialRows(task, t('Result')));
    setNotes('');
    setPalletId(null);
    setPerformFor(task);
  };

  const setRow = (i: number, patch: Partial<PerformRow>) =>
    setRows((rs) => rs.map((r, idx) => (idx === i ? { ...r, ...patch } : r)));

  const submitPerform = () => {
    if (!performFor) return;
    const samples = rows.map((r, i) => ({
      sample_number: i + 1,
      parameter_name: r.parameter_name,
      parameter_type: r.parameter_type,
      value_numeric: r.value_numeric === '' ? null : Number(r.value_numeric),
      is_passed: r.is_passed,
    }));
    perform.mutate(
      { id: performFor.id, payload: { samples, notes: notes || null, pallet_id: palletId } },
      {
        onSuccess: () => setPerformFor(null),
        onError: (e: Error) => Alert.alert(t('Could not record control'), e.message),
      },
    );
  };

  // ── Roaming sheet ────────────────────────────────────────────────────────
  const [roamingOpen, setRoamingOpen] = useState(false);
  const [roamingTrigger, setRoamingTrigger] = useState<number | null>(null);
  const [roamingBatch, setRoamingBatch] = useState<number | null>(null);
  const [roamingPicker, setRoamingPicker] = useState<'trigger' | 'batch' | null>(null);

  const openRoaming = () => {
    setRoamingTrigger(null);
    setRoamingBatch(null);
    setRoamingOpen(true);
  };

  const submitRoaming = () => {
    if (!roamingTrigger || !roamingBatch) return;
    raiseRoaming.mutate(
      { quality_control_trigger_id: roamingTrigger, batch_id: roamingBatch },
      {
        onSuccess: () => setRoamingOpen(false),
        onError: (e: Error) => Alert.alert(t('Could not raise check'), e.message),
      },
    );
  };

  const onSkip = (task: QualityTask) => {
    Alert.alert(t('Skip control?'), t('Skip "{{name}}"?', { name: task.trigger_name ?? t('this control') }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Skip'),
        style: 'destructive',
        onPress: () =>
          skip.mutate(task.id, { onError: (e: Error) => Alert.alert(t('Could not skip'), e.message) }),
      },
    ]);
  };

  const palletLabel = (id: number | null) =>
    id == null ? t('— None —') : (palletOptions.find((p) => p.id === id)?.pallet_no ?? `#${id}`);
  const triggerLabel = roamingTriggers.find((r) => r.id === roamingTrigger)?.name ?? t('Select…');
  const batchLabel = activeBatches.find((b) => b.id === roamingBatch)?.label ?? t('Select…');

  return (
    <>
      <View style={styles.screen}>
        <View style={styles.head}>
          <Text style={styles.h1}>{t('Quality tasks')}</Text>
          <View style={{ flex: 1 }} />
          {roamingTriggers.length ? (
            <Button title={t('Raise roaming check')} variant="secondary" size="sm" onPress={openRoaming} />
          ) : null}
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
            <DataTable<QualityTask>
              data={items}
              searchPlaceholder={t('Search…')}
              columnsLabel={t('Columns')}
              columnsMenuLabel={t('Toggle columns')}
              searchKeys={['trigger_name', 'due_reason', 'work_order_no', 'line_name']}
              emptyText={t('No outstanding quality controls.')}
              columns={[
                {
                  key: 'control',
                  label: t('Control'),
                  flex: 1.4,
                  render: (task) => (
                    <View style={styles.controlCell}>
                      <Text numberOfLines={1} style={styles.controlName}>
                        {task.trigger_name ?? t('Quality control')}
                      </Text>
                      {task.is_blocking ? (
                        <View style={styles.badge}>
                          <Mono size={8} color={colors.blocked} weight="700" letterSpacing={0.5}>
                            {t('BLOCKING')}
                          </Mono>
                        </View>
                      ) : null}
                    </View>
                  ),
                },
                {
                  key: 'due_reason',
                  label: t('Reason'),
                  flex: 1.2,
                  render: (task) => <Text numberOfLines={2} style={styles.cellText}>{task.due_reason || '—'}</Text>,
                },
                { key: 'work_order', label: t('Work order'), width: 110, render: (task) => task.work_order_no ?? '—' },
                {
                  key: 'batch',
                  label: t('Batch'),
                  width: 90,
                  render: (task) => (task.batch_number ? `#${task.batch_number}` : '—'),
                },
                { key: 'line', label: t('Line'), width: 110, render: (task) => task.line_name ?? '—' },
                {
                  key: 'fired_at',
                  label: t('Due since'),
                  width: 120,
                  render: (task) => <Mono size={10} color={colors.faint}>{dueSince(task.fired_at)}</Mono>,
                },
              ]}
              actions={(task) => {
                const list: { label: string; variant?: 'primary'; onPress: () => void }[] = [];
                if (task.batch_id) list.push({ label: t('Perform'), variant: 'primary', onPress: () => openPerform(task) });
                list.push({ label: t('Skip'), onPress: () => onSkip(task) });
                return list;
              }}
            />
          </ScrollView>
        )}
      </View>

      {/* Perform — record the control's samples and complete the task. */}
      <BottomSheet
        open={performFor != null}
        onClose={() => setPerformFor(null)}
        title={t('Perform quality control')}
        subtitle={performFor?.trigger_name ?? undefined}
        footer={
          <View style={styles.footer}>
            <Button title={t('Cancel')} variant="secondary" onPress={() => setPerformFor(null)} style={{ flex: 1 }} />
            <Button title={t('Record result')} onPress={submitPerform} loading={perform.isPending} style={{ flex: 1 }} />
          </View>
        }>
        <ScrollView contentContainerStyle={{ gap: 14 }} keyboardShouldPersistTaps="handled">
          {rows.map((row, i) => (
            <View key={i} style={{ gap: 8 }}>
              <Text style={styles.paramName}>
                {row.parameter_name}
                {row.unit ? <Text style={styles.paramUnit}> ({row.unit})</Text> : null}
              </Text>
              {row.parameter_type === 'measurement' ? (
                <Field
                  label="Measured value"
                  keyboardType="decimal-pad"
                  value={row.value_numeric}
                  onChangeText={(v) => setRow(i, { value_numeric: v })}
                />
              ) : null}
              <SegmentedControl
                value={row.is_passed ? 'pass' : 'fail'}
                onChange={(v) => setRow(i, { is_passed: v === 'pass' })}
                options={[
                  { value: 'pass', label: t('Pass') },
                  { value: 'fail', label: t('Fail') },
                ]}
              />
            </View>
          ))}

          {palletOptions.length > 0 ? (
            <View style={{ gap: 6 }}>
              <Mono size={9} color={colors.faint} letterSpacing={1}>
                {t('Link to pallet (optional)').toUpperCase()}
              </Mono>
              <Pressable onPress={() => setPalletPicker(true)} style={styles.selectBtn}>
                <Text style={styles.selectText}>{palletLabel(palletId)}</Text>
                <FontAwesome name="chevron-down" size={12} color={colors.faint} />
              </Pressable>
            </View>
          ) : null}

          <Field label="Notes" placeholder="Optional" multiline value={notes} onChangeText={setNotes} />

          <Text style={styles.infoNote}>
            {t('A failing result raises a non-conformance and, for blocking controls, halts the work order.')}
          </Text>
        </ScrollView>
      </BottomSheet>

      {/* Raise roaming check — ad-hoc control against an in-progress batch. */}
      <BottomSheet
        open={roamingOpen}
        onClose={() => setRoamingOpen(false)}
        title={t('Raise roaming check')}
        footer={
          <View style={styles.footer}>
            <Button title={t('Cancel')} variant="secondary" onPress={() => setRoamingOpen(false)} style={{ flex: 1 }} />
            <Button
              title={t('Raise')}
              onPress={submitRoaming}
              disabled={!roamingTrigger || !roamingBatch}
              loading={raiseRoaming.isPending}
              style={{ flex: 1 }}
            />
          </View>
        }>
        <View style={{ gap: 14 }}>
          <View style={{ gap: 6 }}>
            <Mono size={9} color={colors.faint} letterSpacing={1}>
              {t('Roaming trigger').toUpperCase()}
            </Mono>
            <Pressable onPress={() => setRoamingPicker('trigger')} style={styles.selectBtn}>
              <Text style={styles.selectText}>{triggerLabel}</Text>
              <FontAwesome name="chevron-down" size={12} color={colors.faint} />
            </Pressable>
          </View>
          <View style={{ gap: 6 }}>
            <Mono size={9} color={colors.faint} letterSpacing={1}>
              {t('Batch').toUpperCase()}
            </Mono>
            <Pressable onPress={() => setRoamingPicker('batch')} style={styles.selectBtn}>
              <Text style={styles.selectText}>{batchLabel}</Text>
              <FontAwesome name="chevron-down" size={12} color={colors.faint} />
            </Pressable>
          </View>
        </View>
      </BottomSheet>

      {/* Selection sheets (nested over the perform / roaming sheets). */}
      <ActionSheet
        open={palletPicker}
        onClose={() => setPalletPicker(false)}
        title={t('Link to pallet (optional)')}
        options={[
          { key: 'none', label: t('— None —'), onSelect: () => setPalletId(null) },
          ...palletOptions.map((p) => ({
            key: String(p.id),
            label: p.pallet_no ?? `#${p.id}`,
            onSelect: () => setPalletId(p.id),
          })),
        ]}
      />
      <ActionSheet
        open={roamingPicker === 'trigger'}
        onClose={() => setRoamingPicker(null)}
        title={t('Roaming trigger')}
        options={roamingTriggers.map((r) => ({
          key: String(r.id),
          label: r.name,
          onSelect: () => setRoamingTrigger(r.id),
        }))}
      />
      <ActionSheet
        open={roamingPicker === 'batch'}
        onClose={() => setRoamingPicker(null)}
        title={t('Batch')}
        options={activeBatches.map((b) => ({
          key: String(b.id),
          label: b.label,
          onSelect: () => setRoamingBatch(b.id),
        }))}
      />
    </>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingVertical: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  tableWrap: { paddingHorizontal: 14, paddingBottom: 24 },
  controlCell: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  controlName: { flexShrink: 1, fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  badge: { backgroundColor: `${colors.blocked}22`, borderRadius: 4, paddingVertical: 2, paddingHorizontal: 5 },
  cellText: { fontSize: 12.5, color: colors.muted, fontFamily: fonts.sans.native.regular },
  footer: { flexDirection: 'row', gap: 10 },
  paramName: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  paramUnit: { color: colors.faint, fontFamily: fonts.sans.native.regular },
  selectBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: 10,
    paddingVertical: 11,
    paddingHorizontal: 12,
    backgroundColor: colors.card,
  },
  selectText: { fontSize: 14, color: colors.ink, fontFamily: fonts.sans.native.medium },
  infoNote: { fontSize: 12, color: colors.faint, fontFamily: fonts.sans.native.regular, lineHeight: 17 },
});
