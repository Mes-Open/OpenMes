import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { useCreateConnection } from '@/hooks/queries/useConnectivity';
import type { ConnectionInput, MachineConnection } from '@/api/connectivity';

const QOS_OPTIONS = [
  { value: '0', label: 'QoS 0' },
  { value: '1', label: 'QoS 1' },
  { value: '2', label: 'QoS 2' },
];

export function NewConnectionScreen() {
  const router = useRouter();
  const m = useCreateConnection();

  return (
    <ConnectionFormFields
      mode="create"
      submitting={m.isPending}
      onSubmit={(input) =>
        m.mutate(input, {
          onSuccess: () => router.back(),
          onError: (e: Error) => Alert.alert('Could not create connection', e.message),
        })
      }
    />
  );
}

interface FormProps {
  initial?: Partial<MachineConnection> & {
    mqtt_connection?: Partial<MachineConnection['mqtt_connection']>;
  };
  mode: 'create' | 'edit';
  submitting?: boolean;
  onSubmit: (input: ConnectionInput) => void;
}

/**
 * Spec-styled MQTT connection form. Shared by connections/new.tsx and
 * connections/edit.tsx so the screens stay free of the old-design ConnectionForm.
 */
export function ConnectionFormFields({ initial, mode, submitting, onSubmit }: FormProps) {
  const { t } = useTranslation();
  const router = useRouter();
  const mqtt = initial?.mqtt_connection ?? null;

  const [name, setName] = useState(initial?.name ?? '');
  const [description, setDescription] = useState(initial?.description ?? '');
  const [isActive, setIsActive] = useState(initial?.is_active ?? true);
  const [brokerHost, setBrokerHost] = useState(mqtt?.broker_host ?? '');
  const [brokerPort, setBrokerPort] = useState(String(mqtt?.broker_port ?? 1883));
  const [clientId, setClientId] = useState(mqtt?.client_id ?? '');
  const [username, setUsername] = useState(mqtt?.username ?? '');
  const [password, setPassword] = useState('');
  const [useTls, setUseTls] = useState(mqtt?.use_tls ?? false);
  const [qos, setQos] = useState(String(mqtt?.qos_default ?? 1));
  const [cleanSession, setCleanSession] = useState(mqtt?.clean_session ?? true);
  const [keepAlive, setKeepAlive] = useState('60');
  const [connectTimeout, setConnectTimeout] = useState('10');
  const [reconnectDelay, setReconnectDelay] = useState('5');

  const intInRange = (v: string, min: number, max: number) => {
    const n = Number(v);
    return v.trim() !== '' && Number.isInteger(n) && n >= min && n <= max;
  };

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Connection name is required';
    if (!brokerHost.trim()) e.brokerHost = 'Broker host is required';
    if (!intInRange(brokerPort, 1, 65535)) e.brokerPort = 'Broker port is required';
    if (!intInRange(keepAlive, 5, 3600)) e.keepAlive = 'Keep alive (seconds) is required';
    if (!intInRange(connectTimeout, 1, 120)) e.connectTimeout = 'Connect timeout is required';
    if (!intInRange(reconnectDelay, 1, 300)) e.reconnectDelay = 'Reconnect delay is required';
    return e;
  }, [name, brokerHost, brokerPort, keepAlive, connectTimeout, reconnectDelay]);

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input: ConnectionInput = {
      name: name.trim(),
      description: description.trim() || null,
      is_active: isActive,
      broker_host: brokerHost.trim(),
      broker_port: Number(brokerPort),
      client_id: clientId.trim() || null,
      username: username.trim() || null,
      // Omit password on edit when blank — server preserves the existing
      // credential. Always include on create (may be empty).
      ...(mode === 'create' || password ? { password: password || null } : {}),
      use_tls: useTls,
      qos_default: Number(qos) as 0 | 1 | 2,
      clean_session: cleanSession,
      keep_alive_seconds: Number(keepAlive),
      connect_timeout: Number(connectTimeout),
      reconnect_delay_seconds: Number(reconnectDelay),
    };
    onSubmit(input);
  };

  const disabled = !!submitting || Object.keys(errors).length > 0;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{mode === 'create' ? t('New connection') : t('Edit connection')}</Text>

      <Text style={styles.section}>{t('General').toUpperCase()}</Text>
      <Field label="Connection name" value={name} onChangeText={setName} error={errors.name} required autoCorrect={false} />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={2} style={{ minHeight: 60, textAlignVertical: 'top' }} />

      <Text style={styles.section}>{t('Broker').toUpperCase()}</Text>
      <Field label="Host" value={brokerHost} onChangeText={setBrokerHost} error={errors.brokerHost} required mono autoCapitalize="none" autoCorrect={false} placeholder="mqtt.factory.local" />
      <Field label="Port" value={brokerPort} onChangeText={setBrokerPort} error={errors.brokerPort} required mono keyboardType="number-pad" placeholder="1883" />
      <Field label="Client ID" value={clientId} onChangeText={setClientId} mono autoCapitalize="none" autoCorrect={false} hint="Leave blank to use a generated ID" />

      <Text style={styles.section}>{t('Authentication').toUpperCase()}</Text>
      <Field label="Username" value={username} onChangeText={setUsername} autoCapitalize="none" autoCorrect={false} />
      <Field label="Password" value={password} onChangeText={setPassword} secureTextEntry hint={mode === 'edit' ? 'Leave blank to keep current password' : undefined} />
      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Use TLS')}</Text>
          <Mono size={9} color={colors.faint}>{t('Encrypt the broker connection')}</Mono>
        </View>
        <Switch value={useTls} onValueChange={setUseTls} />
      </View>

      <Text style={styles.section}>{t('Defaults').toUpperCase()}</Text>
      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Default QoS').toUpperCase()}</Mono>
        <Dropdown value={qos} onChange={(v) => setQos(v as string)} options={QOS_OPTIONS} />
      </View>
      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Clean session')}</Text>
          <Mono size={9} color={colors.faint}>{t('Start a fresh session on each connect')}</Mono>
        </View>
        <Switch value={cleanSession} onValueChange={setCleanSession} />
      </View>
      <Field label="Keep alive (seconds)" value={keepAlive} onChangeText={setKeepAlive} error={errors.keepAlive} mono keyboardType="number-pad" />
      <Field label="Connect timeout (seconds)" value={connectTimeout} onChangeText={setConnectTimeout} error={errors.connectTimeout} mono keyboardType="number-pad" />
      <Field label="Reconnect delay (seconds)" value={reconnectDelay} onChangeText={setReconnectDelay} error={errors.reconnectDelay} mono keyboardType="number-pad" />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Start listening on daemon start')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={mode === 'create' ? t('Create connection') : t('Save changes')} onPress={onSave} loading={!!submitting} disabled={disabled} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  section: { fontFamily: fonts.mono.native.regular, fontSize: 10, letterSpacing: 1.2, color: colors.faint, marginTop: 4, marginBottom: -4 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
