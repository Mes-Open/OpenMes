import { useLocalSearchParams, useRouter } from 'expo-router';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { useQcTemplatesForProcessTemplate } from '@/hooks/queries/useProductionControls';

export function QcTemplatesList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const processTemplateId = Number(id);
  const router = useRouter();

  const query = useQcTemplatesForProcessTemplate(processTemplateId);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="QC templates"
      eyebrow={`PROCESS TEMPLATE #${processTemplateId} · ${items.length} TEMPLATES`}
      newRoute={`/production/templates/${processTemplateId}/qc-templates/new`}
      items={items}
      keyExtractor={(t) => String(t.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No QC templates"
      emptySubtitle="Add one to require quality checks for batches running this template."
      renderItem={(item) => (
        <ListItem
          icon="check-square-o"
          title={item.name}
          eyebrow={`${item.parameters.length} PARAM${item.parameters.length === 1 ? '' : 'S'}`}
          subtitle={
            [
              item.samples_per_check ? `${item.samples_per_check} SAMPLES/CHECK` : null,
              item.min_checks_per_batch ? `MIN ${item.min_checks_per_batch}/BATCH` : null,
            ]
              .filter(Boolean)
              .join(' · ') || undefined
          }
          onPress={() =>
            router.push(
              `/production/templates/${processTemplateId}/qc-templates/${item.id}` as never,
            )
          }
        />
      )}
    />
  );
}
