/**
 * Edit LOT Sequence — mirrors the web admin/lot-sequences edit page: a live
 * "next lot" preview block above the LotSequenceForm, plus a delete action.
 * Keeps the REST update/delete mutations and the lot-preview query unchanged.
 */
import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { LotSequenceForm } from '@/components/admin/LotSequenceForm';
import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeleteLotSequence,
  useLotSequence,
  useLotPreview,
  useUpdateLotSequence,
} from '@/hooks/queries/useLot';

export function EditLotSequenceScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();
  const { t } = useTranslation();

  const query = useLotSequence(numericId);
  const updateMutation = useUpdateLotSequence(numericId);
  const deleteMutation = useDeleteLotSequence();
  const preview = useLotPreview(query.data?.product_type_id ?? undefined, !!query.data);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const s = query.data;

  const onDelete = () =>
    Alert.alert(t('Delete LOT sequence'), t('Delete ":name"?', { name: s.name }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () =>
          deleteMutation.mutate(s.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert(t('Could not delete'), e.message),
          }),
      },
    ]);

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('Edit LOT Sequence')}</Text>

      <View style={styles.previewBlock}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Next lot').toUpperCase()}</Mono>
        <Text style={styles.previewValue}>{preview.data ?? '—'}</Text>
        <Mono size={10} color={colors.muted}>
          {(s.product_type?.name ?? t('Default fallback')).toUpperCase()}
        </Mono>
      </View>

      <LotSequenceForm
        mode="edit"
        initial={s}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              name: v.name,
              prefix: v.prefix,
              suffix: v.suffix || null,
              pad_size: v.pad_size ? Number(v.pad_size) : null,
              year_prefix: v.year_prefix,
              product_type_id: v.product_type_id ?? null,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert(t('Could not update'), e.message) },
          )
        }
      />

      <View style={styles.actions}>
        <Button title={t('Delete LOT sequence')} variant="danger" onPress={onDelete} loading={deleteMutation.isPending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  previewBlock: {
    backgroundColor: colors.card,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    padding: 16,
    gap: 6,
    alignItems: 'flex-start',
  },
  previewValue: {
    fontSize: 30,
    fontFamily: fonts.mono.native.medium,
    color: colors.ink,
    letterSpacing: -0.6,
  },
  actions: { marginTop: 4 },
});
