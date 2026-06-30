import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SimpleCodeNameForm } from '@/components/admin/SimpleCodeNameForm';
import { DangerZone, DetailScreen, LinkRowCard } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useFactory, useFactoryDivisions } from '@/hooks/queries/useOrgStructure';
import {
  useDeleteFactory,
  useToggleFactoryActive,
  useUpdateFactory,
} from '@/hooks/queries/useOrgStructure';

export function EditFactoryScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useFactory(numericId);
  const divisionsQuery = useFactoryDivisions(numericId, true);
  const updateMutation = useUpdateFactory();
  const deleteMutation = useDeleteFactory();
  const toggleMutation = useToggleFactoryActive();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const f = query.data;

  return (
    <DetailScreen>
      <SimpleCodeNameForm
        mode="edit"
        entityLabel="factory"
        initial={{
          code: f.code,
          name: f.name,
          description: f.description ?? '',
          is_active: f.is_active,
        }}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: f.id,
              payload: {
                code: values.code,
                name: values.name,
                description: values.description || undefined,
                is_active: values.is_active,
              },
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <LinkRowCard
        icon="th-large"
        title="Divisions"
        count={divisionsQuery.data?.length ?? f.divisions_count ?? 0}
        subtitle="Manage divisions inside this factory"
        onPress={() => router.push(`/(drawer)/structure/factories/${f.id}/divisions` as never)}
      />

      <DangerZone
        toggleLabel={f.is_active ? 'Deactivate' : 'Activate'}
        toggleLoading={toggleMutation.isPending}
        onToggle={() =>
          toggleMutation.mutate(f.id, { onError: (e: Error) => Alert.alert('Failed', e.message) })
        }
        deleteLabel="Delete factory"
        deleteConfirmTitle="Delete factory"
        deleteConfirmMessage={`Delete "${f.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(f.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
