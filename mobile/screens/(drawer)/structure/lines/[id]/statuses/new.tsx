import { useLocalSearchParams, useRouter } from 'expo-router';
import { useState } from 'react';
import { Alert, Pressable, StyleSheet, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { ActiveToggleCard } from '@/components/ui/ActiveToggleCard';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { DetailScreen } from '@/components/ui/Detail';
import { Field } from '@/components/ui/Field';
import { Mono, SectionLabel } from '@/components/ui/Mono';
import Colors from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCreateLineStatus } from '@/hooks/queries/useOrgStructure';
import type { Control } from 'react-hook-form';

const PRESETS = [
  { name: 'Slate', value: '#64748b' },
  { name: 'Blue', value: '#2563eb' },
  { name: 'Green', value: '#16a34a' },
  { name: 'Amber', value: '#d97706' },
  { name: 'Red', value: '#D6442F' },
  { name: 'Purple', value: '#7c3aed' },
];

export function NewLineStatusScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const lineId = Number(id);
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const [name, setName] = useState('');
  const [color, setColor] = useState(PRESETS[0].value);
  const [isDefault, setIsDefault] = useState(false);
  const [isDoneStatus, setIsDoneStatus] = useState(false);

  const m = useCreateLineStatus();

  const valid = name.trim();

  return (
    <DetailScreen>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Status</SectionLabel>
        <Field label="Name" value={name} onChangeText={setName} placeholder="e.g. In setup, On hold" />
        <View style={{ gap: 8 }}>
          <Mono size={10} color={palette.textFaint} letterSpacing={0.8}>{t('COLOR').toUpperCase()}</Mono>
          <View style={styles.colorRow}>
            {PRESETS.map((p) => {
              const active = p.value === color;
              return (
                <Pressable
                  key={p.value}
                  onPress={() => setColor(p.value)}
                  style={[
                    styles.swatch,
                    {
                      backgroundColor: p.value,
                      borderColor: active ? palette.text : 'transparent',
                    },
                  ]}>
                  {active ? <FontAwesome name="check" size={12} color="#fff" /> : null}
                </Pressable>
              );
            })}
          </View>
          <Field
            label="Custom color (hex)"
            value={color}
            onChangeText={setColor}
            autoCapitalize="characters"
          />
        </View>
      </Card>

      <SimpleSwitchCard
        title="Default status"
        description="NEW WORK ORDERS START HERE"
        value={isDefault}
        onChange={setIsDefault}
      />
      <SimpleSwitchCard
        title="Done status"
        description="MARKS THE ORDER AS EFFECTIVELY COMPLETE"
        value={isDoneStatus}
        onChange={setIsDoneStatus}
      />

      <Button
        title="Create status"
        size="lg"
        loading={m.isPending}
        disabled={!valid}
        onPress={() =>
          m.mutate(
            {
              lineId,
              payload: { name: name.trim(), color, is_default: isDefault, is_done_status: isDoneStatus },
            },
            {
              onSuccess: () => router.back(),
              onError: (e: Error) => Alert.alert('Could not create', e.message),
            },
          )
        }
      />
    </DetailScreen>
  );
}

import { Text } from 'react-native';
import { Switch } from '@/components/ui/Switch';

function SimpleSwitchCard({
  title,
  description,
  value,
  onChange,
}: {
  title: string;
  description: string;
  value: boolean;
  onChange: (v: boolean) => void;
}) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();
  return (
    <Card>
      <View style={styles.toggleRow}>
        <View style={{ flex: 1 }}>
          <Text style={[styles.toggleTitle, { color: palette.text }]}>{t(title)}</Text>
          <Mono size={11} color={palette.textFaint} style={{ marginTop: 3 }}>
            {t(description).toUpperCase()}
          </Mono>
        </View>
        <Switch value={value} onValueChange={onChange} />
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  colorRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 10 },
  swatch: {
    width: 40,
    height: 40,
    borderRadius: 10,
    borderWidth: 3,
    alignItems: 'center',
    justifyContent: 'center',
  },
  toggleRow: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  toggleTitle: { fontSize: 14, fontWeight: '600', letterSpacing: -0.2 },
});
