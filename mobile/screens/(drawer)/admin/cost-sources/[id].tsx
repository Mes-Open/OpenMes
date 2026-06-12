import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { CostSourceForm } from '@/components/admin/CostSourceForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useCostSource, useDeleteCostSource, useUpdateCostSource } from '@/hooks/queries/useOps';

export function EditCostSourceScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useCostSource(numericId);
  const updateMutation = useUpdateCostSource();
  const deleteMutation = useDeleteCostSource();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const cs = query.data;

  return (
    <DetailScreen>
      <CostSourceForm
        mode="edit"
        initial={cs}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              id: cs.id,
              payload: {
                code: v.code,
                name: v.name,
                description: v.description || undefined,
                unit_cost: v.unit_cost ? Number(v.unit_cost) : undefined,
                unit: v.unit || undefined,
                currency: v.currency || undefined,
                is_active: v.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete cost source"
        deleteConfirmTitle="Delete cost source"
        deleteConfirmMessage={`Delete "${cs.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(cs.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
