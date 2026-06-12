import { useRouter } from 'expo-router';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Alert, View } from 'react-native';

import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { DetailScreen } from '@/components/ui/Detail';
import { Field } from '@/components/ui/Field';
import { FormSubmitBar } from '@/components/ui/FormSubmitBar';
import { Mono } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useLines } from '@/hooks/queries/useUsers';
import { useProductTypes } from '@/hooks/queries/useProductTypes';
import { useCreateWorkOrder } from '@/hooks/queries/useWorkOrders';

/**
 * New work order form — mirrors the Laravel admin create flow:
 *  order_no (required, unique), line_id?, product_type_id?, planned_qty (>0),
 *  priority?, due_date?, description?
 *
 * Sits at the root level so the tablet sidebar hides during creation (matches
 * the lift-to-root convention for every other add/edit modal). After submit
 * we route back to the orders list, which invalidates the cache.
 */
export function NewWorkOrderScreen() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const linesQ = useLines();
  const productTypesQ = useProductTypes();
  const create = useCreateWorkOrder();

  const [orderNo, setOrderNo] = useState('');
  const [lineId, setLineId] = useState<number | null>(null);
  const [productTypeId, setProductTypeId] = useState<number | null>(null);
  const [plannedQty, setPlannedQty] = useState('');
  const [priority, setPriority] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [description, setDescription] = useState('');

  const valid =
    orderNo.trim().length > 0 &&
    Number.isFinite(Number(plannedQty)) &&
    Number(plannedQty) > 0;

  const submit = () => {
    const qty = Number(plannedQty);
    create.mutate(
      {
        order_no: orderNo.trim(),
        line_id: lineId ?? undefined,
        product_type_id: productTypeId ?? undefined,
        planned_qty: qty,
        priority: priority.trim() ? Number(priority) : undefined,
        due_date: dueDate.trim() || undefined,
        description: description.trim() || undefined,
      },
      {
        onSuccess: () => router.back(),
        onError: (e: Error) => Alert.alert(t('Could not create'), e.message),
      },
    );
  };

  return (
    <DetailScreen
      title={t('New work order')}
      subtitle={t('Orders · create')}>
      <Field
        label={t('Order number')}
        required
        value={orderNo}
        onChangeText={setOrderNo}
        placeholder="WO-XXX-NNN"
        autoCapitalize="characters"
        autoCorrect={false}
        mono
      />

      <Field
        label={t('Planned quantity')}
        required
        value={plannedQty}
        onChangeText={setPlannedQty}
        placeholder="0"
        keyboardType="numeric"
        mono
      />

      <View style={{ gap: 8 }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.7} weight="700">
          {t('LINE').toUpperCase()}
        </Mono>
        <ChipRow>
          <SelectionChip
            label={t('None')}
            active={lineId === null}
            onPress={() => setLineId(null)}
          />
          {(linesQ.data ?? []).map((l) => (
            <SelectionChip
              key={l.id}
              label={l.name}
              active={l.id === lineId}
              onPress={() => setLineId(l.id)}
            />
          ))}
        </ChipRow>
      </View>

      <View style={{ gap: 8 }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.7} weight="700">
          {t('PRODUCT TYPE').toUpperCase()}
        </Mono>
        <ChipRow>
          <SelectionChip
            label={t('None')}
            active={productTypeId === null}
            onPress={() => setProductTypeId(null)}
          />
          {(productTypesQ.data ?? []).map((p) => (
            <SelectionChip
              key={p.id}
              label={p.name}
              active={p.id === productTypeId}
              onPress={() => setProductTypeId(p.id)}
            />
          ))}
        </ChipRow>
      </View>

      <View style={{ flexDirection: 'row', gap: 10 }}>
        <View style={{ flex: 1 }}>
          <Field
            label={t('Priority')}
            value={priority}
            onChangeText={setPriority}
            placeholder="0–100"
            keyboardType="numeric"
            mono
            hint={t('Higher = sooner')}
          />
        </View>
        <View style={{ flex: 1.4 }}>
          <Field
            label={t('Due date')}
            value={dueDate}
            onChangeText={setDueDate}
            placeholder="YYYY-MM-DD"
            autoCorrect={false}
            mono
          />
        </View>
      </View>

      <Field
        label={t('Description')}
        value={description}
        onChangeText={setDescription}
        placeholder={t('Optional notes')}
        multiline
        numberOfLines={3}
        style={{ minHeight: 80, textAlignVertical: 'top' }}
      />

      <FormSubmitBar
        primary="Create work order"
        onPrimary={submit}
        onSecondary={() => router.back()}
        loading={create.isPending}
        disabled={!valid}
      />
    </DetailScreen>
  );
}
