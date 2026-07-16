/**
 * New worker — inline form mirroring the web worker form (Code / Name / Email /
 * Phone / Crew / Wage Group / Active + skills-and-level matrix). Writes through
 * useCreateWorker.
 */
import { useMemo, useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { Button, Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { useCrews, useSkills, useWageGroups } from '@/hooks/queries/useHr';
import { useCreateWorker } from '@/hooks/mutations/hr';

const LEVELS = [1, 2, 3, 4, 5].map((l) => ({ value: String(l), label: String(l) }));

export function NewWorkerScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const create = useCreateWorker();

  const crewsQ = useCrews(false);
  const wageGroupsQ = useWageGroups(false);
  const skillsQ = useSkills();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [crewId, setCrewId] = useState<number | null>(null);
  const [wageGroupId, setWageGroupId] = useState<number | null>(null);
  const [isActive, setIsActive] = useState(true);
  const [skills, setSkills] = useState<Map<number, number>>(new Map());

  const crewOptions = useMemo(
    () => [{ value: '', label: t('— None —') }, ...(crewsQ.data ?? []).map((c) => ({ value: String(c.id), label: c.name }))],
    [crewsQ.data, t],
  );
  const wageOptions = useMemo(
    () => [{ value: '', label: t('— None —') }, ...(wageGroupsQ.data ?? []).map((g) => ({ value: String(g.id), label: g.name }))],
    [wageGroupsQ.data, t],
  );

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  const toggleSkill = (id: number) => {
    setSkills((cur) => {
      const next = new Map(cur);
      if (next.has(id)) next.delete(id);
      else next.set(id, 1);
      return next;
    });
  };
  const setSkillLevel = (id: number, level: number) => {
    setSkills((cur) => new Map(cur).set(id, level));
  };

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const skillList = [...skills].map(([id, level]) => ({ id, level }));
    create.mutate(
      {
        code: code.trim(),
        name: name.trim(),
        email: email.trim() || undefined,
        phone: phone.trim() || undefined,
        crew_id: crewId ?? undefined,
        wage_group_id: wageGroupId ?? undefined,
        is_active: isActive,
        skills: skillList.length ? skillList : undefined,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{t('New worker')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required autoCapitalize="characters" autoCorrect={false} mono />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" mono />
      <Field label="Phone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" mono />

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Crew').toUpperCase()}</Mono>
        <Dropdown
          value={crewId == null ? '' : String(crewId)}
          onChange={(v) => setCrewId(v ? Number(v) : null)}
          placeholder={t('— None —')}
          options={crewOptions}
        />
      </View>

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Wage Group').toUpperCase()}</Mono>
        <Dropdown
          value={wageGroupId == null ? '' : String(wageGroupId)}
          onChange={(v) => setWageGroupId(v ? Number(v) : null)}
          placeholder={t('— None —')}
          options={wageOptions}
        />
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Show in operator dropdowns and crew rosters')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Skills & level (1-5)').toUpperCase()}</Mono>
        <View style={styles.box}>
          {(skillsQ.data ?? []).length === 0 ? (
            <Text style={styles.empty}>{t('No skills defined.')}</Text>
          ) : (
            (skillsQ.data ?? []).map((sk, i, arr) => {
              const on = skills.has(sk.id);
              return (
                <View
                  key={sk.id}
                  style={[styles.skillRow, i < arr.length - 1 ? styles.skillBorder : null]}>
                  <Pressable onPress={() => toggleSkill(sk.id)} style={styles.checkRow}>
                    <View style={[styles.checkbox, on && styles.checkboxOn]}>
                      {on ? <FontAwesome name="check" size={11} color="#fff" /> : null}
                    </View>
                    <Text numberOfLines={1} style={styles.skillName}>{sk.name}</Text>
                  </Pressable>
                  {on ? (
                    <View style={{ width: 72 }}>
                      <Dropdown
                        value={String(skills.get(sk.id))}
                        onChange={(v) => setSkillLevel(sk.id, Number(v))}
                        options={LEVELS}
                      />
                    </View>
                  ) : null}
                </View>
              );
            })
          )}
        </View>
      </View>

      <View style={styles.actions}>
        <Button variant="ghost" onPress={() => router.back()}>{t('Cancel')}</Button>
        <View style={{ flex: 1 }} />
        <Button onPress={onSave} loading={create.isPending} disabled={create.isPending}>{t('Create Worker')}</Button>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md },
  skillRow: { flexDirection: 'row', alignItems: 'center', gap: 10, paddingVertical: 8, paddingHorizontal: 12 },
  skillBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  checkRow: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: 12 },
  checkbox: { width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  checkboxOn: { backgroundColor: colors.accent, borderColor: colors.accent },
  skillName: { flex: 1, fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, padding: 12 },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
