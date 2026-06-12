import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { CompanyForm } from '@/components/admin/CompanyForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useCompany,
  useDeleteCompany,
  useToggleCompanyActive,
  useUpdateCompany,
} from '@/hooks/queries/useOps';

export function EditCompanyScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useCompany(numericId);
  const updateMutation = useUpdateCompany();
  const deleteMutation = useDeleteCompany();
  const toggleMutation = useToggleCompanyActive();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const c = query.data;

  return (
    <DetailScreen>
      <CompanyForm
        mode="edit"
        initial={c}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              id: c.id,
              payload: {
                code: v.code,
                name: v.name,
                type: v.type,
                tax_id: v.tax_id || undefined,
                email: v.email || undefined,
                phone: v.phone || undefined,
                address: v.address || undefined,
                is_active: v.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        toggleLabel={c.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(c.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete company"
        deleteConfirmTitle="Delete company"
        deleteConfirmMessage={`Delete "${c.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(c.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
