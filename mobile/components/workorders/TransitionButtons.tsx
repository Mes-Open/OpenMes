import { Alert, StyleSheet, View } from 'react-native';

import { Button } from '@/components/ui/Button';
import { useTransitionWorkOrder } from '@/hooks/mutations/workOrders';
import { isSupervisorOrAdmin, useAuthStore } from '@/stores/authStore';
import type { WorkOrderStatus } from '@/types/api';
import type { WorkOrderTransition } from '@/api/workOrders';

interface Props {
  workOrderId: number;
  status: WorkOrderStatus;
}

interface ActionDef {
  transition: WorkOrderTransition;
  label: string;
  variant?: 'primary' | 'secondary' | 'danger' | 'success';
  confirm?: { title: string; message: string };
}

function actionsFor(status: WorkOrderStatus): ActionDef[] {
  switch (status) {
    case 'PENDING':
      return [
        { transition: 'accept', label: 'Accept', variant: 'success' },
        { transition: 'reject', label: 'Reject', variant: 'danger', confirm: { title: 'Reject work order?', message: 'This sets the order to REJECTED.' } },
        { transition: 'cancel', label: 'Cancel', variant: 'secondary', confirm: { title: 'Cancel work order?', message: 'This sets the order to CANCELLED.' } },
      ];
    case 'ACCEPTED':
      return [
        { transition: 'reject', label: 'Reject', variant: 'danger', confirm: { title: 'Reject work order?', message: 'This sets the order to REJECTED.' } },
        { transition: 'cancel', label: 'Cancel', variant: 'secondary', confirm: { title: 'Cancel work order?', message: 'This sets the order to CANCELLED.' } },
      ];
    case 'IN_PROGRESS':
      return [
        { transition: 'pause', label: 'Pause', variant: 'secondary' },
        { transition: 'complete', label: 'Complete', variant: 'success', confirm: { title: 'Mark as DONE?', message: 'This will mark the work order as completed.' } },
        { transition: 'cancel', label: 'Cancel', variant: 'danger', confirm: { title: 'Cancel work order?', message: 'This sets the order to CANCELLED.' } },
      ];
    case 'PAUSED':
      return [
        { transition: 'resume', label: 'Resume', variant: 'success' },
        { transition: 'cancel', label: 'Cancel', variant: 'danger', confirm: { title: 'Cancel work order?', message: 'This sets the order to CANCELLED.' } },
      ];
    case 'BLOCKED':
      return [
        { transition: 'resume', label: 'Resume', variant: 'success' },
        { transition: 'cancel', label: 'Cancel', variant: 'danger', confirm: { title: 'Cancel work order?', message: 'This sets the order to CANCELLED.' } },
      ];
    case 'DONE':
    case 'REJECTED':
    case 'CANCELLED':
      return [
        { transition: 'reopen', label: 'Reopen', variant: 'secondary', confirm: { title: 'Reopen work order?', message: 'This sets the order back to IN_PROGRESS.' } },
      ];
    default:
      return [];
  }
}

export function TransitionButtons({ workOrderId, status }: Props) {
  const user = useAuthStore((s) => s.user);
  const canTransition = isSupervisorOrAdmin(user);
  const mutation = useTransitionWorkOrder();

  if (!canTransition) return null;

  const actions = actionsFor(status);
  if (actions.length === 0) return null;

  const run = (action: ActionDef) => {
    const dispatch = () =>
      mutation.mutate(
        { id: workOrderId, transition: action.transition },
        { onError: (e: Error) => Alert.alert('Failed', e.message) },
      );

    if (action.confirm) {
      Alert.alert(action.confirm.title, action.confirm.message, [
        { text: 'Cancel', style: 'cancel' },
        { text: action.label, style: action.variant === 'danger' ? 'destructive' : 'default', onPress: dispatch },
      ]);
    } else {
      dispatch();
    }
  };

  return (
    <View style={styles.row}>
      {actions.map((a) => (
        <Button
          key={a.transition}
          title={a.label}
          variant={a.variant ?? 'primary'}
          onPress={() => run(a)}
          loading={mutation.isPending}
          style={{ flex: 1, minWidth: 100 }}
        />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
});
