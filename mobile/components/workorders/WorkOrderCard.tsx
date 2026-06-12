import { format, isValid, parseISO } from 'date-fns';
import { StyleSheet, Text, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import { StatusPill } from '@/components/ui/StatusPill';
import Colors, { BRAND, statusKindFor } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { isWorkOrderOverdue } from '@/lib/statusLabels';
import type { WorkOrder } from '@/types/api';

interface Props {
  workOrder: WorkOrder;
  onPress?: () => void;
}

export function WorkOrderCard({ workOrder, onPress }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const due = workOrder.due_date ? parseISO(workOrder.due_date) : null;
  const dueLabel = due && isValid(due) ? format(due, 'MMM d') : null;
  const overdue = isWorkOrderOverdue(workOrder);
  const product = workOrder.product_type?.name;
  const planned = workOrder.planned_qty ?? 0;
  const produced = workOrder.produced_qty ?? 0;
  const pct = planned > 0 ? Math.min(100, Math.round((produced / planned) * 100)) : 0;
  const kind = statusKindFor(workOrder.status);
  const barColor =
    kind === 'inProgress'
      ? palette.success
      : kind === 'paused'
      ? palette.warning
      : kind === 'blocked' || kind === 'rejected'
      ? palette.danger
      : kind === 'done'
      ? palette.textFaint
      : BRAND.amber;

  return (
    <Card onPress={onPress} leftAccent={overdue ? palette.danger : undefined}>
      <View style={styles.topRow}>
        <View style={[styles.iconBadge, { backgroundColor: palette.surfaceAlt }]}>
          <FontAwesome name="cube" size={14} color={palette.textMuted} />
        </View>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Mono size={11} color={palette.textFaint}>
            {workOrder.order_no}
          </Mono>
          {product ? (
            <Text style={[styles.product, { color: palette.text }]} numberOfLines={1}>
              {product}
            </Text>
          ) : (
            <Text style={[styles.product, { color: palette.text }]}>—</Text>
          )}
        </View>
        <StatusPill status={workOrder.status} />
      </View>

      <View style={styles.metaRow}>
        <Mono size={11} color={palette.textMuted}>
          {produced}/{planned} {t('pcs')}
        </Mono>
        {dueLabel ? (
          <Mono size={11} color={overdue ? palette.danger : palette.textMuted} weight={overdue ? '700' : undefined}>
            {t('DUE').toUpperCase()} {dueLabel.toUpperCase()}
          </Mono>
        ) : null}
        {overdue ? (
          <Mono size={11} color={palette.danger} weight="700">
            {t('Overdue').toUpperCase()}
          </Mono>
        ) : null}
        {workOrder.priority != null && workOrder.priority !== '' ? (
          <Mono size={11} color={palette.textMuted}>
            P{workOrder.priority}
          </Mono>
        ) : null}
      </View>

      {planned > 0 ? (
        <View style={[styles.barTrack, { backgroundColor: palette.surfaceAlt }]}>
          <View style={[styles.barFill, { width: `${pct}%`, backgroundColor: barColor }]} />
        </View>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create({
  topRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  iconBadge: {
    width: 38,
    height: 38,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  product: { fontSize: 15, fontWeight: '600', marginTop: 3, letterSpacing: -0.2 },
  metaRow: { flexDirection: 'row', gap: 12, marginTop: 10 },
  barTrack: { height: 4, borderRadius: 2, marginTop: 10, overflow: 'hidden' },
  barFill: { height: '100%' },
});
