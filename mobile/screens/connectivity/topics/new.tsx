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
import { useConnections, useCreateTopic } from '@/hooks/queries/useConnectivity';
import type { MachineConnection, MachineTopic, TopicInput } from '@/api/connectivity';

const PAYLOAD_FORMATS = [
  { value: 'json', label: 'JSON' },
  { value: 'plain', label: 'Plain' },
  { value: 'csv', label: 'CSV' },
  { value: 'hex', label: 'HEX' },
] as const;

export function NewTopicScreen() {
  const router = useRouter();
  // Optional ?connection_id pre-selects + locks the picker — used when
  // navigating in from a specific connection's detail page.
  const { connection_id } = useLocalSearchParams<{ connection_id?: string }>();
  const lockedId = connection_id ? Number(connection_id) : undefined;

  const connsQ = useConnections(true);
  const m = useCreateTopic();

  if (connsQ.isLoading) return <LoadingState />;

  return (
    <TopicFormFields
      mode="create"
      connections={connsQ.data ?? []}
      lockedConnectionId={lockedId}
      submitting={m.isPending}
      onSubmit={(input) =>
        m.mutate(input, {
          onSuccess: () => router.back(),
          onError: (e: Error) => Alert.alert('Could not create topic', e.message),
        })
      }
    />
  );
}

interface FormProps {
  initial?: Partial<MachineTopic>;
  connections: MachineConnection[];
  lockedConnectionId?: number;
  mode: 'create' | 'edit';
  submitting?: boolean;
  onSubmit: (input: TopicInput) => void;
}

/**
 * Spec-styled MQTT topic form. Shared by topics/new.tsx and topics/edit.tsx so
 * the screens stay free of the old-design TopicForm composite.
 */
export function TopicFormFields({
  initial,
  connections,
  lockedConnectionId,
  mode,
  submitting,
  onSubmit,
}: FormProps) {
  const { t } = useTranslation();
  const router = useRouter();

  const defaultConnId =
    lockedConnectionId ?? initial?.machine_connection_id ?? connections[0]?.id ?? 0;

  const [connId, setConnId] = useState<number>(defaultConnId);
  const [pattern, setPattern] = useState(initial?.topic_pattern ?? '');
  const [format, setFormat] = useState<string>(initial?.payload_format ?? 'json');
  const [description, setDescription] = useState(initial?.description ?? '');
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);

  const showConnPicker = mode === 'create' && !lockedConnectionId;

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!pattern.trim()) e.pattern = 'Topic pattern is required';
    if (mode === 'create' && !(connId > 0)) e.connId = 'Pick a connection';
    return e;
  }, [pattern, connId, mode]);

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const base = {
      topic_pattern: pattern.trim(),
      payload_format: format as TopicInput['payload_format'],
      description: description.trim() || null,
      is_active: isActive,
    };
    // machine_connection_id is omitted on edit — the backend doesn't accept
    // moving topics between brokers.
    const input: TopicInput =
      mode === 'create' ? { machine_connection_id: connId, ...base } : base;
    onSubmit(input);
  };

  const disabled =
    !!submitting ||
    Object.keys(errors).length > 0 ||
    (mode === 'create' && connections.length === 0);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{mode === 'create' ? t('New topic') : t('Edit topic')}</Text>

      {showConnPicker ? (
        <View style={{ gap: 6 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Connection').toUpperCase()} *</Mono>
          <Dropdown
            value={String(connId)}
            onChange={(v) => setConnId(Number(v))}
            placeholder={t('Select connection')}
            options={connections.map((c) => ({ value: String(c.id), label: c.name }))}
          />
          {connections.length === 0 ? (
            <Text style={styles.muted}>{t('No connections yet — create one first')}</Text>
          ) : null}
          {errors.connId ? <Text style={styles.error}>{t(errors.connId)}</Text> : null}
        </View>
      ) : null}

      <Field
        label="Topic pattern"
        value={pattern}
        onChangeText={setPattern}
        error={errors.pattern}
        required
        mono
        autoCapitalize="none"
        autoCorrect={false}
        placeholder="plant/wsz/line-01/state"
        hint="MQTT wildcards (+, #) are accepted"
      />
      <Field
        label="Description"
        value={description}
        onChangeText={setDescription}
        multiline
        numberOfLines={2}
        style={{ minHeight: 60, textAlignVertical: 'top' }}
      />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Payload format').toUpperCase()} *</Mono>
        <Dropdown value={format} onChange={(v) => setFormat(v as string)} options={PAYLOAD_FORMATS.map((f) => ({ value: f.value, label: f.label }))} />
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Listen on this topic')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={mode === 'create' ? t('Create topic') : t('Save changes')} onPress={onSave} loading={!!submitting} disabled={disabled} />
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
