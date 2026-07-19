/**
 * Crew edit — inline form (Code / Name / Leader / Description / Active) plus the
 * assigned-workers collection and activate/delete actions. Mirrors the web crew
 * edit page. Reads via useCrew / useCrewWorkers; writes via the hr mutations.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Button, Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useUsers } from '@/hooks/queries/useUsers';
import { useCrew, useCrewWorkers } from '@/hooks/queries/useHr';
import { useDeleteCrew, useToggleCrewActive, useUpdateCrew } from '@/hooks/mutations/hr';

export function EditCrewScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const { t } = useTranslation();
  const router = useRouter();

  const query = useCrew(numericId);
  const workersQuery = useCrewWorkers(numericId);
  const usersQ = useUsers({ role: 'Supervisor' });
  const update = useUpdateCrew();
  const del = useDeleteCrew();
  const toggle = useToggleCrewActive();

  const crew = query.data;

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [leaderId, setLeaderId] = useState<number | null>(null);
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && crew) {
    setCode(crew.code);
    setName(crew.name);
    setDescription(crew.description ?? '');
    setLeaderId(crew.leader_id ?? null);
    setIsActive(crew.is_active);
    setSeeded(true);
  }

  const leaderOptions = useMemo(
    () => [
      { value: '', label: t('— None —') },
      ...(usersQ.data?.data ?? []).map((u) => ({ value: String(u.id), label: u.name ?? u.username })),
    ],
    [usersQ.data, t],
  );

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (query.isLoading && !crew) return <LoadingState />;
  if (query.isError || !crew) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const workers = workersQuery.data ?? [];

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    update.mutate(
      {
        id: crew.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          description: description.trim() || undefined,
          leader_id: leaderId ?? undefined,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const confirmDelete = () => {
    Alert.alert(t('Delete crew'), t('Delete "{{name}}"?', { name: crew.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          del.mutate(crew.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Text style={styles.h1}>{t('Edit crew')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required autoCapitalize="characters" autoCorrect={false} mono />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Leader').toUpperCase()}</Mono>
        <Dropdown
          value={leaderId == null ? '' : String(leaderId)}
          onChange={(v) => setLeaderId(v ? Number(v) : null)}
          placeholder={t('— None —')}
          options={leaderOptions}
        />
      </View>

      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Show in operator dropdowns and crew rosters')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button variant="ghost" onPress={() => router.back()}>{t('Cancel')}</Button>
        <View style={{ flex: 1 }} />
        <Button onPress={onSave} loading={update.isPending} disabled={update.isPending}>{t('Save')}</Button>
      </View>

      {/* Workers */}
      <View style={{ gap: 8, marginTop: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>
          {`${t('Workers').toUpperCase()} · ${workers.length}`}
        </Mono>
        {workers.length === 0 ? (
          <Text style={styles.empty}>{t('No workers assigned.')}</Text>
        ) : (
          <View style={styles.box}>
            {workers.map((w, i) => (
              <View
                key={w.id}
                style={[styles.kvRow, i < workers.length - 1 ? styles.kvBorder : null]}>
                <Text numberOfLines={1} style={styles.kvName}>{w.name}</Text>
                <Mono size={11} color={colors.faint}>{w.code}</Mono>
              </View>
            ))}
          </View>
        )}
      </View>

      {/* Danger zone */}
      <View style={styles.dangerRow}>
        <Button
          variant="outline"
          onPress={() => toggle.mutate(crew.id, { onError: (e: Error) => Alert.alert(t('Failed'), e.message) })}
          loading={toggle.isPending}>
          {crew.is_active ? t('Deactivate') : t('Activate')}
        </Button>
        <View style={{ flex: 1 }} />
        <Button variant="danger" onPress={confirmDelete} loading={del.isPending}>{t('Delete crew')}</Button>
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
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  box: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md },
  kvRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: 10, paddingVertical: 11, paddingHorizontal: 14 },
  kvBorder: { borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  kvName: { flex: 1, fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 4 },
  dangerRow: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 8 },
});
