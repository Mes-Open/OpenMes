import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { LoadingState } from '@/components/ui/StateViews';
import { useCreateMapping, useTopics } from '@/hooks/queries/useConnectivity';
import type {
  MachineTopic,
  MappingActionType,
  MappingInput,
  TopicMapping,
} from '@/api/connectivity';

const ACTION_TYPES: { value: MappingActionType; label: string }[] = [
  { value: 'update_batch_step', label: 'Update batch step' },
  { value: 'update_work_order_qty', label: 'Update WO qty' },
  { value: 'create_issue', label: 'Create issue' },
  { value: 'update_line_status', label: 'Update line status' },
  { value: 'set_work_order_status', label: 'Set WO status' },
  { value: 'log_event', label: 'Log event' },
  { value: 'webhook_forward', label: 'Forward webhook' },
];

export function NewMappingScreen() {
  const router = useRouter();
  const { topic_id } = useLocalSearchParams<{ topic_id?: string }>();
  const lockedId = topic_id ? Number(topic_id) : undefined;

  const topicsQ = useTopics({ include_inactive: true });
  const m = useCreateMapping();

  if (topicsQ.isLoading) return <LoadingState />;

  return (
    <MappingFormFields
      mode="create"
      topics={topicsQ.data ?? []}
      lockedTopicId={lockedId}
      submitting={m.isPending}
      onSubmit={(input) =>
        m.mutate(input, {
          onSuccess: () => router.back(),
          onError: (e: Error) => Alert.alert('Could not create mapping', e.message),
        })
      }
      onValidationError={(msg) => Alert.alert('Form error', msg)}
    />
  );
}

interface FormProps {
  initial?: Partial<TopicMapping>;
  topics: MachineTopic[];
  lockedTopicId?: number;
  mode: 'create' | 'edit';
  submitting?: boolean;
  onSubmit: (input: MappingInput) => void;
  /** Surface JSON parse errors back to the user. */
  onValidationError?: (msg: string) => void;
}

/**
 * Spec-styled topic-mapping form. Shared by mappings/new.tsx and
 * mappings/edit.tsx so the screens stay free of the old-design MappingForm.
 */
export function MappingFormFields({
  initial,
  topics,
  lockedTopicId,
  mode,
  submitting,
  onSubmit,
  onValidationError,
}: FormProps) {
  const { t } = useTranslation();
  const router = useRouter();

  const initialJson = initial?.action_params
    ? JSON.stringify(initial.action_params, null, 2)
    : '';
  const defaultTopicId = lockedTopicId ?? initial?.machine_topic_id ?? topics[0]?.id ?? 0;

  const [topicId, setTopicId] = useState<number>(defaultTopicId);
  const [description, setDescription] = useState(initial?.description ?? '');
  const [fieldPath, setFieldPath] = useState(initial?.field_path ?? '');
  const [condition, setCondition] = useState(initial?.condition_expr ?? '');
  const [priority, setPriority] = useState(String(initial?.priority ?? 10));
  const [actionType, setActionType] = useState<string>(
    (initial?.action_type as MappingActionType) ?? 'log_event',
  );
  const [paramsJson, setParamsJson] = useState(initialJson);
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);

  const showTopicPicker = mode === 'create' && !lockedTopicId;

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (mode === 'create' && !(topicId > 0)) e.topic = 'Pick a topic';
    const p = Number(priority);
    if (!priority.trim() || !Number.isInteger(p)) e.priority = 'Priority must be an integer';
    else if (p < 1) e.priority = 'Priority must be at least 1';
    else if (p > 9999) e.priority = 'Priority must be at most 9999';
    return e;
  }, [mode, topicId, priority]);

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    // Parse the JSON params. Empty string → null; invalid JSON → bubble up to
    // the caller's error handler so the form stays put.
    let params: Record<string, unknown> | null = null;
    const raw = paramsJson.trim();
    if (raw) {
      try {
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
          params = parsed as Record<string, unknown>;
        } else {
          onValidationError?.('Action params must be a JSON object, not an array or primitive.');
          return;
        }
      } catch (err) {
        onValidationError?.(`Invalid JSON in action params: ${(err as Error).message}`);
        return;
      }
    }

    const base = {
      description: description.trim() || null,
      field_path: fieldPath.trim() || null,
      action_type: actionType as MappingActionType,
      action_params: params,
      condition_expr: condition.trim() || null,
      priority: Number(priority),
      is_active: isActive,
    };
    const input: MappingInput =
      mode === 'create' ? { machine_topic_id: topicId, ...base } : base;
    onSubmit(input);
  };

  const disabled =
    !!submitting ||
    Object.keys(errors).length > 0 ||
    (mode === 'create' && topics.length === 0);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{mode === 'create' ? t('New mapping') : t('Edit mapping')}</Text>

      {showTopicPicker ? (
        <View style={{ gap: 6 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Topic').toUpperCase()} *</Mono>
          <Dropdown
            value={String(topicId)}
            onChange={(v) => setTopicId(Number(v))}
            placeholder={t('Select topic')}
            options={topics.map((tp) => ({ value: String(tp.id), label: tp.topic_pattern }))}
          />
          {topics.length === 0 ? (
            <Text style={styles.muted}>{t('No topics yet — create one first')}</Text>
          ) : null}
          {errors.topic ? <Text style={styles.error}>{t(errors.topic)}</Text> : null}
        </View>
      ) : null}

      <Field
        label="Description"
        value={description}
        onChangeText={setDescription}
        multiline
        numberOfLines={2}
        style={{ minHeight: 60, textAlignVertical: 'top' }}
      />
      <Field
        label="Field path"
        value={fieldPath}
        onChangeText={setFieldPath}
        autoCapitalize="none"
        autoCorrect={false}
        mono
        placeholder="data.good_qty"
        hint="JSONPath into the payload — e.g. data.good_qty"
      />
      <Field
        label="Condition"
        value={condition}
        onChangeText={setCondition}
        autoCapitalize="none"
        autoCorrect={false}
        hint="Optional expression to gate this mapping"
      />
      <Field
        label="Priority"
        value={priority}
        onChangeText={setPriority}
        error={errors.priority}
        keyboardType="number-pad"
        mono
        hint="Lower numbers run first"
      />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Action type').toUpperCase()} *</Mono>
        <Dropdown
          value={actionType}
          onChange={(v) => setActionType(v as string)}
          options={ACTION_TYPES.map((a) => ({ value: a.value, label: a.label }))}
        />
      </View>

      <Field
        label="Action params (JSON)"
        value={paramsJson}
        onChangeText={setParamsJson}
        multiline
        numberOfLines={6}
        autoCapitalize="none"
        autoCorrect={false}
        mono
        style={{ minHeight: 120, textAlignVertical: 'top' }}
        placeholder={'{\n  "key": "value"\n}'}
        hint="Optional JSON object passed to the action handler"
      />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Run this mapping on matching messages')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={mode === 'create' ? t('Create mapping') : t('Save changes')} onPress={onSave} loading={!!submitting} disabled={disabled} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  muted: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
