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
import type { MachineConnection, MachineTopic, TopicInput } from '@/api/connectivity';

const PAYLOAD_FORMATS = [
  { id: 'json', label: 'JSON' },
  { id: 'plain', label: 'Plain' },
  { id: 'csv', label: 'CSV' },
  { id: 'hex', label: 'HEX' },
] as const;

export const topicSchema = z.object({
  machine_connection_id: z.number().int().positive('Pick a connection'),
  topic_pattern: nonEmpty('Topic pattern is required'),
  payload_format: z.enum(['json', 'plain', 'csv', 'hex']),
  description: z.string().trim(),
  is_active: z.boolean(),
});

export type TopicFormValues = z.infer<typeof topicSchema>;

interface Props {
  initial?: Partial<MachineTopic>;
  /** Connections to pick from. Hidden on edit since topics don't move between brokers. */
  connections: MachineConnection[];
  /** Lock the connection picker (e.g. opening "new topic" from a specific broker). */
  lockedConnectionId?: number;
  mode: 'create' | 'edit';
  submitting?: boolean;
  onSubmit: (input: TopicInput) => void;
}

export function TopicForm({ initial, connections, lockedConnectionId, mode, submitting, onSubmit }: Props) {
  const { t } = useTranslation();
  const defaultConnId =
    lockedConnectionId ??
    initial?.machine_connection_id ??
    connections[0]?.id ??
    0;

  const { control, handleSubmit, formState: { isValid } } = useForm<TopicFormValues>({
    resolver: zodResolver(topicSchema),
    mode: 'onChange',
    defaultValues: {
      machine_connection_id: defaultConnId,
      topic_pattern: initial?.topic_pattern ?? '',
      payload_format: (initial?.payload_format as TopicFormValues['payload_format']) ?? 'json',
      description: initial?.description ?? '',
      is_active: initial?.is_active ?? true,
    },
  });

  const showConnPicker = mode === 'create' && !lockedConnectionId;

  return (
    <View style={{ gap: 14 }}>
      {showConnPicker ? (
        <Card style={{ gap: 12 }}>
          <SectionLabel>Connection</SectionLabel>
          <Controller
            control={control}
            name="machine_connection_id"
            render={({ field: { value, onChange } }) => (
              <ChipRow>
                {connections.map((c) => (
                  <SelectionChip
                    key={c.id}
                    label={c.name}
                    active={c.id === value}
                    onPress={() => onChange(c.id)}
                  />
                ))}
              </ChipRow>
            )}
          />
          {connections.length === 0 ? (
            <Mono size={11}>{t('No connections yet — create one first').toUpperCase()}</Mono>
          ) : null}
        </Card>
      ) : null}

      <Card style={{ gap: 12 }}>
        <SectionLabel>Topic</SectionLabel>
        <ControlledField
          control={control}
          name="topic_pattern"
          label="Topic pattern"
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="plant/wsz/line-01/state"
          hint="MQTT wildcards (+, #) are accepted"
        />
        <ControlledField
          control={control}
          name="description"
          label="Description"
          multiline
          numberOfLines={2}
          style={{ minHeight: 60, textAlignVertical: 'top' }}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Payload format</SectionLabel>
        <Controller
          control={control}
          name="payload_format"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {PAYLOAD_FORMATS.map((f) => (
                <SelectionChip
                  key={f.id}
                  label={t(f.label)}
                  active={f.id === value}
                  onPress={() => onChange(f.id)}
                />
              ))}
            </ChipRow>
          )}
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create topic' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid || (mode === 'create' && connections.length === 0)}
        onPress={handleSubmit((v) => {
          // machine_connection_id is omitted from the payload on edit — the
          // backend doesn't accept moving topics between brokers.
          const input: TopicInput =
            mode === 'create'
              ? {
                  machine_connection_id: v.machine_connection_id,
                  topic_pattern: v.topic_pattern,
                  payload_format: v.payload_format,
                  description: v.description || null,
                  is_active: v.is_active,
                }
              : {
                  topic_pattern: v.topic_pattern,
                  payload_format: v.payload_format,
                  description: v.description || null,
                  is_active: v.is_active,
                };
          onSubmit(input);
        })}
      />
    </View>
  );
}
