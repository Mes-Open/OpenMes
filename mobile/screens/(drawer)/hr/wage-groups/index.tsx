import { useRouter } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Switch, View } from 'react-native';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useWageGroups } from '@/hooks/queries/useHr';

export function WageGroupsList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [includeInactive, setIncludeInactive] = useState(false);
  const query = useWageGroups(includeInactive);
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Wage groups"
      eyebrow={`HR · ${items.length} GROUPS`}
      newRoute="/hr/wage-groups/new"
      extraHeader={
        <View style={styles.toggle}>
          <Mono size={11} color={palette.textFaint} letterSpacing={0.6}>SHOW INACTIVE</Mono>
          <Switch
            value={includeInactive}
            onValueChange={setIncludeInactive}
            trackColor={{ true: BRAND.amber }}
          />
        </View>
      }
      items={items}
      keyExtractor={(w) => String(w.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No wage groups"
      renderItem={(item) => (
        <ListItem
          icon="money"
          title={item.name}
          eyebrow={item.code}
          subtitle={
            [
              item.base_hourly_rate ? `${item.base_hourly_rate} ${item.currency ?? ''}/H` : null,
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
          onPress={() => router.push(`/hr/wage-groups/${item.id}` as never)}
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
