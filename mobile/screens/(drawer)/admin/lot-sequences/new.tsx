import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { useProductTypes } from '@/hooks/queries/useProductTypes';
import { useCreateLotSequence } from '@/hooks/queries/useLot';

export function NewLotSequenceScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const m = useCreateLotSequence();
  const productTypes = useProductTypes();

  const [name, setName] = useState('');
  const [prefix, setPrefix] = useState('');
  const [suffix, setSuffix] = useState('');
  const [padSize, setPadSize] = useState('4');
  const [yearPrefix, setYearPrefix] = useState(true);
  const [productTypeId, setProductTypeId] = useState<number | null>(null);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (!prefix.trim()) e.prefix = 'Required';
    return e;
  }, [name, prefix]);

  const previewPattern =
    [yearPrefix ? 'YYYY' : null, prefix || null, padSize ? `[${padSize}-DIGIT]` : null, suffix || null]
      .filter(Boolean)
      .join('+') || '—';

  const typeOptions = [
    { value: '', label: t('— None —') },
    ...(productTypes.data ?? []).map((pt) => ({ value: String(pt.id), label: pt.name })),
  ];

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    m.mutate(
      {
        name: name.trim(),
        prefix: prefix.trim(),
        suffix: suffix.trim() || null,
        pad_size: padSize.trim() ? Number(padSize) : null,
        year_prefix: yearPrefix,
        product_type_id: productTypeId,
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not create'), e.message) },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New LOT Sequence')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="e.g. default, EU-PROD" />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Product Type').toUpperCase()}</Mono>
        <Dropdown
          value={productTypeId == null ? '' : String(productTypeId)}
          onChange={(v) => setProductTypeId(v === '' ? null : Number(v))}
          options={typeOptions}
        />
      </View>

      <View style={styles.codeRow}>
        <View style={{ flex: 1 }}>
          <Field label="Prefix" value={prefix} onChangeText={setPrefix} error={errors.prefix} required mono autoCapitalize="characters" autoCorrect={false} placeholder="e.g. LOT" />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Suffix" value={suffix} onChangeText={setSuffix} mono autoCapitalize="characters" autoCorrect={false} />
        </View>
        <View style={{ width: 90 }}>
          <Field label="Pad Size" value={padSize} onChangeText={setPadSize} keyboardType="number-pad" mono />
        </View>
      </View>

      <View style={styles.preview}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Pattern').toUpperCase()}</Mono>
        <Mono size={14} weight="600" color={colors.ink} letterSpacing={0.4}>{previewPattern}</Mono>
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Year Prefix')}</Text>
          <Mono size={9} color={colors.faint}>{t('PREPEND CURRENT YEAR (E.G. 2026LOT0001)')}</Mono>
        </View>
        <Switch value={yearPrefix} onValueChange={setYearPrefix} />
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
  codeRow: { flexDirection: 'row', gap: 10 },
  preview: { gap: 4, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
