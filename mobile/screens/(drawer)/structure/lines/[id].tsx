/**
 * Line detail / edit — re-skinned to the spec form/detail pattern. Mirrors the
 * web admin line configure page: the core form (Code / Name / Description /
 * Active), KPI counts, a product-types stat and link rows to workstations and
 * custom statuses, plus delete. Hooks, payloads and navigation are unchanged.
 */
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useLineDetail, useLineProductTypes, useLineUsers, useWorkstations } from '@/hooks/queries/useLines';
import { useDeleteLine, useUpdateLine } from '@/hooks/mutations/lines';

export function LineDetailScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const lineQuery = useLineDetail(numericId);
  const usersQuery = useLineUsers(numericId);
  const productTypesQuery = useLineProductTypes(numericId);
  const workstationsQuery = useWorkstations(numericId, true);

  const updateMutation = useUpdateLine();
  const deleteMutation = useDeleteLine();

  const [code, setCode] = useState('');
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [seeded, setSeeded] = useState(false);

  const line = lineQuery.data;
  if (line && !seeded) {
    setCode(line.code ?? '');
    setName(line.name);
    setDescription(line.description ?? '');
    setIsActive(line.is_active ?? true);
    setSeeded(true);
  }

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!code.trim()) e.code = 'Required';
    if (!name.trim()) e.name = 'Required';
    return e;
  }, [code, name]);

  if (lineQuery.isLoading && !line) return <LoadingState />;
  if (lineQuery.isError || !line) return <ErrorState error={lineQuery.error} onRetry={lineQuery.refetch} />;

  const invalid = Object.keys(errors).length > 0;
  const counts = {
    workstations: workstationsQuery.data?.length ?? line.workstations_count ?? 0,
    workers: usersQuery.data?.length ?? line.users_count ?? 0,
    workOrders: line.work_orders_count ?? 0,
  };
  const productTypesCount = productTypesQuery.data?.length ?? 0;

  const onSave = () => {
    if (invalid) return;
    updateMutation.mutate(
      {
        id: line.id,
        payload: {
          code: code.trim(),
          name: name.trim(),
          description: description.trim() || undefined,
          is_active: isActive,
        },
      },
      { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
    );
  };

  const confirmDelete = () => {
    Alert.alert(t('Delete line'), t('Delete "{{name}}"?', { name: line.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(line.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);
  };

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit line')}</Text>

      <View style={styles.codeNameRow}>
        <View style={{ width: 120 }}>
          <Field label="Code" value={code} onChangeText={setCode} error={errors.code} required mono autoCapitalize="characters" autoCorrect={false} placeholder="L-04" />
        </View>
        <View style={{ flex: 1 }}>
          <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="Pack & Ship" />
        </View>
      </View>
      <Field label="Description" value={description} onChangeText={setDescription} multiline numberOfLines={3} style={{ minHeight: 80, textAlignVertical: 'top' }} />

      <View style={styles.countsGrid}>
        {[
          { label: t('Workstations'), value: counts.workstations },
          { label: t('Workers'), value: counts.workers },
          { label: t('Work orders'), value: counts.workOrders },
        ].map((c) => (
          <View key={c.label} style={styles.countTile}>
            <Mono size={9} color={colors.faint} letterSpacing={0.6}>{c.label.toUpperCase()}</Mono>
            <Mono size={22} weight="600" color={colors.ink} letterSpacing={-0.5} style={{ marginTop: 4 }}>{String(c.value)}</Mono>
          </View>
        ))}
      </View>

      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.toggleLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('INACTIVE ENTITIES ARE HIDDEN BY DEFAULT')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <View style={styles.statBox}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Other entities').toUpperCase()}</Mono>
        <View style={styles.statRow}>
          <Text style={styles.statLabel}>{t('Product types')}</Text>
          <Mono size={13} color={colors.ink}>{String(productTypesCount)}</Mono>
        </View>
      </View>

      <Pressable
        onPress={() => router.push(`/(drawer)/structure/lines/${line.id}/workstations` as never)}
        style={({ pressed }) => [styles.linkRow, { opacity: pressed ? 0.7 : 1 }]}>
        <View style={styles.iconWrap}>
          <FontAwesome name="sitemap" size={14} color={colors.accent} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.linkTitle} numberOfLines={1}>
            {t('Workstations')}
            <Text style={styles.linkCount}> · {counts.workstations}</Text>
          </Text>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Manage workstations on this line').toUpperCase()}</Mono>
        </View>
        <FontAwesome name="chevron-right" size={12} color={colors.faintest} />
      </Pressable>

      <Pressable
        onPress={() => router.push(`/(drawer)/structure/lines/${line.id}/statuses` as never)}
        style={({ pressed }) => [styles.linkRow, { opacity: pressed ? 0.7 : 1 }]}>
        <View style={styles.iconWrap}>
          <FontAwesome name="columns" size={14} color={colors.accent} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.linkTitle} numberOfLines={1}>{t('Custom statuses')}</Text>
          <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Define Kanban columns for this line').toUpperCase()}</Mono>
        </View>
        <FontAwesome name="chevron-right" size={12} color={colors.faintest} />
      </Pressable>

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save changes')} onPress={onSave} loading={updateMutation.isPending} disabled={invalid || updateMutation.isPending} />
      </View>

      <View style={styles.danger}>
        <Button
          title={t('Delete line')}
          variant="danger"
          leftIcon={<FontAwesome name="trash" size={13} color={colors.blocked} />}
          loading={deleteMutation.isPending}
          onPress={confirmDelete}
        />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  codeNameRow: { flexDirection: 'row', gap: 10 },
  countsGrid: { flexDirection: 'row', gap: 8 },
  countTile: { flex: 1, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 12 },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  toggleLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  statBox: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14, gap: 6 },
  statRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  statLabel: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  linkRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  iconWrap: { width: 34, height: 34, borderRadius: radius.sm, alignItems: 'center', justifyContent: 'center', backgroundColor: `${colors.accent}1A` },
  linkTitle: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  linkCount: { color: colors.faint, fontFamily: fonts.sans.native.medium },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  danger: { gap: 10, marginTop: 8 },
});
