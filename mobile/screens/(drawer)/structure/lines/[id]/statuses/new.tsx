/**
 * New line status — adds a line-specific Kanban column (name, color, default /
 * done flags), mirroring the "add status" form on the web line Show page.
 * Writes via REST useCreateLineStatus.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { useCreateLineStatus } from '@/hooks/queries/useOrgStructure';

const PRESETS = [
  { name: 'Slate', value: '#64748b' },
  { name: 'Blue', value: '#2563eb' },
  { name: 'Green', value: '#16a34a' },
  { name: 'Amber', value: '#d97706' },
  { name: 'Red', value: '#D6442F' },
  { name: 'Purple', value: '#7c3aed' },
];

export function NewLineStatusScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const lineId = Number(id);
  const router = useRouter();

  const [name, setName] = useState('');
  const [color, setColor] = useState(PRESETS[0].value);
  const [isDefault, setIsDefault] = useState(false);
  const [isDoneStatus, setIsDoneStatus] = useState(false);

  const m = useCreateLineStatus();

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [name]);

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    m.mutate(
      {
        lineId,
        payload: { name: name.trim(), color, is_default: isDefault, is_done_status: isDoneStatus },
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not create'), e.message),
      },
    );
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('New status')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="e.g. Waiting for parts" />

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Color').toUpperCase()} *</Mono>
        <View style={styles.colorRow}>
          {PRESETS.map((p) => {
            const active = p.value === color;
            return (
              <Pressable
                key={p.value}
                onPress={() => setColor(p.value)}
                style={[styles.swatch, { backgroundColor: p.value, borderColor: active ? colors.ink : 'transparent' }]}>
                {active ? <FontAwesome name="check" size={12} color="#fff" /> : null}
              </Pressable>
            );
          })}
        </View>
      </View>

      <Field label="Custom color (hex)" value={color} onChangeText={setColor} autoCapitalize="characters" mono />

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleTitle}>{t('Default status')}</Text>
          <Mono size={9} color={colors.faint}>{t('New work orders start here').toUpperCase()}</Mono>
        </View>
        <Switch value={isDefault} onValueChange={setIsDefault} />
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleTitle}>{t('Done status')}</Text>
          <Mono size={9} color={colors.faint}>{t('Marks the order as effectively complete').toUpperCase()}</Mono>
        </View>
        <Switch value={isDoneStatus} onValueChange={setIsDoneStatus} />
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
  colorRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  swatch: { width: 40, height: 40, borderRadius: 10, borderWidth: 3, alignItems: 'center', justifyContent: 'center' },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleTitle: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
