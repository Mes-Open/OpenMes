import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { WageGroupForm } from '@/components/admin/WageGroupForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useWageGroup } from '@/hooks/queries/useHr';
import { useDeleteWageGroup, useToggleWageGroupActive, useUpdateWageGroup } from '@/hooks/mutations/hr';

export function EditWageGroupScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useWageGroup(numericId);
  const updateMutation = useUpdateWageGroup();
  const deleteMutation = useDeleteWageGroup();
  const toggleMutation = useToggleWageGroupActive();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const wg = query.data;

  return (
    <DetailScreen>
      <WageGroupForm
        mode="edit"
        initial={{
          code: wg.code,
          name: wg.name,
          description: wg.description ?? '',
          base_hourly_rate: wg.base_hourly_rate?.toString() ?? '',
          currency: wg.currency ?? '',
          is_active: wg.is_active,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: wg.id,
              payload: {
                code: values.code,
                name: values.name,
                description: values.description || undefined,
                base_hourly_rate: values.base_hourly_rate ? Number(values.base_hourly_rate) : undefined,
                currency: values.currency || undefined,
                is_active: values.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        toggleLabel={wg.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(wg.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete wage group"
        deleteConfirmTitle="Delete wage group"
        deleteConfirmMessage={`Delete "${wg.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(wg.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
