import { FontAwesome } from '@expo/vector-icons';
import { format, parseISO } from 'date-fns';
import { useRouter } from 'expo-router';
import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useMaintenanceEvents } from '@/hooks/queries/useMaintenance';
import type { MaintenanceEvent, Tool, ToolStatus } from '@/api/maintenance';

interface Props {
  tool: Tool;
}

const STATUS_CONFIG: Record<
  ToolStatus,
  { label: string; color: string; icon: React.ComponentProps<typeof FontAwesome>['name'] }
> = {
  available: { label: 'Available', color: '#1C9A55', icon: 'check-circle' },
  in_use: { label: 'In use', color: '#EA5A2B', icon: 'cog' },
  maintenance: { label: 'In maintenance', color: BRAND.amber, icon: 'wrench' },
  retired: { label: 'Retired', color: '#9B9892', icon: 'ban' },
};

/**
 * Tool detail header — status hero + spec rows + recent maintenance history.
 * Matches ScreenToolDetail from gaps.jsx. Renders above the edit form so
 * admins still get the form for changes.
 */
export function ToolProfile({ tool }: Props) {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const status = STATUS_CONFIG[tool.status] ?? STATUS_CONFIG.available;

  // Recent maintenance events for this tool — used for the history list.
  const eventsQ = useMaintenanceEvents({ tool_id: tool.id, per_page: 8 });
  const events: MaintenanceEvent[] = eventsQ.data?.data ?? [];

  return (
    <View style={{ gap: 14 }}>
      {/* Status hero — bordered with status color */}
      <Card style={{ borderColor: status.color, borderWidth: 1 }}>
        <View style={styles.heroRow}>
          <View style={{ flex: 1 }}>
            <Mono size={10.5} color={status.color} letterSpacing={0.8} weight="700">
              {t('STATUS').toUpperCase()}
            </Mono>
            <Text style={[styles.heroTitle, { color: palette.text }]}>
              {t(status.label)}
            </Text>
            {tool.next_service_at ? (
              <Mono size={11} color={palette.textMuted} style={{ marginTop: 6 }}>
                {t('NEXT SERVICE').toUpperCase()} ·{' '}
                {safeDate(tool.next_service_at, 'yyyy-MM-dd')}
              </Mono>
            ) : null}
          </View>
          <FontAwesome name={status.icon} size={36} color={status.color} />
        </View>
      </Card>

      {/* Details */}
      <View style={{ gap: 8 }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
          {t('DETAILS').toUpperCase()}
        </Mono>
        <Card style={{ padding: 0 }}>
          <KV
            label={t('Workstation type')}
            value={tool.workstation_type?.name ?? '—'}
            palette={palette}
          />
          <KV
            label={t('Next service due')}
            value={safeDate(tool.next_service_at, 'yyyy-MM-dd') || '—'}
            palette={palette}
            divider
          />
          <KV label={t('Code')} value={tool.code} palette={palette} divider />
          {tool.description ? (
            <KV
              label={t('Description')}
              value={tool.description}
              palette={palette}
              divider
            />
          ) : null}
        </Card>
      </View>

      {/* History */}
      <View style={{ gap: 8 }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
          {t('MAINTENANCE HISTORY').toUpperCase()} · {events.length}{' '}
          {t('EVENTS').toUpperCase()}
        </Mono>
        <Card style={{ padding: 0 }}>
          {events.length === 0 ? (
            <View style={{ padding: 14 }}>
              <Mono size={11} color={palette.textFaint}>
                {t('No maintenance events yet').toUpperCase()}
              </Mono>
            </View>
          ) : (
            events.map((e, i) => {
              const done = e.status === 'completed';
              const open = e.status === 'in_progress' || e.status === 'pending';
              const date =
                safeDate(e.scheduled_at ?? e.started_at ?? e.completed_at, 'yyyy-MM-dd') ||
                '—';
              return (
                <View
                  key={e.id}
                  style={[
                    styles.historyRow,
                    i < events.length - 1
                      ? {
                          borderBottomColor: palette.border,
                          borderBottomWidth: StyleSheet.hairlineWidth,
                        }
                      : null,
                  ]}>
                  <Mono
                    size={10.5}
                    color={palette.textFaint}
                    letterSpacing={0.3}
                    style={{ width: 80 }}>
                    {date}
                  </Mono>
                  <View style={{ flex: 1 }}>
                    <Text
                      style={[styles.historyTitle, { color: palette.text }]}
                      numberOfLines={2}>
                      {labelForType(e.event_type, t)} · {e.title}
                    </Text>
                  </View>
                  <View
                    style={[
                      styles.statusPill,
                      {
                        backgroundColor: done
                          ? `${palette.success}22`
                          : open
                            ? `${BRAND.amber}22`
                            : palette.surfaceAlt,
                      },
                    ]}>
                    <Mono
                      size={9}
                      color={done ? palette.success : open ? BRAND.amber : palette.textFaint}
                      weight="700"
                      letterSpacing={0.5}>
                      {done
                        ? t('DONE').toUpperCase()
                        : open
                          ? t('OPEN').toUpperCase()
                          : e.status.toUpperCase()}
                    </Mono>
                  </View>
                </View>
              );
            })
          )}
        </Card>
      </View>

      {/* Schedule action — links to maintenance event creation */}
      <View
        style={[
          styles.scheduleBtn,
          { backgroundColor: BRAND.amber },
        ]}>
        <Text
          style={styles.scheduleBtnText}
          onPress={() =>
            router.push(
              `/maintenance/events/new?tool_id=${tool.id}` as never,
            )
          }>
          {t('SCHEDULE MAINTENANCE').toUpperCase()}
        </Text>
      </View>
    </View>
  );
}

function labelForType(type: MaintenanceEvent['event_type'], t: (s: string) => string) {
  if (type === 'planned') return t('Preventive');
  if (type === 'corrective') return t('Corrective');
  if (type === 'inspection') return t('Inspection');
  return type;
}

function safeDate(iso: string | null | undefined, fmt: string): string {
  if (!iso) return '';
  try {
    return format(parseISO(iso), fmt);
  } catch {
    return '';
  }
}

function KV({
  label,
  value,
  palette,
  divider,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
  divider?: boolean;
}) {
  return (
    <View
      style={[
        styles.kvRow,
        divider
          ? { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: palette.border }
          : null,
      ]}>
      <Text style={{ color: palette.textMuted, fontSize: 12, flex: 1 }} numberOfLines={1}>
        {label}
      </Text>
      <Mono size={12} color={palette.text} weight="600" style={{ flexShrink: 1, textAlign: 'right' }}>
        {value}
      </Mono>
    </View>
  );
}

const styles = StyleSheet.create({
  heroRow: { flexDirection: 'row', alignItems: 'flex-start', gap: 12 },
  heroTitle: { fontSize: 22, fontWeight: '700', letterSpacing: -0.3, marginTop: 6 },
  kvRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  historyRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  historyTitle: { fontSize: 12, fontWeight: '500' },
  statusPill: { paddingVertical: 2, paddingHorizontal: 5, borderRadius: 3 },
  scheduleBtn: {
    height: 48,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
  scheduleBtnText: {
    color: '#1a1208',
    fontSize: 12,
    fontWeight: '700',
    letterSpacing: 0.6,
    textAlign: 'center',
    padding: 14,
  },
});
