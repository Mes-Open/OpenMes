import { FontAwesome } from '@expo/vector-icons';
import { DrawerActions, useNavigation } from '@react-navigation/native';
import { CameraView, useCameraPermissions, type BarcodeScanningResult } from 'expo-camera';
import { useRouter } from 'expo-router';
import { useCallback, useRef, useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { ScreenHeader } from '@/components/ui/ScreenHeader';
import { listWorkOrders } from '@/api/workOrders';
import { ApiError } from '@/api/client';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useScanEan } from '@/hooks/queries/usePackaging';

interface RecentScan {
  value: string;
  ok: boolean;
  label: string;
  at: number;
}

export function ScanTab() {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  const [permission, requestPermission] = useCameraPermissions();
  const [busy, setBusy] = useState(false);
  const [recent, setRecent] = useState<RecentScan | null>(null);
  const lastScanRef = useRef<{ value: string; at: number } | null>(null);
  const router = useRouter();
  const scanMutation = useScanEan();

  const handleScan = useCallback(
    async (result: BarcodeScanningResult) => {
      const value = result.data?.trim();
      if (!value || busy) return;
      const last = lastScanRef.current;
      if (last && last.value === value && Date.now() - last.at < 2500) return;
      lastScanRef.current = { value, at: Date.now() };

      setBusy(true);
      try {
        try {
          const scanResult = await scanMutation.mutateAsync(value);
          const wo = scanResult.work_order;
          setRecent({
            value,
            ok: true,
            label: `${wo.product} · ${wo.packed_qty}/${wo.planned_qty}`,
            at: Date.now(),
          });
          return;
        } catch (e) {
          if (e instanceof ApiError && e.status === 404) {
            // fall through to WO lookup
          } else if (e instanceof ApiError && e.status === 422) {
            setRecent({ value, ok: false, label: e.message, at: Date.now() });
            return;
          } else {
            throw e;
          }
        }

        const matches = await listWorkOrders().catch(() => []);
        const wo = matches.find((w) => w.order_no === value);
        if (wo) {
          router.push(`/work-orders/${wo.id}`);
          setRecent({ value, ok: true, label: `${t('Work order')} ${wo.order_no}`, at: Date.now() });
          return;
        }
        setRecent({ value, ok: false, label: t('Unknown code — not an EAN or work order'), at: Date.now() });
      } catch (e) {
        setRecent({
          value,
          ok: false,
          label: `${t('Scan failed')}: ${e instanceof Error ? e.message : t('unknown error')}`,
          at: Date.now(),
        });
      } finally {
        setBusy(false);
      }
    },
    [busy, router, scanMutation],
  );

  if (!permission) {
    return <View style={[styles.center, { backgroundColor: palette.background }]} />;
  }

  if (!permission.granted) {
    return (
      <View style={{ flex: 1, backgroundColor: palette.background }}>
        <ScreenHeader title="Scan station" />
        <View style={[styles.center, { backgroundColor: palette.background }]}>
          <View style={[styles.permissionIcon, { backgroundColor: palette.surface, borderColor: palette.border }]}>
            <FontAwesome name="qrcode" size={28} color={BRAND.amber} />
          </View>
          <Text style={[styles.title, { color: palette.text }]}>{t('Scanner needs camera access')}</Text>
          <Text style={[styles.body, { color: palette.textMuted }]}>
            {t('We use the camera only to read barcodes and QR codes for work orders and EAN labels.')}
          </Text>
          <Button title="Grant access" onPress={requestPermission} size="lg" style={{ marginTop: 8 }} />
        </View>
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: '#000' }}>
      <CameraView
        style={StyleSheet.absoluteFill}
        facing="back"
        onBarcodeScanned={handleScan}
        barcodeScannerSettings={{
          barcodeTypes: ['qr', 'ean13', 'ean8', 'code128', 'code39', 'upc_a', 'upc_e'],
        }}
      />
      <View style={styles.dimOverlay} />

      <TopBar />

      {/* Reticle */}
      <View style={styles.reticleWrap}>
        <View style={styles.reticle}>
          <Corner pos="tl" />
          <Corner pos="tr" />
          <Corner pos="bl" />
          <Corner pos="br" />
          <View style={styles.scanLine} />
        </View>
      </View>

      {/* Hint */}
      <View style={styles.hintWrap}>
        <Mono size={11} color="#bbb" letterSpacing={1}>{t('SCANNING').toUpperCase()}</Mono>
        <Text style={styles.hintTitle}>{t('Align barcode within frame')}</Text>
        <Mono size={11} color="#888">EAN · {t('WORK ORDER').toUpperCase()} · {t('LOT').toUpperCase()} · {t('MATERIAL').toUpperCase()}</Mono>
      </View>

      {/* Recent scan */}
      {recent ? (
        <View style={styles.recentCard}>
          <View style={styles.recentTopRow}>
            <Mono size={10} color="#bbb" letterSpacing={0.6}>
              {t('LAST SCAN').toUpperCase()} · {new Date(recent.at).toLocaleTimeString().slice(0, 5)}
            </Mono>
            <Mono
              size={10}
              color={recent.ok ? '#1C9A55' : '#D6442F'}
              letterSpacing={0.6}>
              {recent.ok ? `✓ ${t('MATCHED').toUpperCase()}` : `✕ ${t('FAILED').toUpperCase()}`}
            </Mono>
          </View>
          <View style={styles.recentRow}>
            <View style={[styles.recentIcon, { backgroundColor: recent.ok ? BRAND.amber : '#FBEAE6' }]}>
              <FontAwesome
                name={recent.ok ? 'cube' : 'exclamation'}
                size={16}
                color={recent.ok ? '#1a1208' : '#D6442F'}
              />
            </View>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Mono size={12} color="#fff">{recent.value}</Mono>
              <Text style={styles.recentLabel} numberOfLines={2}>
                {recent.label}
              </Text>
            </View>
          </View>
        </View>
      ) : null}
    </View>
  );
}

function TopBar() {
  const insets = useSafeAreaInsets();
  const navigation = useNavigation();
  const { t } = useTranslation();
  return (
    <View style={[styles.topBar, { top: insets.top + 8 }]}>
      <Pressable
        onPress={() => navigation.dispatch(DrawerActions.openDrawer())}
        hitSlop={8}
        style={({ pressed }) => [styles.topBtn, { opacity: pressed ? 0.6 : 1 }]}>
        <FontAwesome name="bars" size={16} color="#fff" />
      </Pressable>
      <View style={styles.topPill}>
        <Mono size={10} color="#fff" letterSpacing={0.8}>{t('SCAN STATION').toUpperCase()}</Mono>
      </View>
      <View style={styles.topBtn} />
    </View>
  );
}

function Corner({ pos }: { pos: 'tl' | 'tr' | 'bl' | 'br' }) {
  const map = {
    tl: { top: 0, left: 0, borderRightWidth: 0, borderBottomWidth: 0 },
    tr: { top: 0, right: 0, borderLeftWidth: 0, borderBottomWidth: 0 },
    bl: { bottom: 0, left: 0, borderRightWidth: 0, borderTopWidth: 0 },
    br: { bottom: 0, right: 0, borderLeftWidth: 0, borderTopWidth: 0 },
  } as const;
  return <View style={[styles.cornerBase, map[pos]]} />;
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28, gap: 10 },
  title: { fontSize: 18, fontWeight: '700', textAlign: 'center', letterSpacing: -0.2 },
  body: { fontSize: 14, textAlign: 'center', lineHeight: 20 },
  permissionIcon: {
    width: 64,
    height: 64,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    marginBottom: 6,
  },
  dimOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'rgba(0,0,0,0.35)',
    pointerEvents: 'none',
  },
  topBar: {
    position: 'absolute',
    left: 18,
    right: 18,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },
  topBtn: {
    width: 36,
    height: 36,
    borderRadius: 10,
    backgroundColor: 'rgba(0,0,0,0.5)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  topPill: {
    paddingVertical: 6,
    paddingHorizontal: 12,
    borderRadius: 999,
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  reticleWrap: { ...StyleSheet.absoluteFillObject, alignItems: 'center', justifyContent: 'center', pointerEvents: 'none' },
  reticle: { width: 260, height: 260 },
  cornerBase: { position: 'absolute', width: 26, height: 26, borderWidth: 3, borderColor: BRAND.amber },
  scanLine: { position: 'absolute', left: 0, right: 0, top: '50%', height: 2, backgroundColor: BRAND.amber },
  hintWrap: {
    position: 'absolute',
    bottom: 220,
    left: 0,
    right: 0,
    alignItems: 'center',
    gap: 4,
    pointerEvents: 'none',
  },
  hintTitle: { color: '#fff', fontSize: 18, fontWeight: '500', letterSpacing: -0.2 },
  recentCard: {
    position: 'absolute',
    bottom: 32,
    left: 18,
    right: 18,
    backgroundColor: 'rgba(20,20,22,0.92)',
    borderColor: '#E6E4DE',
    borderWidth: 1,
    borderRadius: 14,
    padding: 14,
    gap: 10,
  },
  recentTopRow: { flexDirection: 'row', justifyContent: 'space-between' },
  recentRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  recentIcon: { width: 36, height: 36, borderRadius: 10, alignItems: 'center', justifyContent: 'center' },
  recentLabel: { color: '#fff', fontSize: 13, marginTop: 2 },
});
