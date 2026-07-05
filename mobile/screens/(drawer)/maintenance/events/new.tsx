/**
 * New maintenance event — inline spec form mirroring the web admin
 * maintenance-events Create page (Title / Type / Tool / Line / Workstation /
 * Cost source / Assigned to / Scheduled at / Actual cost / Currency /
 * Description). Keeps the tool/line/user/cost-source queries and the
 * useCreateMaintenanceEvent mutation unchanged; supports the ?tool_id preset.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { LoadingState } from '@/components/ui/StateViews';
import { useCostSources } from '@/hooks/queries/useOps';
import { useCreateMaintenanceEvent, useTools } from '@/hooks/queries/useMaintenance';
import { useLines, useUsers } from '@/hooks/queries/useUsers';
import type { MaintenanceEventType } from '@/api/maintenance';

const TYPES: { value: MaintenanceEventType; label: string }[] = [
  { value: 'planned', label: 'Planned' },
  { value: 'corrective', label: 'Corrective' },
  { value: 'inspection', label: 'Inspection' },
];

type Opt = { value: string; label: string };

export function NewMaintenanceEventScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const params = useLocalSearchParams<{ tool_id?: string }>();
  const presetToolId = params.tool_id ? String(Number(params.tool_id)) : '';

  const toolsQ = useTools({});
  const linesQ = useLines();
  const usersQ = useUsers({});
  const costSourcesQ = useCostSources(false);
  const m = useCreateMaintenanceEvent();

  const [title, setTitle] = useState('');
  const [eventType, setEventType] = useState<MaintenanceEventType>('planned');
  const [toolId, setToolId] = useState(presetToolId);
  const [lineId, setLineId] = useState('');
  const [workstationId, setWorkstationId] = useState('');
  const [costSourceId, setCostSourceId] = useState('');
  const [assignedToId, setAssignedToId] = useState('');
  const [scheduledAt, setScheduledAt] = useState('');
  const [actualCost, setActualCost] = useState('');
  const [currency, setCurrency] = useState('PLN');
  const [description, setDescription] = useState('');

  const none = t('— None —');
  const toolOpts: Opt[] = useMemo(() => [{ value: '', label: none }, ...(toolsQ.data ?? []).map((x) => ({ value: String(x.id), label: x.name }))], [toolsQ.data, none]);
  const lineOpts: Opt[] = useMemo(() => [{ value: '', label: none }, ...(linesQ.data ?? []).map((x) => ({ value: String(x.id), label: x.name }))], [linesQ.data, none]);
  const costOpts: Opt[] = useMemo(() => [{ value: '', label: none }, ...(costSourcesQ.data ?? []).map((x) => ({ value: String(x.id), label: x.name }))], [costSourcesQ.data, none]);
  const userOpts: Opt[] = useMemo(
    () => [{ value: '', label: none }, ...(usersQ.data?.data ?? []).map((u) => ({ value: String(u.id), label: u.name ?? u.username ?? `#${u.id}` }))],
    [usersQ.data, none],
  );

  const titleError = title.trim() ? undefined : 'Required';

  if (toolsQ.isLoading || linesQ.isLoading || usersQ.isLoading || costSourcesQ.isLoading) {
    return <LoadingState />;
  }

  const onSave = () => {
    if (titleError) return;
    m.mutate(
      {
        title: title.trim(),
        event_type: eventType,
        tool_id: toolId ? Number(toolId) : undefined,
        line_id: lineId ? Number(lineId) : undefined,
        workstation_id: workstationId ? Number(workstationId) : undefined,
        cost_source_id: costSourceId ? Number(costSourceId) : undefined,
        assigned_to_id: assignedToId ? Number(assignedToId) : undefined,
        scheduled_at: scheduledAt.trim() || undefined,
        description: description.trim() || undefined,
        actual_cost: actualCost.trim() ? Number(actualCost) : undefined,
        currency: currency.trim() || undefined,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create event'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New maintenance event')}</Text>
      <Text style={styles.hint}>{t('Select at least one of Tool, Line, or Workstation.')}</Text>

      <Field label="Title" value={title} onChangeText={setTitle} error={titleError} required placeholder="Drive belt wear — replace" />

      <Select label={t('Type')} required value={eventType} onChange={(v) => setEventType(v as MaintenanceEventType)} options={TYPES.map((x) => ({ value: x.value, label: t(x.label) }))} />
      <Select label={t('Tool')} value={toolId} onChange={setToolId} options={toolOpts} />
      <Select label={t('Line')} value={lineId} onChange={setLineId} options={lineOpts} />
      <Select label={t('Workstation')} value={workstationId} onChange={setWorkstationId} options={[{ value: '', label: none }]} />
      <Select label={t('Cost source')} value={costSourceId} onChange={setCostSourceId} options={costOpts} />
      <Select label={t('Assigned to')} value={assignedToId} onChange={setAssignedToId} options={userOpts} />

      <Field label="Scheduled at" value={scheduledAt} onChangeText={setScheduledAt} placeholder="2026-06-01T08:00:00" autoCapitalize="none" autoCorrect={false} hint="ISO 8601 — minute precision" mono />

      <View style={styles.row}>
        <View style={{ flex: 1.4 }}>
          <Field label="Actual cost" value={actualCost} onChangeText={setActualCost} keyboardType="decimal-pad" hint="Filled in after completion" />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Currency" value={currency} onChangeText={setCurrency} autoCapitalize="characters" autoCorrect={false} placeholder="PLN" />
        </View>
      </View>

      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={styles.textarea} />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Create')} onPress={onSave} loading={m.isPending} disabled={m.isPending} />
      </View>
    </ScrollView>
  );
}

function Select({ label, value, onChange, options, required }: { label: string; value: string; onChange: (v: string) => void; options: Opt[]; required?: boolean }) {
  return (
    <View style={{ gap: 6 }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{`${label.toUpperCase()}${required ? ' *' : ''}`}</Mono>
      <Dropdown value={value} onChange={(v) => onChange(v as string)} options={options} />
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  hint: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular, marginTop: -8 },
  row: { flexDirection: 'row', gap: 10 },
  textarea: { minHeight: 80, textAlignVertical: 'top' },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
