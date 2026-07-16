import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useTopic, useUpdateTopic } from '@/hooks/queries/useConnectivity';
import { TopicFormFields } from './new';

export function EditTopicScreen() {
  const router = useRouter();
  const { id: idParam } = useLocalSearchParams<{ id: string }>();
  const id = Number(idParam);
  const q = useTopic(Number.isFinite(id) ? id : undefined);
  const m = useUpdateTopic();

  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data)
    return <ErrorState error={q.error ?? new Error('Topic not found')} onRetry={q.refetch} />;

  return (
    <TopicFormFields
      mode="edit"
      connections={[]}
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
    />
  );
}
