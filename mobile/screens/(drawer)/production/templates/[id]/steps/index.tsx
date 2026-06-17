import { useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { DetailScreen } from '@/components/ui/Detail';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useProcessTemplate } from '@/hooks/queries/useProductTypes';
import {
  useAddTemplateStep,
  useDeleteTemplateStep,
  useReorderTemplateSteps,
  useUpdateTemplateStep,
} from '@/hooks/mutations/productTypes';
import type { TemplateStep } from '@/api/processTemplates';

const TABS = ['Steps', 'QC templates', 'BOM'];

export function StepsEditor() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const processTemplateId = Number(id);
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const tpl = useProcessTemplate(processTemplateId);
  const add = useAddTemplateStep(processTemplateId);
  const reorder = useReorderTemplateSteps(processTemplateId);

  const [showAdd, setShowAdd] = useState(false);
  const [name, setName] = useState('');
  const [instruction, setInstruction] = useState('');
  const [duration, setDuration] = useState('');
  // Tabs are visual placeholders for now — only Steps is wired here.
  // QC templates lives at /qc-templates; BOM is web-only.
  // TODO(template-builder): wire QC + BOM panels into the same builder.
  const [activeTab, setActiveTab] = useState(0);

  if (tpl.isLoading) return <LoadingState />;
  if (tpl.isError || !tpl.data) return <ErrorState error={tpl.error} onRetry={tpl.refetch} />;

  const steps = (tpl.data.steps ?? []).slice().sort((a, b) => a.step_number - b.step_number);

  // Total duration roll-up — mirrors design "32m 30s total" line.
  const totalMin = steps.reduce(
    (acc, s) => acc + (s.estimated_duration_minutes ?? 0),
    0,
  );
  const totalLabel = totalMin > 0 ? `${totalMin}M TOTAL · ` : '';

  const move = (idx: number, dir: -1 | 1) => {
    const next = [...steps];
    const target = idx + dir;
    if (target < 0 || target >= next.length) return;
    const [removed] = next.splice(idx, 1);
    next.splice(target, 0, removed);
    reorder.mutate(next.map((s) => s.id), {
      onError: (e: Error) => Alert.alert('Reorder failed', e.message),
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
      { onSuccess: reset, onError: (e: Error) => Alert.alert('Add failed', e.message) },
    );
  };

  return (
    <DetailScreen>
      <View>
        <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
          {(tpl.data.product_type?.name ?? 'TEMPLATE').toUpperCase()}
        </Mono>
        <Text style={[styles.heading, { color: palette.text }]}>{tpl.data.name}</Text>
        <View style={styles.versionRow}>
          <View
            style={[
              styles.versionPill,
              { backgroundColor: tpl.data.is_active ? palette.success : palette.textFaint },
            ]}>
            <Mono size={10} color="#fff" weight="700" letterSpacing={0.5}>
              v{tpl.data.version} {tpl.data.is_active ? 'ACTIVE' : 'DRAFT'}
            </Mono>
          </View>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.4}>
            {totalLabel}
            {steps.length} STEP{steps.length === 1 ? '' : 'S'}
          </Mono>
        </View>
      </View>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        style={[styles.tabsTrack, { backgroundColor: palette.surfaceAlt }]}
        contentContainerStyle={{ padding: 4, gap: 4 }}>
        {TABS.map((t, i) => {
          const active = i === activeTab;
          return (
            <Pressable
              key={t}
              onPress={() => setActiveTab(i)}
              style={[
                styles.tab,
                active && {
                  backgroundColor: palette.surface,
                  boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.06)',
                },
              ]}>
              <Mono
                size={11}
                color={active ? palette.text : palette.textMuted}
                weight="600"
                letterSpacing={0.5}>
                {t.toUpperCase()}
              </Mono>
            </Pressable>
          );
        })}
      </ScrollView>

      {activeTab === 0 ? (
        <View style={{ gap: 8 }}>
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

          {showAdd ? (
            <Card style={{ gap: 12 }}>
              <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>NEW STEP</Mono>
              <Field label="Name" value={name} onChangeText={setName} required />
              <Field
                label="Instruction"
                value={instruction}
                onChangeText={setInstruction}
                multiline
                numberOfLines={3}
                style={{ minHeight: 80, textAlignVertical: 'top', paddingTop: 12 }}
              />
              <Field
                label="Duration min"
                value={duration}
                onChangeText={setDuration}
                keyboardType="number-pad"
                mono
                suffix={<Text style={{ fontFamily: MONO, fontSize: 11, color: palette.textFaint }}>MIN</Text>}
              />
              <View style={{ flexDirection: 'row', gap: 8 }}>
                <Button title="Cancel" variant="outline" onPress={reset} style={{ flex: 1 }} />
                <Button
                  title="Add step"
                  onPress={onAdd}
                  disabled={!name.trim()}
                  loading={add.isPending}
                  style={{ flex: 2 }}
                  leftIcon={<FontAwesome name="plus" size={13} color="#1a1208" />}
                />
              </View>
            </Card>
          ) : (
            <Pressable
              onPress={() => setShowAdd(true)}
              style={({ pressed }) => [
                styles.addBtn,
                {
                  borderColor: palette.border,
                  opacity: pressed ? 0.7 : 1,
                },
              ]}>
              <FontAwesome name="plus" size={14} color={palette.textMuted} />
              <Mono size={12} color={palette.textMuted} weight="600" letterSpacing={0.5}>
                ADD STEP
              </Mono>
            </Pressable>
          )}
        </View>
      ) : (
        <Card>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>
            {activeTab === 1 ? 'OPEN QC TEMPLATES SUB-SCREEN.' : 'BOM IS WEB-ADMIN ONLY.'}
          </Mono>
        </Card>
      )}
    </DetailScreen>
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const upd = useUpdateTemplateStep(processTemplateId);
  const del = useDeleteTemplateStep(processTemplateId);

  const [editing, setEditing] = useState(false);
  const [name, setName] = useState(step.name);
  const [instruction, setInstruction] = useState(step.instruction ?? '');
  const [duration, setDuration] = useState(
    step.estimated_duration_minutes != null ? String(step.estimated_duration_minutes) : '',
  );

  // Heuristic: name contains "QC" or "INSPECT" → tinted purple per design.
  // TODO(template-builder): replace with a real `is_qc` flag from API.
  const isQc = /\b(qc|inspect|quality)\b/i.test(step.name);
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
      { onSuccess: () => setEditing(false), onError: (e: Error) => Alert.alert('Save failed', e.message) },
    );
  };

  const remove = () => {
    Alert.alert('Delete step', `Delete "${step.name}"?`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Delete',
        style: 'destructive',
        onPress: () => del.mutate(step.id, { onError: (e: Error) => Alert.alert('Delete failed', e.message) }),
      },
    ]);
  };

  return (
    <Card
      style={{
        gap: editing ? 12 : 0,
        borderColor: isQc ? '#cfa8e8' : palette.border,
      }}>
      <View style={styles.row}>
        <View
          style={[
            styles.numBadge,
            { backgroundColor: isQc ? '#f1e5fa' : palette.surfaceAlt },
          ]}>
          <Text
            style={{
              color: isQc ? '#7c3aed' : palette.text,
              fontSize: 12,
              fontWeight: '700',
              fontFamily: MONO,
            }}>
            {String(step.step_number).padStart(2, '0')}
          </Text>
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={[styles.stepName, { color: palette.text }]} numberOfLines={1}>
            {step.name}
          </Text>
          {(step.workstation?.name || dur != null || isQc) ? (
            <Mono
              size={10.5}
              color={palette.textFaint}
              letterSpacing={0.4}
              style={{ marginTop: 4 }}>
              {[
                step.workstation?.name?.toUpperCase(),
                dur != null ? `${dur}M` : null,
                isQc ? 'QC' : null,
              ]
                .filter(Boolean)
                .join(' · ')}
            </Mono>
          ) : null}
        </View>
        <View style={{ flexDirection: 'row', gap: 4 }}>
          <Pressable
            onPress={onMoveUp}
            disabled={!canMoveUp}
            hitSlop={4}
            style={[styles.moveBtn, { borderColor: palette.border, opacity: canMoveUp ? 1 : 0.3 }]}>
            <FontAwesome name="arrow-up" size={11} color={palette.text} />
          </Pressable>
          <Pressable
            onPress={onMoveDown}
            disabled={!canMoveDown}
            hitSlop={4}
            style={[styles.moveBtn, { borderColor: palette.border, opacity: canMoveDown ? 1 : 0.3 }]}>
            <FontAwesome name="arrow-down" size={11} color={palette.text} />
          </Pressable>
          <Pressable
            onPress={() => setEditing((v) => !v)}
            hitSlop={4}
            style={[styles.moveBtn, { borderColor: palette.border }]}>
            <FontAwesome name={editing ? 'times' : 'pencil'} size={11} color={palette.text} />
          </Pressable>
        </View>
      </View>

      {editing ? (
        <View style={{ gap: 10 }}>
          <Field label="Name" value={name} onChangeText={setName} required />
          <Field
            label="Instruction"
            value={instruction}
            onChangeText={setInstruction}
            multiline
            numberOfLines={3}
            style={{ minHeight: 80, textAlignVertical: 'top', paddingTop: 12 }}
          />
          <Field
            label="Duration min"
            value={duration}
            onChangeText={setDuration}
            keyboardType="number-pad"
            mono
            suffix={<Text style={{ fontFamily: MONO, fontSize: 11, color: palette.textFaint }}>MIN</Text>}
          />
          <View style={{ flexDirection: 'row', gap: 8 }}>
            <Button title="Delete" variant="danger" onPress={remove} loading={del.isPending} style={{ flex: 1 }} />
            <Button title="Save" onPress={save} loading={upd.isPending} style={{ flex: 2 }} />
          </View>
        </View>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  heading: { fontSize: 24, fontWeight: '600', letterSpacing: -0.4, marginTop: 4 },
  versionRow: { flexDirection: 'row', alignItems: 'center', gap: 8, marginTop: 8 },
  versionPill: { paddingVertical: 3, paddingHorizontal: 7, borderRadius: 4 },
  tabsTrack: { borderRadius: 10, flexGrow: 0 },
  tab: { paddingVertical: 8, paddingHorizontal: 14, borderRadius: 7 },
  row: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  numBadge: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepName: { fontSize: 13, fontWeight: '600', letterSpacing: -0.1 },
  moveBtn: {
    width: 30,
    height: 30,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  addBtn: {
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    borderStyle: 'dashed',
    alignItems: 'center',
    justifyContent: 'center',
    flexDirection: 'row',
    gap: 8,
  },
});
