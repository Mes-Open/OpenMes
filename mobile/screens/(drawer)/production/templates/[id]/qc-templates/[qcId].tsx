import { useLocalSearchParams, useRouter } from 'expo-router';
import { useMemo } from 'react';
import { Alert } from 'react-native';

import { QcTemplateForm } from '@/components/admin/QcTemplateForm';
import { DangerZone, DetailScreen } from '@/components/ui/Detail';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import {
  useDeleteQcTemplate,
  useQcTemplatesForProcessTemplate,
  useUpdateQcTemplate,
} from '@/hooks/queries/useProductionControls';

export function EditQcTemplateScreen() {
  const { id, qcId } = useLocalSearchParams<{ id: string; qcId: string }>();
  const processTemplateId = Number(id);
  const numericQcId = Number(qcId);
  const router = useRouter();

  const list = useQcTemplatesForProcessTemplate(processTemplateId);
  const updateMutation = useUpdateQcTemplate(processTemplateId);
  const deleteMutation = useDeleteQcTemplate(processTemplateId);

  const template = useMemo(
    () => list.data?.find((t) => t.id === numericQcId),
    [list.data, numericQcId],
  );

  if (list.isLoading) return <LoadingState />;
  if (list.isError) return <ErrorState error={list.error} onRetry={list.refetch} />;
  if (!template) {
    return <ErrorState error={new Error('QC template not found')} onRetry={list.refetch} />;
  }

  return (
    <DetailScreen>
      <QcTemplateForm
        mode="edit"
        initial={template}
        submitting={updateMutation.isPending}
        onSubmit={(v) =>
          updateMutation.mutate(
            {
              id: template.id,
              input: {
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
            },
            { onSuccess: () => router.back(), onError: (e: Error) => Alert.alert('Could not update', e.message) },
          )
        }
      />

      <DangerZone
        deleteLabel="Delete QC template"
        deleteConfirmTitle="Delete QC template"
        deleteConfirmMessage={`Delete "${template.name}"?`}
        deleteLoading={deleteMutation.isPending}
        onDelete={() =>
          deleteMutation.mutate(template.id, {
            onSuccess: () => router.back(),
            onError: (e: Error) => Alert.alert('Could not delete', e.message),
          })
        }
      />
    </DetailScreen>
  );
}
