import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useProcessSegment, useUpdateProcessSegment } from '@/hooks/queries/useProcessSegments';
import { useWorkstationTypes } from '@/hooks/queries/useWorkstationTypes';
import type { CreateProcessSegmentPayload, ProcessSegmentType } from '@/api/processSegments';

const SEGMENT_TYPES: { value: ProcessSegmentType; label: string }[] = [
  { value: 'production', label: 'Production' },
  { value: 'inspection', label: 'Inspection' },
  { value: 'maintenance', label: 'Maintenance' },
  { value: 'setup', label: 'Setup' },
  { value: 'cleaning', label: 'Cleaning' },
  { value: 'transport', label: 'Transport' },
  { value: 'other', label: 'Other' },
];

export function EditProcessSegmentScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useProcessSegment(Number.isFinite(id) ? id : undefined);
  const wsQ = useWorkstationTypes({});
  const m = useUpdateProcessSegment();

  const seg = q.data;
  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [segmentType, setSegmentType] = useState<ProcessSegmentType>('production');
  const [workstationTypeId, setWorkstationTypeId] = useState('');
  const [estDuration, setEstDuration] = useState('');
  const [reqOperators, setReqOperators] = useState('');
  const [standardInstruction, setStandardInstruction] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && seg) {
    setCode(seg.code);
    setName(seg.name);
    setDescription(seg.description ?? '');
    setSegmentType((seg.segment_type as ProcessSegmentType) ?? 'production');
    setWorkstationTypeId(seg.workstation_type_id != null ? String(seg.workstation_type_id) : '');
    setEstDuration(seg.estimated_duration_minutes != null ? String(seg.estimated_duration_minutes) : '');
    setReqOperators(seg.required_operators != null ? String(seg.required_operators) : '');
    setStandardInstruction(seg.standard_instruction ?? '');
    setIsActive(seg.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Code is required';
    if (!name.trim()) e.name = 'Name is required';
    return e;
  }, [code, name]);

  if (q.isLoading || wsQ.isLoading) return <LoadingState />;
  if (q.isError || !seg) return <ErrorState error={q.error ?? new Error(t('Segment not found'))} onRetry={q.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const payload: CreateProcessSegmentPayload = {
      code: code.trim(),
      name: name.trim(),
      description: description.trim() || undefined,
      segment_type: segmentType,
      workstation_type_id: workstationTypeId ? Number(workstationTypeId) : undefined,
      estimated_duration_minutes: estDuration.trim() ? Number(estDuration) : undefined,
      required_operators: reqOperators.trim() ? Number(reqOperators) : undefined,
      standard_instruction: standardInstruction.trim() || undefined,
      is_active: isActive,
    };
    m.mutate(
      { id, payload },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not save changes'), e.message),
      },
    );
  };

  const wsOptions = [
    { value: '', label: t('Any') },
    ...(wsQ.data ?? []).map((w) => ({ value: String(w.id), label: w.name })),
  ];

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit segment')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={2} style={{ minHeight: 60, textAlignVertical: 'top' }} />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Segment type').toUpperCase()} *</Mono>
        <Dropdown
          value={segmentType}
          onChange={(v) => setSegmentType(v as ProcessSegmentType)}
          options={SEGMENT_TYPES.map((s) => ({ value: s.value, label: t(s.label) }))}
        />
      </View>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Workstation type').toUpperCase()}</Mono>
        <Dropdown value={workstationTypeId} onChange={(v) => setWorkstationTypeId(v as string)} options={wsOptions} />
      </View>

      <Field label="Estimated duration (minutes)" value={estDuration} onChangeText={setEstDuration} keyboardType="number-pad" mono />
      <Field label="Required operators" value={reqOperators} onChangeText={setReqOperators} keyboardType="number-pad" mono />
      <Field label="Standard instruction" value={standardInstruction} onChangeText={setStandardInstruction} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Available for template steps')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={m.isPending} disabled={m.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
