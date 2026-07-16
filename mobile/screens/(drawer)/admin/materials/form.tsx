/**
 * Material create/edit form — 1:1 with the web admin material form: code, name,
 * material type, unit of measure, tracking type, description, default scrap %,
 * external code/system and an active toggle.
 */
import { useMemo, useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { LoadingState } from '@/components/ui/StateViews';
import {
  useCreateMaterial,
  useMaterial,
  useMaterialTypes,
  useUpdateMaterial,
} from '@/hooks/queries/useBom';
import type { MaterialTrackingType } from '@/api/bom';

const TRACKING: MaterialTrackingType[] = ['none', 'batch', 'serial'];

export function MaterialFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const detail = useMaterial(isEdit ? id : undefined);
  const types = useMaterialTypes();
  const existing = isEdit ? detail.data : undefined;
  const create = useCreateMaterial();
  const update = useUpdateMaterial();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [typeId, setTypeId] = useState('');
  const [uom, setUom] = useState('');
  const [tracking, setTracking] = useState<MaterialTrackingType>('none');
  const [description, setDescription] = useState('');
  const [scrap, setScrap] = useState('');
  const [externalCode, setExternalCode] = useState('');
  const [externalSystem, setExternalSystem] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(!isEdit);

  if (isEdit && !seeded && existing) {
    setCode(existing.code);
    setName(existing.name);
    setTypeId(String(existing.material_type_id));
    setUom(existing.unit_of_measure ?? '');
    setTracking(existing.tracking_type ?? 'none');
    setDescription(existing.description ?? '');
    setScrap(existing.default_scrap_percentage != null ? String(existing.default_scrap_percentage) : '');
    setExternalCode(existing.external_code ?? '');
    setExternalSystem(existing.external_system ?? '');
    setIsActive(existing.is_active ?? true);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    if (!typeId) e.type = 'Required';
    return e;
  }, [code, name, typeId]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input = {
      code: code.trim(),
      name: name.trim(),
      material_type_id: Number(typeId),
      unit_of_measure: uom.trim() || null,
      tracking_type: tracking,
      description: description.trim() || null,
      default_scrap_percentage: scrap.trim() ? Number(scrap) : null,
      external_code: externalCode.trim() || null,
      external_system: externalSystem.trim() || null,
      is_active: isActive,
    };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    else create.mutate(input, { onSuccess: () => router.back(), onError });
  };

  if (isEdit && detail.isLoading && !existing) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit material') : t('New material')}</Text>

      <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required autoCapitalize="characters" mono placeholder="MAT-001" />
      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="Cotton fabric" />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Material Type').toUpperCase()} *</Mono>
        <Dropdown
          value={typeId}
          onChange={(v) => setTypeId(v as string)}
          placeholder={t('Select type')}
          options={(types.data ?? []).map((mt) => ({ value: String(mt.id), label: mt.name }))}
        />
        {errors.type ? <Text style={styles.error}>{t(errors.type)}</Text> : null}
      </View>

      <Field label="Unit of measure" value={uom} onChangeText={setUom} autoCapitalize="none" mono placeholder="kg" />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Tracking').toUpperCase()}</Mono>
        <Dropdown
          value={tracking}
          onChange={(v) => setTracking(v as MaterialTrackingType)}
          options={TRACKING.map((tr) => ({ value: tr, label: t(tr.charAt(0).toUpperCase() + tr.slice(1)) }))}
        />
      </View>

      <Field label="Description" value={description} onChangeText={setDescription} placeholder="Optional" multiline />
      <Field label="Default scrap %" value={scrap} onChangeText={setScrap} keyboardType="decimal-pad" mono placeholder="0" />
      <Field label="External code" value={externalCode} onChangeText={setExternalCode} autoCapitalize="none" mono placeholder="—" />
      <Field label="External system" value={externalSystem} onChangeText={setExternalSystem} placeholder="—" />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Material is in use')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
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
