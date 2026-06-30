import { FontAwesome } from '@expo/vector-icons';
import { formatDistanceToNowStrict, parseISO } from 'date-fns';
import { useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { isAxiosError } from 'axios';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useConnectSubiekt,
  useSubiektContractors,
  useSubiektProducts,
  useSubiektStatus,
  useSubiektStock,
  useSubiektWarehouses,
  useSyncSubiekt,
} from '@/hooks/queries/useSubiekt';

type TabId = 'products' | 'contractors' | 'warehouses' | 'stock';

const TABS: { id: TabId; label: string }[] = [
  { id: 'products', label: 'Products' },
  { id: 'contractors', label: 'Contractors' },
  { id: 'warehouses', label: 'Warehouses' },
  { id: 'stock', label: 'Stock' },
];

function isModuleDisabled(error: unknown): boolean {
  if (!error) return false;
  if (isAxiosError(error)) return error.response?.status === 404;
  return false;
}

export function SubiektNexoScreen() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const [tab, setTab] = useState<TabId>('products');

  const statusQ = useSubiektStatus();
  const productsQ = useSubiektProducts(tab === 'products');
  const contractorsQ = useSubiektContractors(tab === 'contractors');
  const warehousesQ = useSubiektWarehouses(tab === 'warehouses');
  const stockQ = useSubiektStock(tab === 'stock');

  const connectMutation = useConnectSubiekt();
  const syncMutation = useSyncSubiekt();

  // If status returns 404, the module isn't enabled. The Sfera service may
  // also fail to connect — distinguish "not enabled" (404) from "not connected".
  if (isModuleDisabled(statusQ.error)) {
    return <DisabledState />;
  }

  const status = statusQ.data;
  const connected = status?.connection?.connected ?? false;
  const endpoint = status?.connection?.endpoint ?? null;
  const lastSync = status?.connection?.last_sync_at ?? null;
  const lastSyncAgo = lastSync
    ? safeDistance(lastSync)
    : null;

  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="SubiektNexo" subtitle="ERP INTEGRATION · SFERA API" />
      <ScrollView contentContainerStyle={styles.scroll}>
        {/* Status hero */}
        <View style={styles.hero}>
          <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
            <View
              style={[
                styles.heroDot,
                { backgroundColor: connected ? palette.success : palette.danger },
              ]}
            />
            <Mono size={11} color={connected ? palette.success : palette.danger} weight="700" letterSpacing={0.6}>
              {connected ? 'CONNECTED' : 'DISCONNECTED'}
            </Mono>
          </View>
          <Text style={styles.heroTitle}>InsERT Subiekt nexo</Text>
          <Mono size={11} color="#6F6C66" style={{ marginTop: 4 }} letterSpacing={0.4}>
            {endpoint ? `ENDPOINT ${endpoint.toUpperCase()}` : 'ENDPOINT —'}
            {lastSyncAgo ? ` · LAST SYNC ${lastSyncAgo.toUpperCase()}` : ''}
          </Mono>
          <View style={{ flexDirection: 'row', gap: 8, marginTop: 14 }}>
            <Pressable
              onPress={() =>
                connectMutation.mutate(undefined, {
                  onError: (e: Error) => Alert.alert('Reconnect failed', e.message),
                })
              }
              style={({ pressed }) => [
                styles.heroBtnSecondary,
                { borderColor: BRAND.amber, opacity: connectMutation.isPending || pressed ? 0.85 : 1 },
              ]}>
              <Mono size={11} color={BRAND.amber} weight="700" letterSpacing={0.6}>
                {connectMutation.isPending ? 'CONNECTING…' : 'RECONNECT'}
              </Mono>
            </Pressable>
            <Pressable
              onPress={() =>
                syncMutation.mutate(undefined, {
                  onError: (e: Error) => Alert.alert('Sync failed', e.message),
                  onSuccess: () => Alert.alert('Sync started', 'Products are syncing in the background.'),
                })
              }
              disabled={!connected || syncMutation.isPending}
              style={({ pressed }) => [
                styles.heroBtnPrimary,
                { backgroundColor: BRAND.amber, opacity: !connected || syncMutation.isPending ? 0.5 : pressed ? 0.9 : 1 },
              ]}>
              <Mono size={11} color="#1a1208" weight="700" letterSpacing={0.6}>
                {syncMutation.isPending ? 'SYNCING…' : 'SYNC PRODUCTS'}
              </Mono>
            </Pressable>
          </View>
        </View>

        {/* Tabs */}
        <View style={[styles.tabsTrack, { backgroundColor: palette.surfaceAlt }]}>
          {TABS.map((t) => {
            const active = t.id === tab;
            const count = countFor(t.id, productsQ, contractorsQ, warehousesQ, stockQ);
            return (
              <Pressable
                key={t.id}
                onPress={() => setTab(t.id)}
                style={[
                  styles.tab,
                  active && {
                    backgroundColor: palette.surface,
                    boxShadow: '0px 1px 2px rgba(0, 0, 0, 0.06)',
                  },
                ]}>
                <Mono
                  size={10.5}
                  color={active ? palette.text : palette.textMuted}
                  weight="600">
                  {t.label}
                </Mono>
                <Mono
                  size={9.5}
                  color={palette.textFaint}
                  style={{ marginTop: 1 }}>
                  {count != null ? String(count) : '—'}
                </Mono>
              </Pressable>
            );
          })}
        </View>

        <View style={[styles.readonlyBanner, { backgroundColor: palette.warningSoft }]}>
          <Mono size={11} color="#a8650a" letterSpacing={0.3}>
            ⓘ Read-only on mobile. Edit products & sync rules from web admin.
          </Mono>
        </View>

        {tab === 'products' ? (
          <ListSection
            loading={productsQ.isLoading}
            error={productsQ.error}
            empty="No products"
            items={productsQ.data ?? []}
            renderItem={(p) => (
              <RowProduct key={p.symbol} symbol={p.symbol} name={p.name} value={p.stock != null ? String(p.stock) : null} valueLabel="STOCK" />
            )}
          />
        ) : tab === 'contractors' ? (
          <ListSection
            loading={contractorsQ.isLoading}
            error={contractorsQ.error}
            empty="No contractors"
            items={contractorsQ.data ?? []}
            renderItem={(c) => (
              <RowProduct key={c.symbol} symbol={c.symbol} name={c.name} value={c.city ?? null} valueLabel="CITY" />
            )}
          />
        ) : tab === 'warehouses' ? (
          <ListSection
            loading={warehousesQ.isLoading}
            error={warehousesQ.error}
            empty="No warehouses"
            items={warehousesQ.data ?? []}
            renderItem={(w) => (
              <RowProduct key={w.symbol} symbol={w.symbol} name={w.name} value={null} valueLabel="" />
            )}
          />
        ) : (
          <ListSection
            loading={stockQ.isLoading}
            error={stockQ.error}
            empty="No stock entries"
            items={stockQ.data ?? []}
            renderItem={(s) => (
              <RowProduct
                key={`${s.warehouse}-${s.symbol}`}
                symbol={`${s.symbol} · ${s.warehouse}`}
                name={s.name}
                value={String(s.quantity)}
                valueLabel="QTY"
                warn={s.quantity <= 0}
              />
            )}
          />
        )}
      </ScrollView>
    </View>
  );
}

function ListSection<T>({
  items,
  loading,
  error,
  empty,
  renderItem,
}: {
  items: T[];
  loading: boolean;
  error: unknown;
  empty: string;
  renderItem: (item: T) => React.ReactNode;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  if (isModuleDisabled(error)) {
    return <DisabledState inline />;
  }
  if (error) {
    return (
      <Card>
        <Mono size={11} color={palette.danger} style={{ textAlign: 'center', padding: 8 }}>
          {(error as Error).message?.toUpperCase() ?? 'REQUEST FAILED'}
        </Mono>
      </Card>
    );
  }
  if (loading) {
    return (
      <Card>
        <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 8 }}>
          LOADING…
        </Mono>
      </Card>
    );
  }
  if (items.length === 0) {
    return (
      <Card>
        <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 14 }}>
          {empty.toUpperCase()}
        </Mono>
      </Card>
    );
  }
  return <Card style={{ padding: 0, overflow: 'hidden' }}>{items.map(renderItem)}</Card>;
}

function RowProduct({
  symbol,
  name,
  value,
  valueLabel,
  warn,
}: {
  symbol: string;
  name: string;
  value: string | null;
  valueLabel: string;
  warn?: boolean;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  return (
    <View
      style={[
        styles.listRow,
        { borderBottomColor: palette.border },
      ]}>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Text style={[styles.listName, { color: palette.text }]} numberOfLines={1}>
          {name}
        </Text>
        <Mono size={10} color={palette.textFaint} style={{ marginTop: 3 }} letterSpacing={0.4}>
          {symbol.toUpperCase()}
        </Mono>
      </View>
      {value != null ? (
        <View style={{ alignItems: 'flex-end' }}>
          <Mono size={14} color={warn ? palette.danger : palette.text} weight="700">
            {value}
          </Mono>
          {valueLabel ? (
            <Mono size={9} color={palette.textFaint} letterSpacing={0.4}>
              {valueLabel}
            </Mono>
          ) : null}
        </View>
      ) : null}
    </View>
  );
}

function DisabledState({ inline }: { inline?: boolean }) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const block = (
    <View
      style={[
        styles.disabledCard,
        {
          backgroundColor: palette.surfaceAlt,
          borderColor: palette.border,
        },
      ]}>
      <View style={[styles.disabledIcon, { backgroundColor: palette.surface }]}>
        <FontAwesome name="cube" size={22} color={palette.textFaint} />
      </View>
      <Text style={[styles.disabledTitle, { color: palette.text }]}>
        SubiektNexo is not enabled
      </Text>
      <Text style={[styles.disabledSub, { color: palette.textMuted }]}>
        Enable from web admin → Modules to use this screen.
      </Text>
    </View>
  );

  if (inline) return block;
  return (
    <View style={{ flex: 1, backgroundColor: palette.background }}>
      <ScreenHeader back title="SubiektNexo" subtitle="ERP INTEGRATION · SFERA API" />
      <ScrollView contentContainerStyle={styles.scroll}>{block}</ScrollView>
    </View>
  );
}

function countFor(
  tab: TabId,
  products: ReturnType<typeof useSubiektProducts>,
  contractors: ReturnType<typeof useSubiektContractors>,
  warehouses: ReturnType<typeof useSubiektWarehouses>,
  stock: ReturnType<typeof useSubiektStock>,
): number | null {
  switch (tab) {
    case 'products':
      return products.data?.length ?? null;
    case 'contractors':
      return contractors.data?.length ?? null;
    case 'warehouses':
      return warehouses.data?.length ?? null;
    case 'stock':
      return stock.data?.length ?? null;
  }
}

function safeDistance(iso: string): string | null {
  try {
    return formatDistanceToNowStrict(parseISO(iso), { addSuffix: false });
  } catch {
    return null;
  }
}

const styles = StyleSheet.create({
  scroll: { padding: 16, gap: 14, paddingBottom: 32 },
  hero: { backgroundColor: '#F6F5F1', borderRadius: 14, padding: 18 },
  heroDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  heroTitle: { color: '#fff', fontSize: 22, fontWeight: '700', letterSpacing: -0.3, marginTop: 8 },
  heroBtnSecondary: {
    flex: 1,
    height: 40,
    borderRadius: 8,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroBtnPrimary: {
    flex: 1,
    height: 40,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tabsTrack: {
    flexDirection: 'row',
    padding: 4,
    borderRadius: 10,
    gap: 4,
  },
  tab: { flex: 1, paddingVertical: 8, borderRadius: 7, alignItems: 'center' },
  readonlyBanner: { padding: 10, borderRadius: 8 },
  listRow: {
    padding: 12,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  listName: { fontSize: 13, fontWeight: '500' },
  disabledCard: {
    padding: 16,
    borderRadius: 12,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    alignItems: 'center',
  },
  disabledIcon: {
    width: 44,
    height: 44,
    borderRadius: 22,
    alignItems: 'center',
    justifyContent: 'center',
  },
  disabledTitle: { fontSize: 13, fontWeight: '600', marginTop: 10 },
  disabledSub: { fontSize: 11, marginTop: 4, textAlign: 'center' },
});
