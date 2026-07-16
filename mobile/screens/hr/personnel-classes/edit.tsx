import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useSkills } from '@/hooks/queries/useHr';
import { usePersonnelClass, useUpdatePersonnelClass } from '@/hooks/queries/usePersonnel';
import type { CreatePersonnelClassPayload } from '@/api/personnel';

export function EditPersonnelClassScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = usePersonnelClass(Number.isFinite(id) ? id : undefined);
  const skillsQ = useSkills();
  const m = useUpdatePersonnelClass();

  const pc = q.data;
  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [skillIds, setSkillIds] = useState<number[]>([]);
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && pc) {
    setCode(pc.code);
    setName(pc.name);
    setDescription(pc.description ?? '');
    setSkillIds(pc.required_skill_ids ?? []);
    setIsActive(pc.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Code is required';
    if (!name.trim()) e.name = 'Name is required';
    return e;
  }, [code, name]);

  const toggleSkill = (sid: number) =>
    setSkillIds((cur) => (cur.includes(sid) ? cur.filter((x) => x !== sid) : [...cur, sid]));

  if (q.isLoading || skillsQ.isLoading) return <LoadingState />;
  if (q.isError || !pc) return <ErrorState error={q.error ?? new Error('Class not found')} onRetry={q.refetch} />;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const payload: CreatePersonnelClassPayload = {
      code: code.trim(),
      name: name.trim(),
      description: description.trim() || undefined,
      required_skill_ids: skillIds,
      is_active: isActive,
    };
    m.mutate(
      { id, payload },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not save changes'), e.message),
      },
    );
  };

  const skills = skillsQ.data ?? [];

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit personnel class')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} hint="ISA-95 personnel class code (e.g. OP-A1)" />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={2} style={{ minHeight: 60, textAlignVertical: 'top' }} />

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Required skills').toUpperCase()}</Mono>
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
        {skills.length > 0 ? (
          <Mono size={9} color={colors.faint}>{t('Workers must hold every selected skill')}</Mono>
        ) : null}
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Available for worker assignment')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={m.isPending} disabled={m.isPending} />
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
