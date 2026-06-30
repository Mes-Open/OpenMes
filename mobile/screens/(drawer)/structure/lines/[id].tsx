import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { LineForm } from '@/components/admin/LineForm';
import { DetailScreen, LinkRowCard, StatPanel } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useLineDetail, useLineProductTypes, useLineUsers, useWorkstations } from '@/hooks/queries/useLines';
import { useDeleteLine, useUpdateLine } from '@/hooks/mutations/lines';

export function LineDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const lineQuery = useLineDetail(numericId);
  const usersQuery = useLineUsers(numericId);
  const productTypesQuery = useLineProductTypes(numericId);
  const workstationsQuery = useWorkstations(numericId, true);

  const updateMutation = useUpdateLine();
  const deleteMutation = useDeleteLine();

  if (lineQuery.isLoading) return <LoadingState />;
  if (lineQuery.isError || !lineQuery.data)
    return <ErrorState error={lineQuery.error} onRetry={lineQuery.refetch} />;

  const line = lineQuery.data;

  return (
    <DetailScreen>
      <LineForm
        mode="edit"
        initial={line}
        submitting={updateMutation.isPending}
        counts={{
          workstations: workstationsQuery.data?.length ?? line.workstations_count ?? 0,
          workers: usersQuery.data?.length ?? line.users_count ?? 0,
          workOrders: line.work_orders_count ?? 0,
        }}
        onSubmit={(values) =>
          updateMutation.mutate(
            {
              id: line.id,
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
        onDelete={() =>
          deleteMutation.mutate(line.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />

      <StatPanel
        title="Other entities"
        items={[
          { label: 'Product types', value: productTypesQuery.data?.length ?? 0 },
        ]}
      />

      <LinkRowCard
        icon="sitemap"
        title="Workstations"
        count={workstationsQuery.data?.length ?? 0}
        subtitle="Manage workstations on this line"
        onPress={() =>
          router.push(`/(drawer)/structure/lines/${line.id}/workstations` as never)
        }
      />

      <LinkRowCard
        icon="columns"
        title="Custom statuses"
        subtitle="Define Kanban columns for this line"
        onPress={() => router.push(`/(drawer)/structure/lines/${line.id}/statuses` as never)}
      />
    </DetailScreen>
  );
}
