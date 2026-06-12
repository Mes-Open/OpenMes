import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { SkillForm } from '@/components/admin/SkillForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useSkill } from '@/hooks/queries/useHr';
import { useDeleteSkill, useUpdateSkill } from '@/hooks/mutations/hr';

export function EditSkillScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const router = useRouter();

  const query = useSkill(numericId);
  const updateMutation = useUpdateSkill();
  const deleteMutation = useDeleteSkill();

  if (query.isLoading) return <LoadingState />;
  if (query.isError || !query.data) return <ErrorState error={query.error} onRetry={query.refetch} />;
  const s = query.data;

  return (
    <DetailScreen>
      <SkillForm
        mode="edit"
        initial={{ code: s.code, name: s.name, description: s.description ?? '' }}
        submitting={updateMutation.isPending}
        onSubmit={(values) =>
          updateMutation.mutate(
            { id: s.id, payload: { code: values.code, name: values.name, description: values.description || undefined } },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete skill"
        deleteConfirmTitle="Delete skill"
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
