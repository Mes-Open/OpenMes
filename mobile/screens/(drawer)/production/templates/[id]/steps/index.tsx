/**
 * Template step editor — the mobile counterpart of the web process-templates
 * Show step builder. Lists the ordered steps as bordered rows with move
 * up/down, inline edit and delete, plus an inline "add step" form. Reorder,
 * add, update and delete all go through the same mutations as before.
 */
import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useProcessTemplate } from '@/hooks/queries/useProductTypes';
import {
  useAddTemplateStep,
  useDeleteTemplateStep,
  useReorderTemplateSteps,
  useUpdateTemplateStep,
} from '@/hooks/mutations/productTypes';
import type { TemplateStep } from '@/api/processTemplates';

export function StepsEditor() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const processTemplateId = Number(id);
  const { t } = useTranslation();

  const tpl = useProcessTemplate(processTemplateId);
  const add = useAddTemplateStep(processTemplateId);
  const reorder = useReorderTemplateSteps(processTemplateId);

  const [showAdd, setShowAdd] = useState(false);
  const [name, setName] = useState('');
  const [instruction, setInstruction] = useState('');
  const [duration, setDuration] = useState('');

  if (tpl.isLoading && !tpl.data) return <LoadingState />;
  if (tpl.isError || !tpl.data) return <ErrorState error={tpl.error} onRetry={tpl.refetch} />;

  const steps = (tpl.data.steps ?? []).slice().sort((a, b) => a.step_number - b.step_number);
  const totalMin = steps.reduce((acc, s) => acc + (s.estimated_duration_minutes ?? 0), 0);

  const subtitle = [
    `v${tpl.data.version}`,
    (tpl.data.is_active ? t('Active') : t('Draft')).toUpperCase(),
    totalMin > 0 ? `${totalMin}M ${t('Total').toUpperCase()}` : null,
    `${steps.length} ${(steps.length === 1 ? t('Step') : t('Steps')).toUpperCase()}`,
  ]
    .filter(Boolean)
    .join(' · ');

  const move = (idx: number, dir: -1 | 1) => {
    const next = [...steps];
    const target = idx + dir;
    if (target < 0 || target >= next.length) return;
    const [removed] = next.splice(idx, 1);
    next.splice(target, 0, removed);
    reorder.mutate(next.map((s) => s.id), {
      onError: (e: Error) => Alert.alert(t('Reorder failed'), e.message),
    });
  };

  const reset = () => {
    setShowAdd(false);
    setName('');
    setInstruction('');
    setDuration('');
  };

  const onAdd = () => {
    add.mutate(
      {
        name,
        instruction: instruction || null,
        estimated_duration_minutes: duration ? Number(duration) : null,
      },
      { onSuccess: reset, onError: (e: Error) => Alert.alert(t('Add failed'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View style={styles.head}>
        <View style={{ flex: 1 }}>
          <Text style={styles.h1}>{tpl.data.name}</Text>
          <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 4 }}>{subtitle}</Mono>
        </View>
        {!showAdd ? <Button title={t('Add step')} size="sm" onPress={() => setShowAdd(true)} /> : null}
      </View>

      <View style={{ gap: 10 }}>
        {steps.map((step, idx) => (
          <StepRow
            key={step.id}
            step={step}
            processTemplateId={processTemplateId}
            canMoveUp={idx > 0}
            canMoveDown={idx < steps.length - 1}
            onMoveUp={() => move(idx, -1)}
            onMoveDown={() => move(idx, +1)}
          />
        ))}

        {steps.length === 0 && !showAdd ? <Text style={styles.empty}>{t('No steps defined yet.')}</Text> : null}

        {showAdd ? (
          <View style={styles.box}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('New step').toUpperCase()}</Mono>
            <Field label="Name" value={name} onChangeText={setName} required />
            <Field
              label="Instruction"
              value={instruction}
              onChangeText={setInstruction}
              multiline
              numberOfLines={3}
              style={{ minHeight: 80, textAlignVertical: 'top', paddingTop: 12 }}
            />
            <Field label="Duration min" value={duration} onChangeText={setDuration} keyboardType="number-pad" mono />
            <View style={styles.formActions}>
              <Button title={t('Cancel')} variant="ghost" onPress={reset} />
              <View style={{ flex: 1 }} />
              <Button title={t('Add step')} onPress={onAdd} disabled={!name.trim()} loading={add.isPending} />
            </View>
          </View>
        ) : null}
      </View>
    </ScrollView>
  );
}

function StepRow({
  step,
  processTemplateId,
  canMoveUp,
  canMoveDown,
  onMoveUp,
  onMoveDown,
}: {
  step: TemplateStep;
  processTemplateId: number;
  canMoveUp: boolean;
  canMoveDown: boolean;
  onMoveUp: () => void;
  onMoveDown: () => void;
}) {
  const { t } = useTranslation();
  const upd = useUpdateTemplateStep(processTemplateId);
  const del = useDeleteTemplateStep(processTemplateId);

  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(step.name);
  const [instruction, setInstruction] = useState(step.instruction ?? '');
  const [duration, setDuration] = useState(
    step.estimated_duration_minutes != null ? String(step.estimated_duration_minutes) : '',
  );

  const dur = step.estimated_duration_minutes;

  useEffect(() => {
    setName(step.name);
    setInstruction(step.instruction ?? '');
    setDuration(step.estimated_duration_minutes != null ? String(step.estimated_duration_minutes) : '');
  }, [step.id, step.name, step.instruction, step.estimated_duration_minutes]);

  const save = () => {
    upd.mutate(
      {
        stepId: step.id,
        input: {
          name,
          instruction: instruction || null,
          estimated_duration_minutes: duration ? Number(duration) : null,
        },
      },
      { onSuccess: () => setEditing(false), onError: (e: Error) => Alert.alert(t('Save failed'), e.message) },
    );
  };

  const remove = () => {
    Alert.alert(t('Delete step'), step.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(step.id, { onError: (e: Error) => Alert.alert(t('Delete failed'), e.message) }),
      },
    ]);
  };

  const meta = [step.workstation?.name?.toUpperCase(), dur != null ? `${dur}M` : null].filter(Boolean).join(' · ');

  return (
    <View style={styles.box}>
      <View style={styles.rowTop}>
        <View style={styles.numBadge}>
          <Mono size={11} color={colors.accent}>{String(step.step_number).padStart(2, '0')}</Mono>
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text numberOfLines={1} style={styles.stepName}>{step.name}</Text>
          {meta ? (
            <Mono size={10} color={colors.faint} letterSpacing={0.4} style={{ marginTop: 4 }}>{meta}</Mono>
          ) : null}
        </View>
        <View style={styles.iconRow}>
          <IconBtn name="arrow-up" onPress={onMoveUp} disabled={!canMoveUp} />
          <IconBtn name="arrow-down" onPress={onMoveDown} disabled={!canMoveDown} />
          <IconBtn name={editing ? 'times' : 'pencil'} onPress={() => setEditing((v) => !v)} />
        </View>
      </View>

      {editing ? (
        <View style={{ gap: 10, marginTop: 12 }}>
          <Field label="Name" value={name} onChangeText={setName} required />
          <Field
            label="Instruction"
            value={instruction}
            onChangeText={setInstruction}
            multiline
            numberOfLines={3}
            style={{ minHeight: 80, textAlignVertical: 'top', paddingTop: 12 }}
          />
          <Field label="Duration min" value={duration} onChangeText={setDuration} keyboardType="number-pad" mono />
          <View style={styles.formActions}>
            <Button title={t('Delete')} variant="danger" onPress={remove} loading={del.isPending} />
            <View style={{ flex: 1 }} />
            <Button title={t('Save')} onPress={save} loading={upd.isPending} />
          </View>
        </View>
      ) : null}
    </View>
  );
}

function IconBtn({ name, onPress, disabled }: { name: React.ComponentProps<typeof FontAwesome>['name']; onPress: () => void; disabled?: boolean }) {
  return (
    <Pressable
      onPress={onPress}
      disabled={disabled}
      hitSlop={4}
      style={[styles.iconBtn, { opacity: disabled ? 0.3 : 1 }]}>
      <FontAwesome name={name} size={12} color={colors.ink} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14, gap: 12 },
  rowTop: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  numBadge: { width: 32, height: 32, borderRadius: radius.sm, backgroundColor: colors.chip, alignItems: 'center', justifyContent: 'center' },
  stepName: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  iconRow: { flexDirection: 'row', gap: 6 },
  iconBtn: { width: 30, height: 30, borderRadius: radius.sm, borderWidth: 1, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  formActions: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 8 },
});
