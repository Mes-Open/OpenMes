import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert } from 'react-native';

import { QcTemplateForm } from '@/components/admin/QcTemplateForm';
import { DetailScreen } from '@/components/ui/Detail';
import { useCreateQcTemplate } from '@/hooks/queries/useProductionControls';

export function NewQcTemplateScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const processTemplateId = Number(id);
  const router = useRouter();
  const m = useCreateQcTemplate(processTemplateId);

  return (
    <DetailScreen>
      <QcTemplateForm
        mode="create"
        submitting={m.isPending}
        onSubmit={(v) =>
          m.mutate(
            {
              name: v.name,
              parameters: v.parameters.map((p) => ({
                name: p.name,
                type: p.type,
                unit: p.unit || null,
                min: p.min ? Number(p.min) : null,
                max: p.max ? Number(p.max) : null,
              })),
              min_checks_per_batch: v.min_checks_per_batch ? Number(v.min_checks_per_batch) : null,
              min_checks_per_day: v.min_checks_per_day ? Number(v.min_checks_per_day) : null,
              samples_per_check: v.samples_per_check ? Number(v.samples_per_check) : null,
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not create', e.message) },
          )
        }
      />
    </DetailScreen>
  );
}
