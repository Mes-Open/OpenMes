import { useRouter } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import { Switch } from '@/components/ui/Switch';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCrews } from '@/hooks/queries/useHr';

export function CrewsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [includeInactive, setIncludeInactive] = useState(false);
  const query = useCrews(includeInactive);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Crews"
      eyebrow={`HR · ${items.length} CREWS`}
      newRoute="/hr/crews/new"
      extraHeader={
        <View style={styles.toggle}>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>SHOW INACTIVE</Mono>
          <Switch value={includeInactive} onValueChange={setIncludeInactive} />
        </View>
      }
      items={items}
      keyExtractor={(c) => String(c.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No crews"
      renderItem={(item) => (
        <ListItem
          icon="users"
          title={item.name}
          eyebrow={item.code}
          subtitle={
            [
              item.leader ? `LED BY ${(item.leader.name ?? item.leader.username).toUpperCase()}` : null,
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
          onPress={() => router.push(`/hr/crews/${item.id}` as never)}
          chevron={false}
        />
      )}
    />
  );
}

const styles = StyleSheet.create({
  toggle: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: 6,
    paddingHorizontal: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#00000010',
  },
});
