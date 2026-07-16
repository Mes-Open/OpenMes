/**
 * New maintenance schedule — inline spec form mirroring the web admin
 * maintenance-schedules Create page (Name / Description / Event type / Tool /
 * Line / Workstation / Assigned to / Cost source / Frequency / Interval /
 * Preferred time / Lead time / Next due / Active). Keeps the tool/line/user/
 * cost-source queries and the useCreateMaintenanceSchedule mutation unchanged.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { LoadingState } from '@/components/ui/StateViews';
import { useCostSources } from '@/hooks/queries/useOps';
import { useCreateMaintenanceSchedule, useTools } from '@/hooks/queries/useMaintenance';
import { useLines, useUsers } from '@/hooks/queries/useUsers';
import type {
  MaintenanceEventType,
  MaintenanceScheduleInput,
  ScheduleFrequency,
} from '@/api/maintenanceSchedules';

const EVENT_TYPES: { value: MaintenanceEventType; label: string }[] = [
  { value: 'planned', label: 'Planned' },
  { value: 'corrective', label: 'Corrective' },
  { value: 'inspection', label: 'Inspection' },
];

const FREQS: { value: ScheduleFrequency; label: string }[] = [
  { value: 'daily', label: 'Daily' },
  { value: 'weekly', label: 'Weekly' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'quarterly', label: 'Quarterly' },
  { value: 'annually', label: 'Annually' },
  { value: 'by_hours', label: 'By hours' },
];

type Opt = { value: string; label: string };

function defaultNextDueIso(): string {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  d.setHours(8, 0, 0, 0);
  return d.toISOString().slice(0, 19);
}

export function NewMaintenanceScheduleScreen() {
  const { t } = useTranslation();
  const router = useRouter();

  const toolsQ = useTools({});
  const linesQ = useLines();
  const usersQ = useUsers({});
  const costSourcesQ = useCostSources(false);
  const m = useCreateMaintenanceSchedule();

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [eventType, setEventType] = useState<MaintenanceEventType>('planned');
  const [toolId, setToolId] = useState('');
  const [lineId, setLineId] = useState('');
  const [workstationId, setWorkstationId] = useState('');
  const [assignedToId, setAssignedToId] = useState('');
  const [costSourceId, setCostSourceId] = useState('');
  const [frequency, setFrequency] = useState<ScheduleFrequency>('monthly');
  const [intervalValue, setIntervalValue] = useState('1');
  const [preferredTime, setPreferredTime] = useState('');
  const [leadTimeDays, setLeadTimeDays] = useState('');
  const [nextDueAt, setNextDueAt] = useState(defaultNextDueIso());
  const [isActive, setIsActive] = useState(true);

  const none = t('— None —');
  const toolOpts: Opt[] = useMemo(() => [{ value: '', label: none }, ...(toolsQ.data ?? []).map((x) => ({ value: String(x.id), label: x.name }))], [toolsQ.data, none]);
  const lineOpts: Opt[] = useMemo(() => [{ value: '', label: none }, ...(linesQ.data ?? []).map((x) => ({ value: String(x.id), label: x.name }))], [linesQ.data, none]);
  const costOpts: Opt[] = useMemo(() => [{ value: '', label: none }, ...(costSourcesQ.data ?? []).map((x) => ({ value: String(x.id), label: x.name }))], [costSourcesQ.data, none]);
  const userOpts: Opt[] = useMemo(
    () => [{ value: '', label: none }, ...(usersQ.data?.data ?? []).map((u) => ({ value: String(u.id), label: u.name ?? u.username ?? `#${u.id}` }))],
    [usersQ.data, none],
  );

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (!nextDueAt.trim()) e.nextDueAt = 'Required';
    if (!toolId && !lineId && !workstationId) e.target = 'Select at least one of Tool, Line, or Workstation.';
    if (preferredTime.trim() && !/^\d{2}:\d{2}$/.test(preferredTime.trim())) e.preferredTime = 'Use HH:mm';
    return e;
  }, [name, nextDueAt, toolId, lineId, workstationId, preferredTime]);

  if (toolsQ.isLoading || linesQ.isLoading || usersQ.isLoading || costSourcesQ.isLoading) {
    return <LoadingState />;
  }

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input: MaintenanceScheduleInput = {
      name: name.trim(),
      description: description.trim() || null,
      event_type: eventType,
      tool_id: toolId ? Number(toolId) : null,
      line_id: lineId ? Number(lineId) : null,
      workstation_id: workstationId ? Number(workstationId) : null,
      assigned_to_id: assignedToId ? Number(assignedToId) : null,
      cost_source_id: costSourceId ? Number(costSourceId) : null,
      frequency,
      interval_value: Number(intervalValue) || 1,
      preferred_time: preferredTime.trim() || null,
      lead_time_days: leadTimeDays.trim() ? Number(leadTimeDays) : null,
      next_due_at: nextDueAt.trim(),
      is_active: isActive,
    };
    m.mutate(input, {
      onSuccess: () => router.back(),
      onError: (e: Error) => Alert.alert(t('Could not create schedule'), e.message),
    });
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New maintenance schedule')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required autoCorrect={false} />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={2} style={styles.textarea} />
      <Select label={t('Event type')} required value={eventType} onChange={(v) => setEventType(v as MaintenanceEventType)} options={EVENT_TYPES.map((x) => ({ value: x.value, label: t(x.label) }))} />

      <Select label={t('Tool')} value={toolId} onChange={setToolId} options={toolOpts} />
      <Select label={t('Line')} value={lineId} onChange={setLineId} options={lineOpts} />
      <Select label={t('Workstation')} value={workstationId} onChange={setWorkstationId} options={[{ value: '', label: none }]} />
      {errors.target ? <Text style={styles.error}>{t(errors.target)}</Text> : null}
      <Select label={t('Assigned to')} value={assignedToId} onChange={setAssignedToId} options={userOpts} />
      <Select label={t('Cost source')} value={costSourceId} onChange={setCostSourceId} options={costOpts} />

      <Select label={t('Frequency')} required value={frequency} onChange={(v) => setFrequency(v as ScheduleFrequency)} options={FREQS.map((x) => ({ value: x.value, label: t(x.label) }))} />
      <Field label="Interval value" value={intervalValue} onChangeText={setIntervalValue} required keyboardType="number-pad" hint="e.g. 2 with frequency Weekly = every 2 weeks" />
      <Field label="Preferred time" value={preferredTime} onChangeText={setPreferredTime} error={errors.preferredTime} placeholder="08:00" autoCapitalize="none" autoCorrect={false} hint="HH:mm" mono />
      <Field label="Lead time (days)" value={leadTimeDays} onChangeText={setLeadTimeDays} keyboardType="number-pad" hint="Generate the event N days before it is due" />
      <Field label="Next due at" value={nextDueAt} onChangeText={setNextDueAt} error={errors.nextDueAt} required placeholder="2026-06-01T08:00:00" autoCapitalize="none" autoCorrect={false} mono />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Generate events from this schedule')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

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
  textarea: { minHeight: 60, textAlignVertical: 'top' },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular, marginTop: -8 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
