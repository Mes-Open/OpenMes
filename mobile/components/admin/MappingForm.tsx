import { zodResolver } from '@hookform/resolvers/zod';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { z } from 'zod';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { ControlledField } from '@/components/ui/ControlledField';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { nonEmpty } from '@/lib/forms/zod';
import type {
  MachineTopic,
  MappingActionType,
  MappingInput,
  TopicMapping,
} from '@/api/connectivity';

const ACTION_TYPES: { id: MappingActionType; label: string }[] = [
  { id: 'update_batch_step', label: 'Update batch step' },
  { id: 'update_work_order_qty', label: 'Update WO qty' },
  { id: 'create_issue', label: 'Create issue' },
  { id: 'update_line_status', label: 'Update line status' },
  { id: 'set_work_order_status', label: 'Set WO status' },
  { id: 'log_event', label: 'Log event' },
  { id: 'webhook_forward', label: 'Forward webhook' },
];

const priorityField = z.coerce
  .number({ message: 'Priority is required' })
  .int('Priority must be an integer')
  .min(1, 'Priority must be at least 1')
  .max(9999, 'Priority must be at most 9999');

export const mappingSchema = z.object({
  machine_topic_id: z.number().int().positive('Pick a topic'),
  description: z.string().trim(),
  field_path: z.string().trim(),
  action_type: z.enum([
    'update_batch_step',
    'update_work_order_qty',
    'create_issue',
    'update_line_status',
    'set_work_order_status',
    'log_event',
    'webhook_forward',
  ]),
  // Free-form JSON object. Parsed/validated on submit so the user gets a
  // clear error if their JSON is malformed.
  action_params_json: z.string(),
  condition_expr: z.string().trim(),
  priority: priorityField,
  is_active: z.boolean(),
});

export type MappingFormInput = z.input<typeof mappingSchema>;
export type MappingFormValues = z.output<typeof mappingSchema>;

interface Props {
  initial?: Partial<TopicMapping>;
  topics: MachineTopic[];
  lockedTopicId?: number;
  mode: 'create' | 'edit';
  submitting?: boolean;
  onSubmit: (input: MappingInput) => void;
  /** Surface JSON parse errors back to the user. */
  onValidationError?: (msg: string) => void;
}

export function MappingForm({
  initial,
  topics,
  lockedTopicId,
  mode,
  submitting,
  onSubmit,
  onValidationError,
}: Props) {
  const { t } = useTranslation();
  const initialJson = initial?.action_params
    ? JSON.stringify(initial.action_params, null, 2)
    : '';

  const defaultTopicId =
    lockedTopicId ?? initial?.machine_topic_id ?? topics[0]?.id ?? 0;

  const { control, handleSubmit, formState: { isValid } } = useForm<
    MappingFormInput,
    unknown,
    MappingFormValues
  >({
    resolver: zodResolver(mappingSchema),
    mode: 'onChange',
    defaultValues: {
      machine_topic_id: defaultTopicId,
      description: initial?.description ?? '',
      field_path: initial?.field_path ?? '',
      action_type: (initial?.action_type as MappingActionType) ?? 'log_event',
      action_params_json: initialJson,
      condition_expr: initial?.condition_expr ?? '',
      priority: String(initial?.priority ?? 10),
      is_active: initial?.is_active ?? true,
    },
  });

  const showTopicPicker = mode === 'create' && !lockedTopicId;

  return (
    <View style={{ gap: 14 }}>
      {showTopicPicker ? (
        <Card style={{ gap: 12 }}>
          <SectionLabel>Topic</SectionLabel>
          <Controller
            control={control}
            name="machine_topic_id"
            render={({ field: { value, onChange } }) => (
              <ChipRow>
                {topics.map((t) => (
                  <SelectionChip
                    key={t.id}
                    label={t.topic_pattern}
                    active={t.id === value}
                    onPress={() => onChange(t.id)}
                  />
                ))}
              </ChipRow>
            )}
          />
          {topics.length === 0 ? (
            <Mono size={11}>{t('No topics yet — create one first').toUpperCase()}</Mono>
          ) : null}
        </Card>
      ) : null}

      <Card style={{ gap: 12 }}>
        <SectionLabel>Mapping</SectionLabel>
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={2}
          style={{ minHeight: 60, textAlignVertical: 'top' }}
        />
        <ControlledField
          control={control}
          name="field_path"
          label="Field path"
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="data.good_qty"
          hint="JSONPath into the payload — e.g. data.good_qty"
        />
        <ControlledField
          control={control}
          name="condition_expr"
          label="Condition"
          autoCapitalize="none"
          autoCorrect={false}
          hint="Optional expression to gate this mapping"
        />
        <ControlledField
          control={control}
          name="priority"
          label="Priority"
          keyboardType="number-pad"
          hint="Lower numbers run first"
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Action</SectionLabel>
        <Controller
          control={control}
          name="action_type"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {ACTION_TYPES.map((a) => (
                <SelectionChip
                  key={a.id}
                  label={t(a.label)}
                  active={a.id === value}
                  onPress={() => onChange(a.id)}
                />
              ))}
            </ChipRow>
          )}
        />
        <ControlledField
          control={control}
          name="action_params_json"
          label="Action params (JSON)"
          multiline
          numberOfLines={6}
          autoCapitalize="none"
          autoCorrect={false}
          style={{ minHeight: 120, textAlignVertical: 'top', fontFamily: 'GeistMono_400Regular' }}
          placeholder={'{\n  "key": "value"\n}'}
          hint="Optional JSON object passed to the action handler"
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create mapping' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid || (mode === 'create' && topics.length === 0)}
        onPress={handleSubmit((v) => {
          // Parse the JSON params. Empty string → null; invalid JSON → bubble
          // up to the caller's error handler so the form stays put.
          let params: Record<string, unknown> | null = null;
          const raw = v.action_params_json.trim();
          if (raw) {
            try {
              const parsed = JSON.parse(raw);
              if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                params = parsed as Record<string, unknown>;
              } else {
                onValidationError?.('Action params must be a JSON object, not an array or primitive.');
                return;
              }
            } catch (e) {
              onValidationError?.(`Invalid JSON in action params: ${(e as Error).message}`);
              return;
            }
          }

          const input: MappingInput =
            mode === 'create'
              ? {
                  machine_topic_id: v.machine_topic_id,
                  description: v.description || null,
                  field_path: v.field_path || null,
                  action_type: v.action_type,
                  action_params: params,
                  condition_expr: v.condition_expr || null,
                  priority: v.priority,
                  is_active: v.is_active,
                }
              : {
                  description: v.description || null,
                  field_path: v.field_path || null,
                  action_type: v.action_type,
                  action_params: params,
                  condition_expr: v.condition_expr || null,
                  priority: v.priority,
                  is_active: v.is_active,
                };
          onSubmit(input);
        })}
      />
    </View>
  );
}
