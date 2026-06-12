import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { ConsumeLotModal } from '@/components/materialLots/ConsumeLotModal';
import { Card } from '@/components/ui/Card';
import { DetailScreen } from '@/components/ui/Detail';
import { Mono } from '@/components/ui/Mono';
import { ErrorState, LoadingState } from '@/components/ui/StateViews';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import {
  useLotBackwardGenealogy,
  useLotForwardGenealogy,
  useMaterialLot,
} from '@/hooks/queries/useMaterialLots';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { BatchStepLotConsumption, MaterialLotStatus } from '@/api/materialLots';

const STATUS_COLOR: Record<string, string> = {
  available: '#1f9d6c',
  pending_inspection: BRAND.amber,
  quarantined: '#7c3aed',
  consumed: '#8a8780',
  scrapped: '#dc2626',
  expired: '#dc2626',
};

/**
 * Lot detail with backward (sources) + forward (consumed into) genealogy.
 * Matches ScreenLotGenealogy from v10. Pulls from /forward-genealogy +
 * /backward-genealogy endpoints in parallel.
 */
export function LotGenealogyScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const numericId = Number(id);
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const lotQ = useMaterialLot(numericId);
  const fwdQ = useLotForwardGenealogy(numericId);
  const bwdQ = useLotBackwardGenealogy(numericId);

  const canConsume = isSupervisorOrAdmin(useAuthStore((s) => s.user));
  const [consumeOpen, setConsumeOpen] = useState(false);

  if (lotQ.isLoading) return <LoadingState />;
  if (lotQ.isError || !lotQ.data)
    return <ErrorState error={lotQ.error} onRetry={lotQ.refetch} />;

  const lot = lotQ.data;
  const statusColor =
    STATUS_COLOR[(lot.status as MaterialLotStatus) ?? 'available'] ?? palette.textMuted;

  return (
    <DetailScreen title={t('Lot genealogy')} subtitle={lot.lot_number}>
      {/* Lot header card */}
      <Card style={{ borderColor: BRAND.amber, borderWidth: 2 }}>
        <View style={styles.heroRow}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Mono size={10.5} color={BRAND.amber} weight="700" letterSpacing={0.8}>
              {t('SELECTED LOT').toUpperCase()}
            </Mono>
            <Mono size={15} color={palette.text} weight="700" style={{ marginTop: 6 }}>
              {lot.lot_number}
            </Mono>
            <Text style={[styles.matName, { color: palette.textMuted }]}>
              {lot.material?.name ?? `Material #${lot.material_id}`}
              {' · '}
              {lot.quantity_available} {lot.unit_of_measure}
            </Text>
          </View>
          <View
            style={[
              styles.statePill,
              { backgroundColor: `${statusColor}22` },
            ]}>
            <View style={[styles.stateDot, { backgroundColor: statusColor }]} />
            <Mono size={10} color={statusColor} weight="700" letterSpacing={0.5}>
              {String(lot.status ?? 'available').toUpperCase()}
            </Mono>
          </View>
        </View>

        {canConsume && Number(lot.quantity_available) > 0 ? (
          <Pressable
            onPress={() => setConsumeOpen(true)}
            style={({ pressed }) => [
              styles.consumeBtn,
              { borderColor: BRAND.amber, opacity: pressed ? 0.7 : 1 },
            ]}>
            <FontAwesome name="minus-circle" size={14} color={BRAND.amber} />
            <Mono size={11} color={BRAND.amber} weight="700" letterSpacing={0.5}>
              {t('CONSUME FROM THIS LOT')}
            </Mono>
          </Pressable>
        ) : null}
      </Card>

      <ConsumeLotModal
        visible={consumeOpen}
        lot={lot}
        onClose={() => setConsumeOpen(false)}
      />

      {/* Backward — sources */}
      <Section
        icon="reply"
        label={t('BACKWARD · SOURCE LOTS').toUpperCase()}
        palette={palette}>
        {bwdQ.isLoading ? (
          <Mono size={11} color={palette.textFaint} style={{ padding: 8 }}>
            {t('Loading…').toUpperCase()}
          </Mono>
        ) : bwdQ.isError ? (
          <Mono size={11} color={palette.danger} style={{ padding: 8 }}>
            {t('Failed to load').toUpperCase()}
          </Mono>
        ) : (bwdQ.data?.upstream_consumptions ?? []).length === 0 &&
          !bwdQ.data?.inspection &&
          !bwdQ.data?.supplier_lot_no ? (
          <Mono size={11} color={palette.textFaint} style={{ padding: 8 }}>
            {t('No upstream sources recorded').toUpperCase()}
          </Mono>
        ) : (
          <View style={{ gap: 8 }}>
            {bwdQ.data?.supplier_lot_no ? (
              <View style={[styles.bwdRow, { backgroundColor: palette.surfaceAlt }]}>
                <FontAwesome name="archive" size={16} color={palette.textMuted} />
                <View style={{ flex: 1 }}>
                  <Mono size={11} color={palette.text} weight="600">
                    {bwdQ.data.supplier_lot_no}
                  </Mono>
                  <Mono
                    size={9.5}
                    color={palette.textFaint}
                    letterSpacing={0.3}
                    style={{ marginTop: 2 }}>
                    {(bwdQ.data.supplier_reference ?? t('SUPPLIER LOT')).toUpperCase()}
                  </Mono>
                </View>
              </View>
            ) : null}
            {(bwdQ.data?.upstream_consumptions ?? []).map((c) => (
              <ConsumptionRow key={c.id} consumption={c} direction="back" palette={palette} />
            ))}
          </View>
        )}
      </Section>

      {/* Forward — consumed into */}
      <Section
        icon="share"
        label={t('FORWARD · CONSUMED INTO').toUpperCase()}
        palette={palette}>
        {fwdQ.isLoading ? (
          <Mono size={11} color={palette.textFaint} style={{ padding: 8 }}>
            {t('Loading…').toUpperCase()}
          </Mono>
        ) : (fwdQ.data?.consumptions ?? []).length === 0 ? (
          <Mono size={11} color={palette.textFaint} style={{ padding: 8 }}>
            {t('Not yet consumed').toUpperCase()}
          </Mono>
        ) : (
          <View style={{ gap: 6 }}>
            {(fwdQ.data?.consumptions ?? []).map((c) => (
              <ConsumptionRow key={c.id} consumption={c} direction="fwd" palette={palette} />
            ))}
          </View>
        )}
      </Section>

      <View style={[styles.helpBlock, { backgroundColor: palette.surfaceAlt }]}>
        <Mono size={11} color={palette.textMuted} letterSpacing={0.3}>
          ⓘ {t('Genealogy traverses material_lots → consumptions → batch_steps recursively.')}
        </Mono>
      </View>
    </DetailScreen>
  );
}

function Section({
  icon,
  label,
  palette,
  children,
}: {
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  label: string;
  palette: typeof Colors.light;
  children: React.ReactNode;
}) {
  return (
    <View style={{ gap: 8 }}>
      <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
        <FontAwesome name={icon} size={13} color={palette.textMuted} />
        <Mono size={11} color={palette.textFaint} letterSpacing={0.8}>
          {label}
        </Mono>
      </View>
      <Card style={{ padding: 12, gap: 6 }}>{children}</Card>
    </View>
  );
}

function ConsumptionRow({
  consumption,
  direction,
  palette,
}: {
  consumption: BatchStepLotConsumption;
  direction: 'back' | 'fwd';
  palette: typeof Colors.light;
}) {
  const wo = consumption.batchStep?.batch?.work_order;
  const woNo = wo?.order_no ?? '—';
  const batchNo = consumption.batchStep?.batch?.batch_no ?? '';
  const timestamp = consumption.recorded_at
    ? safeRelative(consumption.recorded_at)
    : '';
  return (
    <View style={[styles.consumRow, { backgroundColor: palette.surfaceAlt }]}>
      <View
        style={[
          styles.consumIcon,
          { backgroundColor: `${BRAND.amber}22` },
        ]}>
        <Mono size={11} color={BRAND.amber} weight="700">
          {direction === 'fwd' ? 'WO' : 'SRC'}
        </Mono>
      </View>
      <View style={{ flex: 1, minWidth: 0 }}>
        <Mono size={11} color={palette.text} weight="600">
          {woNo}
          {batchNo ? ` · ${batchNo}` : ''}
        </Mono>
        <Mono
          size={10}
          color={palette.textFaint}
          letterSpacing={0.3}
          style={{ marginTop: 2 }}>
          {(wo?.product_type?.name ?? '—').toUpperCase()}
        </Mono>
      </View>
      <View style={{ alignItems: 'flex-end' }}>
        <Mono size={12} color={palette.text} weight="700">
          {consumption.quantity} {consumption.unit_of_measure ?? ''}
        </Mono>
        {timestamp ? (
          <Mono size={9} color={palette.textFaint} letterSpacing={0.3} style={{ marginTop: 2 }}>
            {timestamp}
          </Mono>
        ) : null}
      </View>
    </View>
  );
}

function safeRelative(iso: string) {
  try {
    const ms = Date.now() - new Date(iso).getTime();
    const min = Math.floor(ms / 60000);
    if (min < 60) return `${min}m`;
    const h = Math.floor(min / 60);
    if (h < 24) return `${h}h`;
    const d = Math.floor(h / 24);
    if (d === 1) return 'Yesterday';
    if (d < 7) return `${d} days ago`;
    return format(parseISO(iso), 'yyyy-MM-dd');
  } catch {
    return '';
  }
}

const styles = StyleSheet.create({
  heroRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  consumeBtn: {
    marginTop: 14,
    height: 40,
    borderRadius: 8,
    borderWidth: 1,
    borderStyle: 'dashed',
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 8,
  },
  matName: { fontSize: 12.5, marginTop: 4 },
  statePill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 4,
    paddingHorizontal: 10,
    borderRadius: 999,
  },
  stateDot: { width: 6, height: 6, borderRadius: 3 },
  bwdRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 10,
    borderRadius: 8,
  },
  consumRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    padding: 10,
    borderRadius: 8,
  },
  consumIcon: {
    width: 32,
    height: 32,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
  helpBlock: { padding: 12, borderRadius: 10 },
});
