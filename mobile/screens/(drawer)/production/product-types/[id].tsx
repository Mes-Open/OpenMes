import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ProductTypeForm } from '@/components/admin/ProductTypeForm';
import { DangerZone, DetailScreen, LinkRowCard } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useProductType, useProcessTemplatesForProductType } from '@/hooks/queries/useProductTypes';
import {
  useDeleteProductType,
  useToggleProductTypeActive,
  useUpdateProductType,
} from '@/hooks/mutations/productTypes';

export function ProductTypeDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const ptQuery = useProductType(numericId);
  const templatesQuery = useProcessTemplatesForProductType(numericId, true);
  const updateMutation = useUpdateProductType();
  const deleteMutation = useDeleteProductType();
  const toggleMutation = useToggleProductTypeActive();

  if (ptQuery.isLoading) return <LoadingState />;
  if (ptQuery.isError || !ptQuery.data) return <ErrorState error={ptQuery.error} onRetry={ptQuery.refetch} />;

  const pt = ptQuery.data;

  return (
    <DetailScreen>
      <ProductTypeForm
        mode="edit"
        initial={pt}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: pt.id,
              payload: {
                code: values.code,
                name: values.name,
                description: values.description || undefined,
                unit_of_measure: values.unit_of_measure || undefined,
                is_active: values.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <LinkRowCard
        icon="flask"
        title="Process templates"
        count={templatesQuery.data?.length ?? pt.process_templates_count ?? 0}
        subtitle="Versioned recipes and step lists"
        onPress={() =>
          router.push(`/(drawer)/production/product-types/${pt.id}/templates` as never)
        }
      />

      <DangerZone
        toggleLabel={pt.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(pt.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete product type"
        deleteConfirmTitle="Delete product type"
        deleteConfirmMessage={`Delete "${pt.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(pt.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
