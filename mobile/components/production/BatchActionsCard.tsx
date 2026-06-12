import { Alert, View } from 'react-native';
import { useRouter } from 'expo-router';
import { FontAwesome } from '@expo/vector-icons';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { SectionLabel } from '@/components/ui/Mono';
import {
  useCancelBatch,
  useDeleteBatch,
  useReleaseBatch,
} from '@/hooks/mutations/batches';
import type { Batch } from '@/types/api';

interface Props {
  batch: Batch;
}

export function BatchActionsCard({ batch }: Props) {
  const router = useRouter();

  const cancel = useCancelBatch(batch.id, batch.work_order_id);
  const release = useReleaseBatch(batch.id, batch.work_order_id);
  const del = useDeleteBatch(batch.id, batch.work_order_id);

  const isPending = batch.status === 'PENDING';
  const isDone = batch.status === 'DONE';
  const isTerminal = batch.status === 'DONE' || batch.status === 'CANCELLED';

  const confirmAction = (title: string, message: string, action: () => void) => {
    Alert.alert(title, message, [
      { text: 'Cancel', style: 'cancel' },
      { text: title, style: 'destructive', onPress: action },
    ]);
  };

  if (isTerminal && !isDone && !isPending) return null;

  return (
    <Card style={{ gap: 12 }}>
      <SectionLabel>Batch actions</SectionLabel>

      {isDone ? (
        <View style={{ gap: 8 }}>
          <Button
            title="Release for production"
            variant="success"
            leftIcon={<FontAwesome name="industry" size={14} color="#fff" />}
            onPress={() =>
              release.mutate('for_production', {
                onError: (e: Error) => Alert.alert('Release failed', e.message),
              })
            }
            loading={release.isPending}
          />
          <Button
            title="Release for sale"
            variant="success"
            leftIcon={<FontAwesome name="truck" size={14} color="#fff" />}
            onPress={() =>
              release.mutate('for_sale', {
                onError: (e: Error) => Alert.alert('Release failed', e.message),
              })
            }
            loading={release.isPending}
          />
        </View>
      ) : null}

      {!isTerminal ? (
        <Button
          title="Cancel batch"
          variant="outline"
          leftIcon={<FontAwesome name="ban" size={13} color="#D6442F" />}
          onPress={() =>
            confirmAction(
              'Cancel batch',
              'This will mark the batch as CANCELLED. Continue?',
              () =>
                cancel.mutate(undefined, {
                  onError: (e: Error) => Alert.alert('Cancel failed', e.message),
                }),
            )
          }
          loading={cancel.isPending}
        />
      ) : null}

      {isPending ? (
        <Button
          title="Delete batch"
          variant="danger"
          leftIcon={<FontAwesome name="trash" size={13} color="#fff" />}
          onPress={() =>
            confirmAction(
              'Delete batch',
              'This permanently removes the batch. Only allowed when no steps have started.',
              () =>
                del.mutate(undefined, {
                  onSuccess: () => router.back(),
                  onError: (e: Error) => Alert.alert('Delete failed', e.message),
                }),
            )
          }
          loading={del.isPending}
        />
      ) : null}
    </Card>
  );
}
