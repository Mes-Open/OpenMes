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
import { LoadingState } from '@/components/ui/StateViews';
import { useMaterialTypes, useMaterials } from '@/hooks/queries/useBom';
import { useCreateInspectionPlan } from '@/hooks/queries/useInspections';
import type { CreateInspectionPlanPayload, InspectionCriterionType } from '@/api/inspections';

type Scope = 'material' | 'material_type' | 'generic';
type CriterionRow = {
  name: string;
  type: InspectionCriterionType;
  required: boolean;
  unit: string;
  spec_min: string;
  spec_max: string;
};

const TYPE_OPTIONS: { value: InspectionCriterionType; label: string }[] = [
  { value: 'visual', label: 'Visual' },
  { value: 'measurement', label: 'Measurement' },
  { value: 'functional', label: 'Functional' },
  { value: 'pass_fail', label: 'Pass/Fail' },
];

const emptyCriterion = (): CriterionRow => ({
  name: '',
  type: 'measurement',
  required: true,
  unit: '',
  spec_min: '',
  spec_max: '',
});

export function NewInspectionPlanScreen() {
  const { t } = useTranslation();
  const router = useRouter();
  const matsQ = useMaterials({});
  const typesQ = useMaterialTypes();
  const m = useCreateInspectionPlan();

  const materials = (matsQ.data ?? []).map((x) => ({ id: x.id, name: x.name }));
  const materialTypes = (typesQ.data ?? []).map((x) => ({ id: x.id, name: x.name }));

  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [scope, setScope] = useState<Scope>('generic');
  const [materialId, setMaterialId] = useState<number | null>(null);
  const [materialTypeId, setMaterialTypeId] = useState<number | null>(null);
  const [isActive, setIsActive] = useState(true);
  const [criteria, setCriteria] = useState<CriterionRow[]>([emptyCriterion()]);

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (criteria.filter((c) => c.name.trim()).length === 0) e.criteria = 'Add at least one criterion';
    return e;
  }, [name, criteria]);

  const setCriterion = (i: number, patch: Partial<CriterionRow>) =>
    setCriteria((cur) => cur.map((c, j) => (j === i ? { ...c, ...patch } : c)));

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const payload: CreateInspectionPlanPayload = {
      name: name.trim(),
      description: description.trim() || undefined,
      material_id: scope === 'material' && materialId != null ? materialId : undefined,
      material_type_id: scope === 'material_type' && materialTypeId != null ? materialTypeId : undefined,
      is_active: isActive,
      criteria: criteria
        .filter((c) => c.name.trim())
        .map((c) => ({
          name: c.name.trim(),
          type: c.type,
          required: c.required,
          unit: c.unit.trim() || null,
          spec_min: c.spec_min.trim() ? Number(c.spec_min) : null,
          spec_max: c.spec_max.trim() ? Number(c.spec_max) : null,
        })),
    };
    m.mutate(payload, {
      onSuccess: () => router.back(),
      onError: (e: Error) => Alert.alert(t('Could not create plan'), e.message),
    });
  };

  if (matsQ.isLoading || typesQ.isLoading) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New Inspection Plan')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required autoFocus />
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={2} style={styles.textarea} />

      <View style={{ gap: 6 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Scope').toUpperCase()} *</Mono>
        <Dropdown
          value={scope}
          onChange={(v) => setScope(v as Scope)}
          options={[
            { value: 'material', label: t('Specific material') },
            { value: 'material_type', label: t('Material type') },
            { value: 'generic', label: t('Generic') },
          ]}
        />
      </View>

      {scope === 'material' ? (
        <View style={{ gap: 6 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Material').toUpperCase()} *</Mono>
          <Dropdown
            value={materialId == null ? '' : String(materialId)}
            onChange={(v) => setMaterialId(v === '' ? null : Number(v))}
            placeholder={t('Select material')}
            options={materials.map((mat) => ({ value: String(mat.id), label: mat.name }))}
          />
        </View>
      ) : null}
      {scope === 'material_type' ? (
        <View style={{ gap: 6 }}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Material Type').toUpperCase()} *</Mono>
          <Dropdown
            value={materialTypeId == null ? '' : String(materialTypeId)}
            onChange={(v) => setMaterialTypeId(v === '' ? null : Number(v))}
            placeholder={t('Select material type')}
            options={materialTypes.map((mt) => ({ value: String(mt.id), label: mt.name }))}
          />
        </View>
      ) : null}

      <View style={{ gap: 10 }}>
        <View style={styles.criteriaHead}>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Criteria').toUpperCase()} *</Mono>
          <View style={{ flex: 1 }} />
          <Button title={t('Add criterion')} size="sm" variant="ghost" onPress={() => setCriteria((c) => [...c, emptyCriterion()])} />
        </View>
        {criteria.map((c, i) => (
          <View key={i} style={styles.criterion}>
            <View style={styles.criterionHead}>
              <Mono size={9} color={colors.faint} letterSpacing={0.6}>{`${t('Criterion')} #${i + 1}`}</Mono>
              <View style={{ flex: 1 }} />
              {criteria.length > 1 ? (
                <Pressable onPress={() => setCriteria((cur) => cur.filter((_, j) => j !== i))} hitSlop={8} style={styles.optDel}>
                  <FontAwesome name="trash-o" size={16} color={colors.blocked} />
                </Pressable>
              ) : null}
            </View>
            <Field label="Characteristic" value={c.name} onChangeText={(v) => setCriterion(i, { name: v })} placeholder="Surface finish" />
            <View style={{ gap: 6 }}>
              <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Type').toUpperCase()}</Mono>
              <Dropdown
                value={c.type}
                onChange={(v) => setCriterion(i, { type: v as InspectionCriterionType })}
                options={TYPE_OPTIONS.map((o) => ({ value: o.value, label: t(o.label) }))}
              />
            </View>
            <View style={styles.criterionRow}>
              <View style={{ flex: 1 }}>
                <Field label="Unit" value={c.unit} onChangeText={(v) => setCriterion(i, { unit: v })} autoCapitalize="none" autoCorrect={false} placeholder="N" />
              </View>
              <View style={{ flex: 1 }}>
                <Field label="Min" value={c.spec_min} onChangeText={(v) => setCriterion(i, { spec_min: v })} keyboardType="decimal-pad" mono />
              </View>
              <View style={{ flex: 1 }}>
                <Field label="Max" value={c.spec_max} onChangeText={(v) => setCriterion(i, { spec_max: v })} keyboardType="decimal-pad" mono />
              </View>
            </View>
            <View style={styles.reqRow}>
              <Text style={styles.toggleLabel}>{t('Required')}</Text>
              <Switch value={c.required} onValueChange={(v) => setCriterion(i, { required: v })} />
            </View>
          </View>
        ))}
        {errors.criteria ? <Text style={styles.error}>{t(errors.criteria)}</Text> : null}
      </View>

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
        <Button title={t('Create plan')} onPress={onSave} loading={m.isPending} disabled={m.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  textarea: { minHeight: 60, textAlignVertical: 'top', paddingTop: 12 },
  criteriaHead: { flexDirection: 'row', alignItems: 'center' },
  criterion: { gap: 10, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  criterionHead: { flexDirection: 'row', alignItems: 'center' },
  criterionRow: { flexDirection: 'row', gap: 10 },
  optDel: { padding: 4 },
  reqRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
