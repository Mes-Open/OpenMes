import { useMemo, useState } from 'react';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useCompany,
  useDeleteCompany,
  useToggleCompanyActive,
  useUpdateCompany,
} from '@/hooks/queries/useOps';
import type { CompanyType } from '@/api/ops';

export function EditCompanyScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useCompany(numericId);
  const updateMutation = useUpdateCompany();
  const deleteMutation = useDeleteCompany();
  const toggleMutation = useToggleCompanyActive();
  const c = query.data;

  const [code, setCode] = useState(c?.code ?? '');
  const [name, setName] = useState(c?.name ?? '');
  const [taxId, setTaxId] = useState(c?.tax_id ?? '');
  const [type, setType] = useState<CompanyType>(c?.type ?? 'supplier');
  const [email, setEmail] = useState(c?.email ?? '');
  const [phone, setPhone] = useState(c?.phone ?? '');
  const [address, setAddress] = useState(c?.address ?? '');
  const [isActive, setIsActive] = useState(c?.is_active ?? true);
  const [seeded, setSeeded] = useState(false);

  if (!seeded && c) {
    setCode(c.code);
    setName(c.name);
    setTaxId(c.tax_id ?? '');
    setType(c.type);
    setEmail(c.email ?? '');
    setPhone(c.phone ?? '');
    setAddress(c.address ?? '');
    setIsActive(c.is_active);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (query.isLoading && !c) return <LoadingState />;
  if (query.isError || !c) return <ErrorState error={query.error} onRetry={query.refetch} />;

  const typeOptions = [
    { value: 'supplier', label: t('Supplier') },
    { value: 'customer', label: t('Customer') },
    { value: 'both', label: t('Both') },
  ];

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    updateMutation.mutate(
      {
        id: c.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          type,
          tax_id: taxId.trim() || undefined,
          email: email.trim() || undefined,
          phone: phone.trim() || undefined,
          address: address.trim() || undefined,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const onDelete = () =>
    Alert.alert(t('Delete company'), c.name, [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(c.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit Company')}</Text>

      <View style={styles.codeRow}>
        <View style={{ width: 130 }}>
          <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Name" value={name} onChangeText={setName} error={errors.name} required />
        </View>
      </View>
      <Field label="Tax ID" value={taxId} onChangeText={setTaxId} mono />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Type').toUpperCase()} *</Mono>
        <Dropdown value={type} onChange={(v) => setType(v as CompanyType)} options={typeOptions} />
      </View>

      <Field label="Email" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" autoCorrect={false} />
      <Field label="Phone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" />
      <Field label="Address" value={address} onChangeText={setAddress} multiline numberOfLines={3} style={styles.textarea} />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('INACTIVE ENTITIES ARE HIDDEN BY DEFAULT')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save changes')} onPress={onSave} loading={updateMutation.isPending} disabled={updateMutation.isPending} />
      </View>

      <View style={styles.danger}>
        <Button
          title={c.is_active ? t('Deactivate') : t('Activate')}
          variant="outline"
          loading={toggleMutation.isPending}
          onPress={() => toggleMutation.mutate(c.id, { onError: (e: Error) => Alert.alert(t('Failed'), e.message) })}
        />
        <Button title={t('Delete company')} variant="danger" onPress={onDelete} loading={deleteMutation.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  codeRow: { flexDirection: 'row', gap: 10 },
  textarea: { minHeight: 80, textAlignVertical: 'top', paddingTop: 12 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  danger: { marginTop: 8, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line2, paddingTop: 16, gap: 10 },
});
