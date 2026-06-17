import { useRouter } from 'expo-router';
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { InactiveToggle } from '@/components/ui/InactiveToggle';
import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useAdminLines } from '@/hooks/queries/useLines';

export function LinesList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [includeInactive, setIncludeInactive] = useState(false);

  const query = useAdminLines({ include_inactive: includeInactive });
  const items = query.data ?? [];

  return (
    <ListScreen
      title="Production lines"
      eyebrow={`STRUCTURE · ${items.length} LINES`}
      newRoute="/structure/lines/new"
      extraHeader={<InactiveToggle value={includeInactive} onValueChange={setIncludeInactive} />}
      items={items}
      keyExtractor={(l) => String(l.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle="No lines"
      renderItem={(item) => (
        <View>
          <ListItem
            badge={item.code ?? `L${item.id}`}
            title={item.name}
            eyebrow={item.code ?? undefined}
            trailing={
              <StatusPill
                status={item.is_active ? 'IN_PROGRESS' : 'CANCELLED'}
                label={item.is_active ? 'Active' : 'Inactive'}
              />
            }
            onPress={() => router.push(`/structure/lines/${item.id}` as never)}
            chevron={false}
          />
          {item.description ? (
            <View style={[styles.descBlock, { borderColor: palette.border }]}>
              <Mono size={10} color={palette.textFaint} letterSpacing={0.6}>NOTES</Mono>
              <Text style={[styles.desc, { color: palette.textMuted }]} numberOfLines={2}>
                {item.description}
              </Text>
            </View>
          ) : null}
        </View>
      )}
    />
  );
}

const styles = StyleSheet.create({
  descBlock: {
    marginTop: -1,
    paddingHorizontal: 14,
    paddingVertical: 10,
    borderWidth: 1,
    borderTopWidth: 0,
    borderBottomLeftRadius: 14,
    borderBottomRightRadius: 14,
    backgroundColor: 'transparent',
  },
  desc: { fontSize: 13, lineHeight: 19, marginTop: 4 },
});
