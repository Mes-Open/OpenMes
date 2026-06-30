import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';

import { InactiveToggle } from '@/components/ui/InactiveToggle';
import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { StatusPill } from '@/components/ui/StatusPill';
import { useFactoryDivisions } from '@/hooks/queries/useOrgStructure';

export function DivisionsList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const factoryId = Number(id);
  const router = useRouter();
  const [includeInactive, setIncludeInactive] = useState(false);
  const query = useFactoryDivisions(factoryId, includeInactive);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Divisions"
      eyebrow={`FACTORY #${factoryId} · ${items.length} DIVISIONS`}
      newRoute={`/structure/factories/${factoryId}/divisions/new`}
      extraHeader={<InactiveToggle value={includeInactive} onValueChange={setIncludeInactive} />}
      items={items}
      keyExtractor={(d) => String(d.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No divisions"
      emptySubtitle="Tap + to add one."
      renderItem={(item) => (
        <ListItem
          icon="th-large"
          title={item.name}
          eyebrow={item.code}
          subtitle={item.lines_count != null ? `${item.lines_count} LINES` : undefined}
          trailing={
            <StatusPill
              status={item.is_active ? 'IN_PROGRESS' : 'CANCELLED'}
              label={item.is_active ? 'Active' : 'Inactive'}
            />
          }
          onPress={() => router.push(`/structure/divisions/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}
