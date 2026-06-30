import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';

import { InactiveToggle } from '@/components/ui/InactiveToggle';
import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { StatusPill } from '@/components/ui/StatusPill';
import { useWorkstations } from '@/hooks/queries/useLines';

export function WorkstationsList() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const lineId = Number(id);
  const router = useRouter();
  const [includeInactive, setIncludeInactive] = useState(false);

  const query = useWorkstations(lineId, includeInactive);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Workstations"
      eyebrow={`LINE #${lineId} · ${items.length} STATIONS`}
      newRoute={`/structure/lines/${lineId}/workstations/new`}
      extraHeader={<InactiveToggle value={includeInactive} onValueChange={setIncludeInactive} />}
      items={items}
      keyExtractor={(w) => String(w.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No workstations"
      emptySubtitle="Tap + to add one."
      renderItem={(item) => (
        <ListItem
          icon="industry"
          title={item.name}
          eyebrow={item.code}
          subtitle={
            [
              item.workstation_type,
              item.template_steps_count != null ? `${item.template_steps_count} TEMPLATE STEPS` : null,
              item.workers_count != null ? `${item.workers_count} WORKERS` : null,
            ]
              .filter(Boolean)
              .join(' · ') || undefined
          }
          trailing={
            <StatusPill
              status={item.is_active ? 'IN_PROGRESS' : 'CANCELLED'}
              label={item.is_active ? 'Active' : 'Inactive'}
            />
          }
          onPress={() =>
            router.push(`/structure/lines/${lineId}/workstations/${item.id}` as never)
          }
          chevron={false}
        />
      )}
    />
  );
}
