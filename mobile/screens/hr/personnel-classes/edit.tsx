import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { PersonnelClassForm } from '@/components/admin/PersonnelClassForm';
import { DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useSkills } from '@/hooks/queries/useHr';
import {
  usePersonnelClass,
  useUpdatePersonnelClass,
} from '@/hooks/queries/usePersonnel';

export function EditPersonnelClassScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);

  const q = usePersonnelClass(Number.isFinite(id) ? id : undefined);
  const skillsQ = useSkills();
  const m = useUpdatePersonnelClass();

  if (q.isLoading || skillsQ.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Class not found')} onRetry={q.refetch} />;

  return (
    <DetailScreen>
      <PersonnelClassForm
        mode="edit"
        initial={q.data}
        skills={(skillsQ.data ?? []).map((s) => ({ id: s.id, name: s.name }))}
        submitting={m.isPending}
        onSubmit={(payload) =>
          m.mutate(
            { id, payload },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not save changes', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
