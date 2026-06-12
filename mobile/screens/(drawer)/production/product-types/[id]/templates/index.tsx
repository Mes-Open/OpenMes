import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';

import { InactiveToggle } from '@/components/ui/InactiveToggle';
import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { StatusPill } from '@/components/ui/StatusPill';
import { useProcessTemplatesForProductType } from '@/hooks/queries/useProductTypes';

export function TemplatesList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productTypeId = Number(id);
  const router = useRouter();
  const [includeInactive, setIncludeInactive] = useState(true);

  const query = useProcessTemplatesForProductType(productTypeId, includeInactive);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Process templates"
      eyebrow={`PRODUCT TYPE #${productTypeId} · ${items.length} TEMPLATES`}
      extraHeader={<InactiveToggle value={includeInactive} onValueChange={setIncludeInactive} />}
      items={items}
      keyExtractor={(t) => String(t.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No process templates"
      emptySubtitle="Create or edit templates on the web app — drag-and-drop step builder isn't available on mobile."
      renderItem={(item) => (
        <ListItem
          icon="flask"
          title={item.name}
          eyebrow={`v${item.version}`}
          subtitle={`${item.steps?.length ?? 0} STEPS${item.is_active ? '' : ' · INACTIVE'}`}
          trailing={
            <StatusPill
              status={item.is_active ? 'IN_PROGRESS' : 'CANCELLED'}
              label={item.is_active ? 'Active' : 'Inactive'}
            />
          }
          onPress={() => router.push(`/production/templates/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}
