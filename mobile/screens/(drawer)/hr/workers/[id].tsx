import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCrews, useSkills, useWageGroups, useWorker } from '@/hooks/queries/useHr';
import { useDeleteWorker, useSyncWorkerSkills, useUpdateWorker } from '@/hooks/mutations/hr';

function splitName(full: string): { first: string; last: string } {
  const trimmed = full.trim();
  if (!trimmed) return { first: '', last: '' };
  const idx = trimmed.indexOf(' ');
  if (idx === -1) return { first: trimmed, last: '' };
  return { first: trimmed.slice(0, idx), last: trimmed.slice(idx + 1).trim() };
}

export function EditWorkerScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useWorker(numericId);
  const crewsQuery = useCrews(false);
  const wageGroupsQuery = useWageGroups(false);
  const skillsQuery = useSkills();
  const updateMutation = useUpdateWorker();
  const skillsMutation = useSyncWorkerSkills();
  const deleteMutation = useDeleteWorker();

  const worker = query.data;
  const [code, setCode] = useState('');
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [crewId, setCrewId] = useState<number | null>(null);
  const [wageGroupId, setWageGroupId] = useState<number | null>(null);
  const [isActive, setIsActive] = useState(true);
  const [skillIds, setSkillIds] = useState<number[]>([]);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && worker) {
    const names = splitName(worker.name);
    setCode(worker.code);
    setFirstName(names.first);
    setLastName(names.last);
    setEmail(worker.email ?? '');
    setPhone(worker.phone ?? '');
    setCrewId(worker.crew_id ?? null);
    setWageGroupId(worker.wage_group_id ?? null);
    setIsActive(worker.is_active);
    setSkillIds(worker.skills?.map((s) => s.id) ?? []);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!firstName.trim()) e.firstName = 'Required';
    if (!lastName.trim()) e.lastName = 'Required';
    return e;
  }, [code, firstName, lastName]);

  const toggleSkill = (sid: number) =>
    setSkillIds((cur) => (cur.includes(sid) ? cur.filter((x) => x !== sid) : [...cur, sid]));

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !worker) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const pending = updateMutation.isPending || skillsMutation.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const name = [firstName.trim(), lastName.trim()].filter(Boolean).join(' ');
    updateMutation.mutate(
      {
        id: worker.id,
        payload: {
          code: code.trim(),
          name,
          email: email.trim() || undefined,
          phone: phone.trim() || undefined,
          crew_id: crewId ?? undefined,
          wage_group_id: wageGroupId ?? undefined,
          is_active: isActive,
        },
      },
      {
        onSuccess: () => {
          skillsMutation.mutate(
            { id: worker.id, skills: skillIds.map((sid) => ({ id: sid, level: 3 })) },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert(t('Skills update failed'), e.message),
            },
          );
        },
        onError: (e: Error) => Alert.alert(t('Could not update'), e.message),
      },
    );
  };

  const onDelete = () => {
    Alert.alert(t('Delete worker'), t('Delete "{{name}}"?', { name: worker.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete worker'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(worker.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  const crewOptions = [
    { value: '', label: t('— None —') },
    ...(crewsQuery.data ?? []).map((c) => ({ value: String(c.id), label: c.name })),
  ];
  const wageOptions = [
    { value: '', label: t('— None —') },
    ...(wageGroupsQuery.data ?? []).map((w) => ({ value: String(w.id), label: w.name })),
  ];
  const skills = skillsQuery.data ?? [];

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <View>
        <Text style={styles.h1}>{worker.name}</Text>
        <View style={styles.identityMeta}>
          <Mono size={11} color={colors.faint}>{worker.code}</Mono>
          <View style={[styles.pill, { backgroundColor: worker.is_active ? colors.doneBg : colors.chip }]}>
            <Mono size={9} color={worker.is_active ? colors.done : colors.faint} letterSpacing={0.6}>
              {worker.is_active ? t('Active').toUpperCase() : t('Inactive').toUpperCase()}
            </Mono>
          </View>
        </View>
      </View>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
      <View style={styles.row}>
        <View style={{ flex: 1 }}>
          <Field label="First name" value={firstName} onChangeText={setFirstName} error={errors.firstName} required />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Last name" value={lastName} onChangeText={setLastName} error={errors.lastName} required />
        </View>
      </View>
      <Field label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" mono />
      <Field label="Phone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" mono />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Crew').toUpperCase()}</Mono>
        <Dropdown value={crewId == null ? '' : String(crewId)} onChange={(v) => setCrewId(v ? Number(v) : null)} options={crewOptions} />
      </View>
      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Wage group').toUpperCase()}</Mono>
        <Dropdown value={wageGroupId == null ? '' : String(wageGroupId)} onChange={(v) => setWageGroupId(v ? Number(v) : null)} options={wageOptions} />
      </View>

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Skills').toUpperCase()}</Mono>
        {skills.length === 0 ? (
          <Text style={styles.muted}>{t('No skills defined yet')}</Text>
        ) : (
          skills.map((sk) => {
            const checked = skillIds.includes(sk.id);
            return (
              <Pressable key={sk.id} onPress={() => toggleSkill(sk.id)} style={styles.checkRow}>
                <View style={[styles.checkbox, checked && styles.checkboxOn]}>
                  {checked ? <FontAwesome name="check" size={11} color="#fff" /> : null}
                </View>
                <Text style={styles.checkLabel}>{sk.name}</Text>
              </Pressable>
            );
          })
        )}
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Show in operator dropdowns and crew rosters')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>

      <View style={styles.danger}>
        <Button
          title={t('Delete worker')}
          variant="danger"
          leftIcon={<FontAwesome name="trash" size={13} color={colors.blocked} />}
          loading={deleteMutation.isPending}
          onPress={onDelete}
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  identityMeta: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 6 },
  pill: { paddingVertical: 3, paddingHorizontal: 8, borderRadius: radius.pill },
  row: { flexDirection: 'row', gap: 10 },
  muted: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8 },
  checkbox: { width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  checkboxOn: { backgroundColor: colors.accent, borderColor: colors.accent },
  checkLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  danger: { marginTop: 12, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2, paddingTop: 16 },
});
