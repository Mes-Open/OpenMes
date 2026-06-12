import { FontAwesome } from '@expo/vector-icons';
import { useEffect, useState } from 'react';
import {
  Alert,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';

import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import {
  useActiveDowntime,
  useDowntimeReasons,
  useStartDowntime,
  useStopDowntime,
} from '@/hooks/queries/useDowntime';
import type { DowntimeReason } from '@/api/downtime';

interface Props {
  lineId: number;
  workstationId?: number | null;
  /** Force dark mode regardless of color scheme — operator screens are always dark. */
  dark?: boolean;
}

export function DowntimeBanner({ lineId, workstationId, dark = true }: Props) {
  const palette = dark ? Colors.dark : Colors.light;
  const activeQ = useActiveDowntime(lineId);
  const reasonsQ = useDowntimeReasons();
  const startMutation = useStartDowntime();
  const stopMutation = useStopDowntime();

  const [pickerOpen, setPickerOpen] = useState(false);
  const [reasonId, setReasonId] = useState<number | null>(null);
  const [notes, setNotes] = useState('');
  const [tick, setTick] = useState(0);

  const active = activeQ.data ?? null;

  // Re-render every 10s to update the elapsed timer in the active state.
  useEffect(() => {
    if (!active) return;
    const id = setInterval(() => setTick((t) => t + 1), 10_000);
    return () => clearInterval(id);
  }, [active]);
  void tick;

  const onStart = () => {
    if (!reasonId) return;
    startMutation.mutate(
      {
        line_id: lineId,
        workstation_id: workstationId ?? undefined,
        downtime_reason_id: reasonId,
        notes: notes || undefined,
      },
      {
        onSuccess: () => {
          setPickerOpen(false);
          setReasonId(null);
          setNotes('');
        },
        onError: (e: Error) => Alert.alert('Could not start', e.message),
      },
    );
  };

  const onStop = () => {
    if (!active) return;
    Alert.alert('Stop downtime?', `Reason: ${active.reason?.name ?? 'unknown'}`, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Stop',
        onPress: () =>
          stopMutation.mutate(active.id, {
            onError: (e: Error) => Alert.alert('Failed', e.message),
          }),
      },
    ]);
  };

  if (active) {
    const elapsed = elapsedFrom(active.started_at);
    return (
      <Pressable onPress={onStop} style={({ pressed }) => ({ opacity: pressed ? 0.92 : 1 })}>
        <View style={styles.activeBanner}>
          <View style={styles.liveCol}>
            <View style={styles.liveDot} />
            <Mono size={9} color="#fff" weight="700" letterSpacing={0.5}>LIVE</Mono>
          </View>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Mono size={9.5} color="rgba(255,255,255,0.85)" letterSpacing={0.7}>
              DOWNTIME · {(active.reason?.name ?? '—').toUpperCase()}
            </Mono>
            <Text style={styles.elapsedText}>{elapsed}</Text>
            <Mono size={10.5} color="rgba(255,255,255,0.85)" style={{ marginTop: 4 }}>
              STARTED {formatTime(active.started_at).toUpperCase()}
              {active.reported_by_user?.username
                ? ` · ${active.reported_by_user.username.toUpperCase()}`
                : ''}
            </Mono>
            {active.notes ? (
              <Text style={styles.notesText} numberOfLines={2}>
                "{active.notes}"
              </Text>
            ) : null}
          </View>
          <View style={styles.stopBtn}>
            <Mono size={11} color={palette.danger} weight="700" letterSpacing={0.5}>STOP</Mono>
          </View>
        </View>
      </Pressable>
    );
  }

  return (
    <>
      <Pressable
        onPress={() => setPickerOpen(true)}
        style={({ pressed }) => [styles.idleBanner, { opacity: pressed ? 0.9 : 1 }]}>
        <View style={styles.idleIcon}>
          <FontAwesome name="exclamation-triangle" size={18} color={palette.danger} />
        </View>
        <View style={{ flex: 1 }}>
          <Text style={[styles.idleTitle, { color: palette.danger }]}>Line stopped?</Text>
          <Mono size={11} color={dark ? '#6F6C66' : '#6F6C66'} style={{ marginTop: 3 }}>
            Report downtime to track availability
          </Mono>
        </View>
        <View style={[styles.idleCta, { borderColor: palette.danger }]}>
          <Mono size={11} color={palette.danger} weight="700" letterSpacing={0.5}>
            REPORT
          </Mono>
        </View>
      </Pressable>

      <ReasonPickerModal
        open={pickerOpen}
        reasons={reasonsQ.data ?? []}
        reasonId={reasonId}
        notes={notes}
        loading={startMutation.isPending}
        onClose={() => {
          setPickerOpen(false);
          setReasonId(null);
          setNotes('');
        }}
        onSelect={setReasonId}
        onNotes={setNotes}
        onStart={onStart}
      />
    </>
  );
}

function ReasonPickerModal({
  open,
  reasons,
  reasonId,
  notes,
  loading,
  onClose,
  onSelect,
  onNotes,
  onStart,
}: {
  open: boolean;
  reasons: DowntimeReason[];
  reasonId: number | null;
  notes: string;
  loading: boolean;
  onClose: () => void;
  onSelect: (id: number) => void;
  onNotes: (v: string) => void;
  onStart: () => void;
}) {
  const [kindFilter, setKindFilter] = useState<'all' | DowntimeReason['kind']>(
    'all',
  );
  const scopedReasons = kindFilter === 'all'
    ? reasons
    : reasons.filter((r) => r.kind === kindFilter);
  return (
    <Modal animationType="slide" transparent visible={open} onRequestClose={onClose}>
      <View style={styles.modalBackdrop}>
        <View style={styles.modalSheet}>
          <View style={styles.modalHandle} />
          <Text style={styles.modalTitle}>Why is the line stopped?</Text>
          <Mono size={11} color="#6F6C66" style={{ marginTop: 4 }}>
            Pick a kind, then a specific reason
          </Mono>

          {/* Kind picker — three category cards. Tap to filter the reason list
              below. Tapping the active kind clears back to "all". */}
          <View style={styles.kindRow}>
            {([
              { id: 'planned', label: 'Planned', sub: 'scheduled work', color: '#EA5A2B' },
              { id: 'changeover', label: 'Changeover', sub: 'product switch', color: '#EA5A2B' },
              { id: 'unplanned', label: 'Breakdown', sub: 'unplanned', color: '#D6442F' },
            ] as const).map((k) => {
              const on = kindFilter === k.id;
              return (
                <Pressable
                  key={k.id}
                  onPress={() => setKindFilter(on ? 'all' : k.id)}
                  style={[
                    styles.kindCard,
                    {
                      backgroundColor: on ? '#F1EFEA' : '#F6F5F1',
                      borderColor: on ? k.color : '#E6E4DE',
                    },
                  ]}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
                    <View
                      style={{
                        width: 8,
                        height: 8,
                        borderRadius: 4,
                        backgroundColor: k.color,
                      }}
                    />
                    <Text style={styles.kindLabel}>{k.label}</Text>
                  </View>
                  <Mono size={9.5} color="#9B9892" letterSpacing={0.4} style={{ marginTop: 4 }}>
                    {k.sub.toUpperCase()}
                  </Mono>
                </Pressable>
              );
            })}
          </View>

          <ScrollView style={styles.modalList} contentContainerStyle={{ gap: 6 }}>
            {scopedReasons.map((r) => {
              const sel = r.id === reasonId;
              return (
                <Pressable
                  key={r.id}
                  onPress={() => onSelect(r.id)}
                  style={[
                    styles.reasonRow,
                    {
                      backgroundColor: sel ? '#332b1c' : '#F6F5F1',
                      borderColor: sel ? BRAND.amber : '#E6E4DE',
                    },
                  ]}>
                  <View
                    style={[
                      styles.reasonRadio,
                      {
                        borderColor: sel ? BRAND.amber : '#9B9892',
                        backgroundColor: sel ? BRAND.amber : 'transparent',
                      },
                    ]}
                  />
                  <Text
                    style={[
                      styles.reasonName,
                      { color: '#1A1917', fontWeight: sel ? '600' : '400' },
                    ]}
                    numberOfLines={1}>
                    {r.name}
                  </Text>
                  {r.kind === 'planned' ? (
                    <View style={styles.plannedTag}>
                      <Mono size={9} color="#EA5A2B" weight="700" letterSpacing={0.5}>
                        PLANNED
                      </Mono>
                    </View>
                  ) : r.kind === 'changeover' ? (
                    <View style={[styles.plannedTag, { backgroundColor: '#FAF0DD' }]}>
                      <Mono size={9} color="#8a5a0e" weight="700" letterSpacing={0.5}>
                        CHANGEOVER
                      </Mono>
                    </View>
                  ) : null}
                </Pressable>
              );
            })}
          </ScrollView>

          <TextInput
            value={notes}
            onChangeText={onNotes}
            placeholder="Optional notes — what's blocking?"
            placeholderTextColor="#9B9892"
            multiline
            style={styles.notesInput}
          />

          <View style={{ flexDirection: 'row', gap: 8, marginTop: 12 }}>
            <Pressable
              onPress={onClose}
              style={({ pressed }) => [
                styles.cancelBtn,
                { opacity: pressed ? 0.85 : 1 },
              ]}>
              <Mono size={12} color="#1A1917" weight="700" letterSpacing={0.5}>CANCEL</Mono>
            </Pressable>
            <Pressable
              onPress={onStart}
              disabled={!reasonId || loading}
              style={({ pressed }) => [
                styles.startBtn,
                { opacity: !reasonId || loading ? 0.5 : pressed ? 0.9 : 1 },
              ]}>
              <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.6}>
                {loading ? 'STARTING…' : 'START DOWNTIME TIMER'}
              </Mono>
            </Pressable>
          </View>
        </View>
      </View>
    </Modal>
  );
}

function elapsedFrom(iso: string): string {
  try {
    const startMs = new Date(iso).getTime();
    const totalMin = Math.max(0, Math.floor((Date.now() - startMs) / 60000));
    const h = Math.floor(totalMin / 60);
    const m = totalMin % 60;
    if (h > 0) return `${h}h ${String(m).padStart(2, '0')}m`;
    return `${m} min`;
  } catch {
    return '—';
  }
}

function formatTime(iso: string): string {
  try {
    const d = new Date(iso);
    return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  } catch {
    return '—';
  }
}

const styles = StyleSheet.create({
  idleBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1.5,
    borderStyle: 'dashed',
    borderColor: '#D6442F',
    backgroundColor: 'rgba(239,68,68,0.08)',
  },
  idleIcon: {
    width: 40,
    height: 40,
    borderRadius: 10,
    backgroundColor: '#FBEAE6',
    alignItems: 'center',
    justifyContent: 'center',
  },
  idleTitle: { fontSize: 13, fontWeight: '600' },
  idleCta: {
    paddingVertical: 8,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },
  activeBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    padding: 14,
    borderRadius: 12,
    backgroundColor: '#D6442F',
    boxShadow: '0px 6px 14px rgba(220, 38, 38, 0.3)',
  },
  liveCol: { alignItems: 'center', gap: 6 },
  liveDot: {
    width: 12,
    height: 12,
    borderRadius: 6,
    backgroundColor: '#fff',
  },
  elapsedText: {
    color: '#fff',
    fontFamily: MONO,
    fontSize: 28,
    fontWeight: '700',
    letterSpacing: -0.5,
    lineHeight: 30,
    marginTop: 2,
  },
  notesText: { color: 'rgba(255,255,255,0.85)', fontSize: 11, fontStyle: 'italic', marginTop: 4 },
  stopBtn: {
    paddingVertical: 10,
    paddingHorizontal: 14,
    borderRadius: 8,
    backgroundColor: '#fff',
  },
  modalBackdrop: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(0,0,0,0.5)',
  },
  modalSheet: {
    backgroundColor: '#FFFFFF',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    padding: 16,
    paddingBottom: 28,
    borderWidth: 1,
    borderColor: '#E6E4DE',
    maxHeight: '88%',
  },
  modalHandle: {
    alignSelf: 'center',
    width: 36,
    height: 4,
    borderRadius: 2,
    backgroundColor: '#C4C0B8',
    marginBottom: 14,
  },
  modalTitle: { color: '#fff', fontSize: 16, fontWeight: '600' },
  modalList: { marginTop: 14, maxHeight: 320 },
  reasonRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },
  reasonRadio: { width: 14, height: 14, borderRadius: 7, borderWidth: 2 },
  reasonName: { flex: 1, fontSize: 13 },
  plannedTag: { paddingVertical: 1, paddingHorizontal: 5, borderRadius: 3, backgroundColor: '#0e1a3a' },
  notesInput: {
    marginTop: 12,
    padding: 12,
    borderRadius: 8,
    backgroundColor: '#F6F5F1',
    borderWidth: 1,
    borderColor: '#E6E4DE',
    color: '#1A1917',
    fontSize: 12,
    minHeight: 60,
    textAlignVertical: 'top',
  },
  cancelBtn: {
    flex: 1,
    height: 48,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#E6E4DE',
    backgroundColor: '#F1EFEA',
    alignItems: 'center',
    justifyContent: 'center',
  },
  startBtn: {
    flex: 2,
    height: 48,
    borderRadius: 12,
    backgroundColor: BRAND.amber,
    alignItems: 'center',
    justifyContent: 'center',
  },
  kindRow: { flexDirection: 'row', gap: 6, marginTop: 12 },
  kindCard: {
    flex: 1,
    padding: 10,
    borderRadius: 10,
    borderWidth: 2,
  },
  kindLabel: { color: '#1A1917', fontSize: 12, fontWeight: '700' },
});
