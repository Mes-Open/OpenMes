import { useState } from 'react';
import { View } from 'react-native';

import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ChipRow, SelectionChip } from '@/components/ui/SelectionChip';
import { Field } from '@/components/ui/Field';
import { SectionLabel } from '@/components/ui/Mono';
import type { Tool, ToolStatus } from '@/api/maintenance';

export interface ToolFormValues {
  code: string;
  name: string;
  description: string;
  status: ToolStatus;
  next_service_at: string;
}

interface Props {
  initial?: Partial<Tool>;
  mode: 'create' | 'edit';
  onSubmit: (values: ToolFormValues) => void;
  submitting?: boolean;
}

const STATUSES: { id: ToolStatus; label: string }[] = [
  { id: 'available', label: 'Available' },
  { id: 'in_use', label: 'In use' },
  { id: 'maintenance', label: 'Maintenance' },
  { id: 'retired', label: 'Retired' },
];

export function ToolForm({ initial, mode, onSubmit, submitting }: Props) {
  const [code, setCode] = useState(initial?.code ?? '');
  const [name, setName] = useState(initial?.name ?? '');
  const [description, setDescription] = useState(initial?.description ?? '');
  const [status, setStatus] = useState<ToolStatus>(initial?.status ?? 'available');
  const [nextService, setNextService] = useState(initial?.next_service_at?.slice(0, 10) ?? '');

  const valid = code.trim() && name.trim();

  return (
    <View style={{ gap: 14 }}>
      <Card style={{ gap: 12 }}>
        <SectionLabel>Tool</SectionLabel>
        <Field label="Code" value={code} onChangeText={setCode} autoCapitalize="characters" autoCorrect={false} />
        <Field label="Name" value={name} onChangeText={setName} />
        <Field
          label="Description"
          value={description}
          onChangeText={setDescription}
          multiline
          numberOfLines={3}
          style={{ minHeight: 80, textAlignVertical: 'top' }}
        />
        <Field
          label="Next service"
          value={nextService}
          onChangeText={setNextService}
          placeholder="YYYY-MM-DD"
          autoCapitalize="none"
          autoCorrect={false}
        />
      </Card>

      <Card style={{ gap: 12 }}>
        <SectionLabel>Status</SectionLabel>
        <ChipRow>
          {STATUSES.map((s) => (
            <SelectionChip
              key={s.id}
              label={s.label}
              active={s.id === status}
              onPress={() => setStatus(s.id)}
            />
          ))}
        </ChipRow>
      </Card>

      <Button
        title={mode === 'create' ? 'Create tool' : 'Save changes'}
        size="lg"
        loading={!!submitting}
        disabled={!valid}
        onPress={() =>
          onSubmit({
            code: code.trim(),
            name: name.trim(),
            description: description.trim(),
            status,
            next_service_at: nextService.trim(),
          })
        }
      />
    </View>
  );
}
