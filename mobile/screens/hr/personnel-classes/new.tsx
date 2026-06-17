import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { PersonnelClassForm } from '@/components/admin/PersonnelClassForm';
import { DetailScreen } from '@/components/ui/Detail';
import { LoadingState } from '@/components/ui/StateViews';
import { useSkills } from '@/hooks/queries/useHr';
import { useCreatePersonnelClass } from '@/hooks/queries/usePersonnel';

export function NewPersonnelClassScreen() {
  const router = useRouter();
  const skillsQ = useSkills();
  const m = useCreatePersonnelClass();

  if (skillsQ.isLoading) return <LoadingState />;

  return (
    <DetailScreen>
      <PersonnelClassForm
        mode="create"
        skills={(skillsQ.data ?? []).map((s) => ({ id: s.id, name: s.name }))}
        submitting={m.isPending}
        onSubmit={(payload) =>
          m.mutate(payload, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not create class', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
