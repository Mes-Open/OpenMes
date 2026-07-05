/**
 * Users & Accounts — 1:1 with the web admin/users table (Pages/admin/users/Index.jsx):
 * the shared DataTable with the web's column set (Name / Username / Email / Type /
 * Role-Station / Last Login) and per-row actions (Edit / Delete — Delete hidden
 * for the signed-in account, like web). Search is sent server-side, so the
 * DataTable's own search box stays off. Data via REST useUsers.
 */
import { useState } from 'react';
import { useRouter } from 'expo-router';
import { Alert, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useTranslation } from 'react-i18next';

import { colors, fonts } from '@openmes/ui';
import { DataTable } from '@openmes/ui/table';
import { SearchField } from '@openmes/ui/native';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import { useUsers } from '@/hooks/queries/useUsers';
import { useDeleteUser } from '@/hooks/mutations/users';
import { useAuthStore } from '@/stores/authStore';
import type { User } from '@/types/api';

function roleName(u: User): string {
  const r = u.role ?? u.roles?.[0]?.name;
  return r ? String(r) : '—';
}

export function UsersList() {
  const { t } = useTranslation();
  const router = useRouter();
  const [q, setQ] = useState('');

  const query = useUsers({ q: q.trim() || undefined });
  const del = useDeleteUser();
  const currentUserId = useAuthStore((s) => s.user?.id);
  const rows = query.data?.data ?? [];

  const onDelete = (u: User) =>
    Alert.alert(t('Delete account'), t('Delete "{{name}}"?', { name: u.name ?? u.username }), [
      { text: t('Cancel'), style: 'cancel' },
      {
        text: t('Delete'),
        style: 'destructive',
        onPress: () => del.mutate(u.id, { onError: (e: Error) => Alert.alert(t('Could not delete'), e.message) }),
      },
    ]);

  return (
    <View style={styles.screen}>
      <View style={styles.head}>
        <Text style={styles.h1}>{t('Users & Accounts')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('+ New Account')} size="sm" onPress={() => router.push('/admin/users/new' as never)} />
      </View>

      <View style={styles.filters}>
        <SearchField value={q} onChange={setQ} placeholder={t('Search by name, email, username')} />
      </View>

      {query.isLoading && !query.data ? (
        <LoadingState />
      ) : query.isError && !query.data ? (
        <ErrorState error={query.error} onRetry={query.refetch} />
      ) : (
        <ScrollView
          style={styles.screen}
          contentContainerStyle={styles.content}
          refreshControl={<RefreshControl refreshing={query.isFetching} onRefresh={query.refetch} tintColor={colors.accent} />}>
          <DataTable<User>
            data={rows as User[]}
            searchable={false}
            columnsLabel={t('Columns')}
            columnsMenuLabel={t('Toggle columns')}
            emptyText={t('No accounts yet.')}
            onRowPress={(u) => router.push(`/admin/users/${u.id}` as never)}
            columns={[
              { key: 'name', label: t('Name'), flex: 1.3, render: (u) => <Text numberOfLines={1} style={styles.name}>{u.name ?? u.username}</Text> },
              { key: 'username', label: t('Username'), flex: 1, render: (u) => <Mono size={11} color={colors.muted}>{u.username}</Mono> },
              { key: 'email', label: t('Email'), flex: 1.3, render: (u) => u.email ?? '—' },
              {
                key: 'account_type',
                label: t('Type'),
                width: 96,
                render: (u) => <Text style={styles.typeChip}>{u.account_type === 'workstation' ? t('Workstation') : t('User')}</Text>,
              },
              {
                key: 'role',
                label: t('Role / Station'),
                flex: 1,
                render: (u) => (u.account_type === 'workstation' ? '—' : roleName(u)),
              },
              {
                key: 'last_login_at',
                label: t('Last Login'),
                width: 110,
                render: (u) => (
                  <Mono size={10} color={colors.muted}>
                    {u.last_login_at ? String(u.last_login_at).slice(0, 16).replace('T', ' ') : t('never')}
                  </Mono>
                ),
              },
            ]}
            actions={(u) => {
              const acts: { label: string; icon: 'edit' | 'delete'; onPress: () => void }[] = [
                { label: t('Edit'), icon: 'edit', onPress: () => router.push(`/admin/users/${u.id}` as never) },
              ];
              if (String(u.id) !== String(currentUserId)) {
                acts.push({ label: t('Delete'), icon: 'delete', onPress: () => onDelete(u) });
              }
              return acts;
            }}
          />
        </ScrollView>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { paddingHorizontal: 18, paddingBottom: 32 },
  head: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 18, paddingTop: 16 },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  filters: { paddingHorizontal: 18, paddingVertical: 12 },
  name: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  typeChip: { alignSelf: 'flex-start', fontSize: 11, fontFamily: fonts.sans.native.medium, color: colors.accent, backgroundColor: colors.chip, paddingHorizontal: 7, paddingVertical: 2, borderRadius: 4, overflow: 'hidden' },
});
