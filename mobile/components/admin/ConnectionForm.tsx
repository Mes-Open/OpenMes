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
import { ControlledSwitch } from '@/components/ui/ControlledSwitch';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import { nonEmpty } from '@/lib/forms/zod';
import type { ConnectionInput, MachineConnection } from '@/api/connectivity';

// Number-as-string fields: the input is a TextInput so RHF holds a string,
// but the API expects a real integer. z.coerce.number() handles the string→
// number conversion in the resolver so we don't need a manual transform.
const intField = (label = 'Required', min = 1, max = Number.MAX_SAFE_INTEGER) =>
  z.coerce.number({ message: label }).int(label).min(min, label).max(max, label);

const QOS_CHOICES = [
  { id: '0', label: 'QoS 0' },
  { id: '1', label: 'QoS 1' },
  { id: '2', label: 'QoS 2' },
] as const;

export const connectionSchema = z.object({
  name: nonEmpty('Connection name is required'),
  description: z.string().trim(),
  is_active: z.boolean(),
  broker_host: nonEmpty('Broker host is required'),
  broker_port: intField('Broker port is required', 1, 65535),
  client_id: z.string().trim(),
  username: z.string().trim(),
  // Password is stored as plain string on the form; on submit we only send it
  // when non-empty (the server keeps the existing credential otherwise).
  password: z.string(),
  use_tls: z.boolean(),
  qos_default: z.coerce.number().int().min(0).max(2),
  clean_session: z.boolean(),
  keep_alive_seconds: intField('Keep alive (seconds) is required', 5, 3600),
  connect_timeout: intField('Connect timeout is required', 1, 120),
  reconnect_delay_seconds: intField('Reconnect delay is required', 1, 300),
});

// Input shape (what react-hook-form holds): string text inputs.
// Output shape (after zod resolution): numbers + booleans + the union types.
export type ConnectionFormInput = z.input<typeof connectionSchema>;
export type ConnectionFormValues = z.output<typeof connectionSchema>;

interface Props {
  initial?: Partial<MachineConnection> & {
    mqtt_connection?: Partial<MachineConnection['mqtt_connection']>;
  };
  mode: 'create' | 'edit';
  submitting?: boolean;
  onSubmit: (input: ConnectionInput) => void;
}

export function ConnectionForm({ initial, mode, submitting, onSubmit }: Props) {
  const { t } = useTranslation();
  const mqtt = initial?.mqtt_connection ?? null;
  // RHF defaults are strings even for numbers so the text input stays
  // controlled; zod transforms back to numbers on submit.
  const { control, handleSubmit, formState: { isValid } } = useForm<
    ConnectionFormInput,
    unknown,
    ConnectionFormValues
  >({
    resolver: zodResolver(connectionSchema),
    mode: 'onChange',
    defaultValues: {
      name: initial?.name ?? '',
      description: initial?.description ?? '',
      is_active: initial?.is_active ?? true,
      broker_host: mqtt?.broker_host ?? '',
      broker_port: String(mqtt?.broker_port ?? 1883),
      client_id: mqtt?.client_id ?? '',
      username: mqtt?.username ?? '',
      password: '',
      use_tls: mqtt?.use_tls ?? false,
      qos_default: String(mqtt?.qos_default ?? 1),
      clean_session: mqtt?.clean_session ?? true,
      keep_alive_seconds: '60',
      connect_timeout: '10',
      reconnect_delay_seconds: '5',
    },
  });

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Identity</SectionLabel>
        <ControlledField
          control={control}
          name="name"
          label="Connection name"
          autoCorrect={false}
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
        <SectionLabel>Broker</SectionLabel>
        <ControlledField
          control={control}
          name="broker_host"
          label="Host"
          autoCapitalize="none"
          autoCorrect={false}
          placeholder="mqtt.factory.local"
        />
        <ControlledField
          control={control}
          name="broker_port"
          label="Port"
          keyboardType="number-pad"
          placeholder="1883"
        />
        <ControlledField
          control={control}
          name="client_id"
          label="Client ID"
          autoCapitalize="none"
          autoCorrect={false}
          hint="Leave blank to use a generated ID"
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Authentication</SectionLabel>
        <ControlledField
          control={control}
          name="username"
          label="Username"
          autoCapitalize="none"
          autoCorrect={false}
        />
        <ControlledField
          control={control}
          name="password"
          label="Password"
          secureTextEntry
          hint={mode === 'edit' ? 'Leave blank to keep current password' : undefined}
        />
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <Mono size={11}>{t('Use TLS').toUpperCase()}</Mono>
          <ControlledSwitch control={control} name="use_tls" />
        </View>
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Defaults</SectionLabel>
        <Mono size={10.5}>{t('Default QoS').toUpperCase()}</Mono>
        <Controller
          control={control}
          name="qos_default"
          render={({ field: { value, onChange } }) => (
            <ChipRow>
              {QOS_CHOICES.map((q) => (
                <SelectionChip
                  key={q.id}
                  label={q.label}
                  active={String(value) === q.id}
                  onPress={() => onChange(q.id)}
                />
              ))}
            </ChipRow>
          )}
        />
        <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
          <Mono size={11}>{t('Clean session').toUpperCase()}</Mono>
          <ControlledSwitch control={control} name="clean_session" />
        </View>
        <ControlledField
          control={control}
          name="keep_alive_seconds"
          label="Keep alive (seconds)"
          keyboardType="number-pad"
        />
        <ControlledField
          control={control}
          name="connect_timeout"
          label="Connect timeout (seconds)"
          keyboardType="number-pad"
        />
        <ControlledField
          control={control}
          name="reconnect_delay_seconds"
          label="Reconnect delay (seconds)"
          keyboardType="number-pad"
        />
      </Card>

      <ActiveToggleCard control={control} name="is_active" />

      <Button
        title={mode === 'create' ? 'Create connection' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!isValid}
        onPress={handleSubmit((v) => {
          const input: ConnectionInput = {
            name: v.name,
            description: v.description || null,
            is_active: v.is_active,
            broker_host: v.broker_host,
            broker_port: v.broker_port,
            client_id: v.client_id || null,
            username: v.username || null,
            // Omit password on edit when blank — server preserves the
            // existing credential. Always include on create (may be empty).
            ...(mode === 'create' || v.password
              ? { password: v.password || null }
              : {}),
            use_tls: v.use_tls,
            qos_default: v.qos_default as 0 | 1 | 2,
            clean_session: v.clean_session,
            keep_alive_seconds: v.keep_alive_seconds,
            connect_timeout: v.connect_timeout,
            reconnect_delay_seconds: v.reconnect_delay_seconds,
          };
          onSubmit(input);
        })}
      />
    </View>
  );
}
