import { useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { LotSequenceForm } from '@/components/admin/LotSequenceForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateLotSequence } from '@/hooks/queries/useLot';

export function NewLotSequenceScreen() {
  const router = useRouter();
  const m = useCreateLotSequence();

  return (
    <DetailScreen>
      <LotSequenceForm
        mode="create"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            {
              name: v.name,
              prefix: v.prefix,
              suffix: v.suffix || null,
              pad_size: v.pad_size ? Number(v.pad_size) : null,
              year_prefix: v.year_prefix,
              product_type_id: v.product_type_id ?? null,
            },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not create', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}
