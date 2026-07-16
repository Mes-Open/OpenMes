import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { useLines, useRoles } from '@/hooks/queries/useUsers';
import { useCreateUser } from '@/hooks/mutations/users';

export function NewUserScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const createMutation = useCreateUser();
  const roles = useRoles();
  const lines = useLines();

  const [name, setName] = useState('');
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [accountType, setAccountType] = useState<'user' | 'workstation'>('user');
  const [role, setRole] = useState('Operator');
  const [password, setPassword] = useState('');
  const [forcePasswordChange, setForcePasswordChange] = useState(false);
  const [lineIds, setLineIds] = useState<number[]>([]);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (!username.trim()) e.username = 'Required';
    if (!email.trim()) e.email = 'Required';
    if (password.length < 8) e.password = 'At least 8 characters';
    return e;
  }, [name, username, email, password]);

  const toggleLine = (id: number) =>
    setLineIds((cur) => (cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id]));

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    createMutation.mutate(
      {
        name: name.trim(),
        username: username.trim(),
        email: email.trim(),
        password,
        account_type: accountType,
        role: accountType === 'user' ? role : undefined,
        force_password_change: forcePasswordChange,
        line_ids: lineIds,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create user'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New Account')}</Text>

      <Field label="Display Name" value={name} onChangeText={setName} error={errors.name} required placeholder="Karolina Wójcik" />
      <Field label="Username" value={username} onChangeText={setUsername} error={errors.username} required mono autoCapitalize="none" autoCorrect={false} hint="Lowercase, no spaces. Must be unique." />
      <Field label="Email" value={email} onChangeText={setEmail} error={errors.email} required mono keyboardType="email-address" autoCapitalize="none" autoCorrect={false} />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Account type').toUpperCase()} *</Mono>
        <Dropdown
          value={accountType}
          onChange={(v) => setAccountType(v as 'user' | 'workstation')}
          options={[
            { value: 'user', label: t('Personal account') },
            { value: 'workstation', label: t('Workstation kiosk') },
          ]}
        />
      </View>

      {accountType === 'user' ? (
        <View style={{ gap: 6 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Role').toUpperCase()} *</Mono>
          <Dropdown
            value={role}
            onChange={(v) => setRole(v as string)}
            options={(roles.data ?? []).map((r) => ({ value: r.name, label: r.name }))}
          />
        </View>
      ) : null}

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Line assignments').toUpperCase()}</Mono>
        {lines.isLoading ? (
          <Text style={styles.muted}>{t('Loading…')}</Text>
        ) : (lines.data ?? []).length === 0 ? (
          <Text style={styles.muted}>{t('No lines exist yet')}</Text>
        ) : (
          (lines.data ?? []).map((l) => {
            const checked = lineIds.includes(l.id);
            return (
              <Pressable key={l.id} onPress={() => toggleLine(l.id)} style={styles.checkRow}>
                <View style={[styles.checkbox, checked && styles.checkboxOn]}>
                  {checked ? <FontAwesome name="check" size={11} color="#fff" /> : null}
                </View>
                <Text style={styles.checkLabel}>{l.name}</Text>
              </Pressable>
            );
          })
        )}
      </View>

      <Field label="Temporary password" value={password} onChangeText={setPassword} error={errors.password} required mono secureTextEntry placeholder="At least 8 characters" />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Send setup email')}</Text>
          <Mono size={9} color={colors.faint}>{t('EMAIL PASSWORD + LOGIN URL ON CREATE')}</Mono>
        </View>
        <Switch value={forcePasswordChange} onValueChange={setForcePasswordChange} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Create user')} onPress={onSave} loading={createMutation.isPending} disabled={createMutation.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  muted: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8 },
  checkbox: { width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  checkboxOn: { backgroundColor: colors.accent, borderColor: colors.accent },
  checkLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
