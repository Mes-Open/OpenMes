import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors, { MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useBomRequirements } from '@/hooks/queries/useBom';

interface Props {
  processTemplateId?: number;
  quantity?: number;
}

export function BomRequirementsCard({ processTemplateId, quantity }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];

  const query = useBomRequirements(processTemplateId, quantity);

  if (!processTemplateId || !quantity || quantity <= 0) return null;

  const items = query.data ?? [];

  return (
    <Card style={{ gap: 12 }}>
      <SectionLabel
        right={<Mono size={11} color={palette.textFaint}>{`FOR ${quantity} UNITS`}</Mono>}>
        Material requirements
      </SectionLabel>

      {query.isLoading ? (
        <Mono size={11} color={palette.textFaint}>CALCULATING…</Mono>
      ) : query.isError ? (
        <Mono size={11} color={palette.danger}>COULD NOT LOAD BOM</Mono>
      ) : items.length === 0 ? (
        <Mono size={11} color={palette.textFaint}>NO MATERIALS DEFINED FOR THIS TEMPLATE</Mono>
      ) : (
        items.map((item, i) => {
          const isLast = i === items.length - 1;
          return (
            <View
              key={item.material_id}
              style={[
                styles.item,
                { borderBottomColor: isLast ? 'transparent' : palette.border },
              ]}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={[styles.itemName, { color: palette.text }]} numberOfLines={1}>
                  {item.material_name}
                </Text>
                <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
                  {item.material_code}
                  {item.step_number != null ? ` · STEP ${item.step_number}` : ''}
                  {item.consumed_at ? ` · ${item.consumed_at.toUpperCase()}` : ''}
                </Mono>
              </View>
              <View style={styles.qtyBlock}>
                <Text style={[styles.qty, { color: palette.text, fontFamily: MONO }]}>
                  {fmt(item.required_qty)}
                  {item.unit_of_measure ? <Text style={styles.unit}> {item.unit_of_measure}</Text> : null}
                </Text>
                {item.scrap_qty > 0 ? (
                  <Mono size={10} color={palette.textFaint}>+{fmt(item.scrap_qty)} SCRAP</Mono>
                ) : null}
              </View>
            </View>
          );
        })
      )}
    </Card>
  );
}

function fmt(n: number) {
  return Math.round(n * 100) / 100;
}

const styles = StyleSheet.create({
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 10,
    borderBottomWidth: StyleSheet.hairlineWidth,
  },
  itemName: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
  qtyBlock: { alignItems: 'flex-end' },
  qty: { fontSize: 15, fontWeight: '700', letterSpacing: 0.3 },
  unit: { fontSize: 11, fontWeight: '500' },
});
