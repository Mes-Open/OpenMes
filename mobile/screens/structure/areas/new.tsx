/**
 * New area — re-skinned to the spec form pattern. Mirrors the web admin area
 * form (Site / Code / Name / Description / Active). An optional ?site_id locks
 * the site picker (used when adding from a site detail). Hooks, payload and
 * navigation are unchanged.
 */
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
import { useCreateArea, useSites } from '@/hooks/queries/useStructureIsa95';

export function NewAreaScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const { site_id } = useLocalSearchParams<{ site_id?: string }>();
  const lockedId = site_id ? Number(site_id) : undefined;

  const sitesQ = useSites();
  const m = useCreateArea();
  const sites = sitesQ.data ?? [];

  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [siteId, setSiteId] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  if (!sitesQ.isLoading && !seeded) {
    setSiteId(String(lockedId ?? sites[0]?.id ?? ''));
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Name is required';
    if (!code.trim()) e.code = 'Code is required';
    if (!siteId || Number(siteId) <= 0) e.siteId = 'Pick a site';
    return e;
  }, [name, code, siteId]);

  if (sitesQ.isLoading) return <LoadingState />;

  const invalid = Object.keys(errors).length > 0 || sites.length === 0;

  const onSave = () => {
    if (invalid) return;
    m.mutate(
      {
        name: name.trim(),
        code: code.trim(),
        site_id: Number(siteId),
        description: description.trim() || null,
        is_active: isActive,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create area'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New area')}</Text>

      {!lockedId ? (
        <View style={{ gap: 6 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Site').toUpperCase()} *</Mono>
          <Dropdown
            value={siteId}
            onChange={(v) => setSiteId(v as string)}
            placeholder={t('Select site')}
            options={sites.map((s) => ({ value: String(s.id), label: s.name }))}
          />
          {sites.length === 0 ? <Text style={styles.error}>{t('No sites yet — create one first')}</Text> : null}
          {errors.siteId ? <Text style={styles.error}>{t(errors.siteId)}</Text> : null}
        </View>
      ) : null}

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} hint="Unique within the parent site" />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={2} style={{ minHeight: 60, textAlignVertical: 'top' }} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('INACTIVE ENTITIES ARE HIDDEN BY DEFAULT')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Create')} onPress={onSave} loading={m.isPending} disabled={invalid || m.isPending} />
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
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
