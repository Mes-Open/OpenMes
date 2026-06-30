import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet, View } from 'react-native';

import { ListItem } from '@/components/ui/ListItem';
import { ListScreen } from '@/components/ui/ListScreen';
import { Mono } from '@/components/ui/Mono';
import { SearchBar } from '@/components/ui/SearchBar';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useUsers } from '@/hooks/queries/useUsers';

type FilterId = 'all' | 'Admin' | 'Supervisor' | 'Operator';

const ROLE_COLORS: Record<string, string> = {
  Admin: '#D6442F',
  Supervisor: '#EA5A2B',
  Operator: BRAND.amber,
  Maintenance: '#7c3aed',
};

export function UsersList() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [q, setQ] = useState('');
  const [filter, setFilter] = useState<FilterId>('all');

  const query = useUsers({ q: q.trim() || undefined });
  const all = query.data?.data ?? [];

  const counts = useMemo(() => {
    const c = { all: all.length, Admin: 0, Supervisor: 0, Operator: 0 };
    for (const u of all) {
      const role = (u.role ?? u.roles?.[0]?.name ?? '') as keyof typeof c;
      if (role === 'Admin' || role === 'Supervisor' || role === 'Operator') c[role]++;
    }
    return c;
  }, [all]);

  const filtered = useMemo(() => {
    return all.filter((u) => {
      const role = u.role ?? u.roles?.[0]?.name ?? '';
      if (filter !== 'all' && role !== filter) return false;
      return true;
    });
  }, [all, filter]);

  return (
    <ListScreen
      title={t('Users')}
      eyebrow={`${t('ADMIN').toUpperCase()} · ${all.length} ${t('ACCOUNTS').toUpperCase()}`}
      newRoute="/admin/users/new"
      filters={[
        { id: 'all', label: t('All'), count: counts.all },
        { id: 'Admin', label: t('Admin'), count: counts.Admin },
        { id: 'Supervisor', label: t('Supervisor'), count: counts.Supervisor },
        { id: 'Operator', label: t('Operator'), count: counts.Operator },
      ]}
      activeFilter={filter}
      onFilterChange={(id) => setFilter(id as FilterId)}
      extraHeader={
        <SearchBar
          placeholder="Search by name, email, username"
          value={q}
          onChangeText={setQ}
        />
      }
      items={filtered}
      keyExtractor={(u) => String(u.id)}
      isLoading={query.isLoading}
      isError={query.isError}
      error={query.error}
      isFetching={query.isFetching}
      onRefresh={query.refetch}
      emptyTitle={t('No users')}
      renderItem={(item) => {
        const role = item.role ?? item.roles?.[0]?.name ?? '—';
        const roleColor = ROLE_COLORS[role] ?? palette.textMuted;
        return (
          <ListItem
            badge={initials(item.name ?? item.username)}
            title={item.name ?? item.username}
            inlineBadge={{ label: role, color: roleColor }}
            subtitle={item.email ?? item.username}
            trailing={
              item.last_login_at ? (
                <View style={{ alignItems: 'flex-end' }}>
                  <Mono size={10} color={palette.textFaint} letterSpacing={0.4}>
                    {t('LAST').toUpperCase()}
                  </Mono>
                  <Mono size={11} color={palette.textMuted} weight="600" style={{ marginTop: 2 }}>
                    {formatAgo(item.last_login_at)}
                  </Mono>
                </View>
              ) : undefined
            }
            onPress={() => router.push(`/admin/users/${item.id}` as never)}
            chevron={false}
          />
        );
      }}
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

function formatAgo(iso: string): string {
  try {
    const ms = Date.now() - new Date(iso).getTime();
    const m = Math.floor(ms / 60000);
    if (m < 1) return 'now';
    if (m < 60) return `${m}m`;
    const h = Math.floor(m / 60);
    if (h < 24) return `${h}h`;
    const d = Math.floor(h / 24);
    return `${d}d`;
  } catch {
    return '—';
  }
}

const styles = StyleSheet.create({});
void styles;
