import { useRouter } from 'expo-router';
import { useState } from 'react';

import { Field } from '@/components/ui/Field';
import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { useSkills } from '@/hooks/queries/useHr';

export function SkillsList() {
  const router = useRouter();
  const [q, setQ] = useState('');
  const query = useSkills(q.trim() || undefined);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Skills"
      eyebrow={`HR · ${items.length} SKILLS`}
      newRoute="/hr/skills/new"
      extraHeader={
        <Field
          label="Search"
          value={q}
          onChangeText={setQ}
          autoCapitalize="none"
          placeholder="name or code"
        />
      }
      items={items}
      keyExtractor={(s) => String(s.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No skills"
      renderItem={(item) => (
        <ListItem
          icon="graduation-cap"
          title={item.name}
          eyebrow={item.code}
          subtitle={item.workers_count != null ? `${item.workers_count} workers` : undefined}
          onPress={() => router.push(`/hr/skills/${item.id}` as never)}
        />
      )}
    />
  );
}
