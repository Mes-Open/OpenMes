import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useMapping, useUpdateMapping } from '@/hooks/queries/useConnectivity';
import { MappingFormFields } from './new';

export function EditMappingScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);
  const q = useMapping(Number.isFinite(id) ? id : undefined);
  const m = useUpdateMapping();

  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Mapping not found')} onRetry={q.refetch} />;

  return (
    <MappingFormFields
      mode="edit"
      topics={[]}
      initial={q.data}
      submitting={m.isPending}
      onSubmit={(input) =>
        m.mutate(
          { id, input },
          {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not save changes', e.message),
          },
        )
      }
      onValidationError={(msg) => Alert.alert('Form error', msg)}
    />
  );
}
