/**
 * Edit site — re-skinned to the spec form pattern. Mirrors the web admin site
 * form (Company / Code / Name / Description / Address / City / Country /
 * Timezone / Active). Hooks, payload and navigation are unchanged.
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
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCompanies } from '@/hooks/queries/useOps';
import { useSite, useUpdateSite } from '@/hooks/queries/useStructureIsa95';

export function EditSiteScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = useSite(Number.isFinite(id) ? id : undefined);
  const companiesQ = useCompanies({});
  const m = useUpdateSite();

  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [companyId, setCompanyId] = useState('');
  const [description, setDescription] = useState('');
  const [address, setAddress] = useState('');
  const [city, setCity] = useState('');
  const [country, setCountry] = useState('');
  const [timezone, setTimezone] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  const site = q.data;
  if (site && !seeded) {
    setName(site.name);
    setCode(site.code);
    setCompanyId(site.company_id != null ? String(site.company_id) : '');
    setDescription(site.description ?? '');
    setAddress(site.address ?? '');
    setCity(site.city ?? '');
    setCountry(site.country ?? '');
    setTimezone(site.timezone ?? '');
    setIsActive(site.is_active);
    setSeeded(true);
  }

  const companies = companiesQ.data ?? [];

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Name is required';
    if (!code.trim()) e.code = 'Code is required';
    return e;
  }, [name, code]);

  if (q.isLoading || companiesQ.isLoading) return <LoadingState />;
  if (q.isError || !site) return <ErrorState error={q.error ?? new Error('Site not found')} onRetry={q.refetch} />;

  const invalid = Object.keys(errors).length > 0;

  const onSave = () => {
    if (invalid) return;
    m.mutate(
      {
        id,
        input: {
          name: name.trim(),
          code: code.trim(),
          company_id: companyId === '' ? null : Number(companyId),
          description: description.trim() || null,
          address: address.trim() || null,
          city: city.trim() || null,
          country: country.trim() || null,
          timezone: timezone.trim() || null,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not save changes'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit site')}</Text>

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Company').toUpperCase()}</Mono>
        <Dropdown
          value={companyId}
          onChange={(v) => setCompanyId(v as string)}
          placeholder={t('None')}
          options={[{ value: '', label: t('None') }, ...companies.map((c) => ({ value: String(c.id), label: c.name }))]}
        />
      </View>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={2} style={{ minHeight: 60, textAlignVertical: 'top' }} />
      <Field label="Address" value={address} onChangeText={setAddress} multiline numberOfLines={2} style={{ minHeight: 60, textAlignVertical: 'top' }} />
      <Field label="City" value={city} onChangeText={setCity} />
      <Field label="Country (2-letter)" value={country} onChangeText={setCountry} />
      <Field label="Timezone" value={timezone} onChangeText={setTimezone} autoCapitalize="none" autoCorrect={false} placeholder="Europe/Warsaw" hint="IANA timezone name (e.g. Europe/Warsaw)" />

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
        <Button title={t('Save changes')} onPress={onSave} loading={m.isPending} disabled={invalid || m.isPending} />
      </View>

      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Sites group areas, which group lines (ISA-95)').toUpperCase()}</Mono>
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
});
