import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { View } from 'react-native';

import { Field } from '@/components/ui/Field';
import { ListItem } from '@/components/ui/ListItem';
import { ListScreen, type FilterChip } from '@/components/ui/ListScreen';
import { StatusPill } from '@/components/ui/StatusPill';
import { useCrews, useWorkers } from '@/hooks/queries/useHr';

export function WorkersList() {
  const router = useRouter();
  const [q, setQ] = useState('');
  const [crewId, setCrewId] = useState<number | null>(null);

  const filters = useMemo(
    () => ({
      q: q.trim() || undefined,
      crew_id: crewId ?? undefined,
    }),
    [q, crewId],
  );
  const query = useWorkers(filters);
  const crewsQuery = useCrews(false);

  const crews = crewsQuery.data ?? [];
  const chips: FilterChip[] = [
    { id: 'all', label: 'All crews' },
    ...crews.map((c) => ({ id: String(c.id), label: c.name })),
  ];

  const items = query.data?.data ?? [];

  return (
    <ListScreen
      title="Workers"
      eyebrow={`HR · ${items.length} ITEMS`}
      newRoute="/hr/workers/new"
      filters={chips}
      activeFilter={crewId == null ? 'all' : String(crewId)}
      onFilterChange={(id) => setCrewId(id === 'all' ? null : Number(id))}
      extraHeader={
        <Field
          label="Search"
          value={q}
          onChangeText={setQ}
          placeholder="name, code, email"
          autoCapitalize="none"
        />
      }
      items={items}
      keyExtractor={(w) => String(w.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No workers"
      emptySubtitle="Add a worker to the HR roster."
      renderItem={(item) => (
        <ListItem
          badge={initials(item.name)}
          title={item.name}
          eyebrow={item.code}
          subtitle={[item.crew?.name, item.wage_group?.name].filter(Boolean).join(' · ') || undefined}
          trailing={
            <StatusPill status={item.is_active ? 'IN_PROGRESS' : 'CANCELLED'} label={item.is_active ? 'Active' : 'Inactive'} />
          }
          onPress={() => router.push(`/hr/workers/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}

function initials(name: string) {
  return name
    .split(/\s+/)
    .map((p) => p[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase();
}
