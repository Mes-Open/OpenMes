import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, StyleSheet, Text, View } from 'react-native';

import { LotSequenceForm } from '@/components/admin/LotSequenceForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
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
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useLotSequence(numericId);
  const updateMutation = useUpdateLotSequence(numericId);
  const deleteMutation = useDeleteLotSequence();
  const preview = useLotPreview(query.data?.product_type_id ?? undefined, !!query.data);

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const s = query.data;

  return (
    <DetailScreen>
      <View style={[styles.previewBlock, { backgroundColor: palette.surfaceInverse }]}>
        <Mono size={10} color="#6F6C66" letterSpacing={0.8}>NEXT LOT</Mono>
        <Text style={[styles.previewValue, { fontFamily: MONO }]}>{preview.data ?? '—'}</Text>
        <Mono size={11} color="#6F6C66" style={{ marginTop: 6 }}>
          {s.product_type?.name?.toUpperCase() ?? 'DEFAULT FALLBACK'}
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
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete LOT sequence"
        deleteConfirmTitle="Delete LOT sequence"
        deleteConfirmMessage={`Delete "${s.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(s.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}

const styles = StyleSheet.create({
  previewBlock: { borderRadius: 18, padding: 18, alignItems: 'flex-start' },
  previewValue: {
    color: '#fff',
    fontSize: 32,
    fontWeight: '500',
    letterSpacing: -0.6,
    marginTop: 6,
  },
});
