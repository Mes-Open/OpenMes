import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';
import { StyleSheet, Text, View } from 'react-native';

import { Card } from '@/components/ui/Card';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import type { Worker } from '@/api/hr';

interface Props {
  worker: Worker;
}

/**
 * Worker profile header — identity card + assignment KV list + skill chips +
 * this-week stats. Matches design ScreenWorkerDetail from gaps.jsx. Renders
 * above the existing edit form so admins still get the form for changes.
 *
 * Week stats are placeholders until the backend ships an aggregate endpoint
 * (TODO: /api/v1/workers/{id}/week-stats).
 */
export function WorkerProfile({ worker }: Props) {
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const initials = worker.name
    .split(/\s+/)
    .map((p) => p[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase();

  // Count skills past their expiry — surfaced in the section header so admins
  // see a tally even when the chip row is long.
  const expiredCount = (worker.skills ?? []).filter(
    (s) => certExpiryState(s.pivot?.expires_at) === 'expired',
  ).length;

  return (
    <View style={{ gap: 14 }}>
      {/* Identity */}
      <Card>
        <View style={styles.identity}>
          <View style={[styles.avatar, { backgroundColor: BRAND.amber }]}>
            <Mono size={22} color="#1a1208" weight="700">{initials}</Mono>
          </View>
          <View style={{ flex: 1 }}>
            <Text style={[styles.name, { color: palette.text }]}>{worker.name}</Text>
            <Mono size={11} color={palette.textFaint} letterSpacing={0.4} style={{ marginTop: 4 }}>
              {worker.code.toUpperCase()}
            </Mono>
            <View
              style={[
                styles.shiftPill,
                {
                  backgroundColor: worker.is_active ? `${palette.success}22` : palette.surfaceAlt,
                },
              ]}>
              <View
                style={[
                  styles.shiftDot,
                  { backgroundColor: worker.is_active ? palette.success : palette.textFaint },
                ]}
              />
              <Mono
                size={10}
                color={worker.is_active ? palette.success : palette.textFaint}
                weight="700"
                letterSpacing={0.5}>
                {worker.is_active
                  ? `${t('ON SHIFT').toUpperCase()}${worker.workstation?.name ? ` · ${worker.workstation.name.toUpperCase()}` : ''}`
                  : t('INACTIVE').toUpperCase()}
              </Mono>
            </View>
          </View>
        </View>
      </Card>

      {/* Assignment */}
      <View style={{ gap: 8 }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
          {t('ASSIGNMENT').toUpperCase()}
        </Mono>
        <Card style={{ padding: 0 }}>
          <KV
            icon="users"
            label={t('Crew')}
            value={worker.crew?.name ?? '—'}
            palette={palette}
          />
          <KV
            icon="line-chart"
            label={t('Wage group')}
            value={worker.wage_group?.name ?? '—'}
            palette={palette}
            divider
          />
          <KV
            icon="cog"
            label={t('Workstation')}
            value={worker.workstation?.name ?? '—'}
            palette={palette}
            divider
          />
          <KV
            icon="envelope"
            label={t('Email')}
            value={worker.email ?? '—'}
            palette={palette}
            divider
          />
          <KV
            icon="phone"
            label={t('Phone')}
            value={worker.phone ?? '—'}
            palette={palette}
            divider
          />
        </Card>
      </View>

      {/* Skills — chips carry a certification status when the pivot ships
          certified_at/expires_at (migration add_certification_to_worker_skills,
          2026-05-22). Expired skills show a red rail + EXPIRED tag; skills
          within 30 days of expiry get an amber EXPIRES SOON warning. */}
      {worker.skills && worker.skills.length > 0 ? (
        <View style={{ gap: 8 }}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('SKILLS').toUpperCase()} · {worker.skills.length}
            {expiredCount > 0
              ? ` · ${expiredCount} ${t('EXPIRED').toUpperCase()}`
              : ''}
          </Mono>
          <View style={styles.chipRow}>
            {worker.skills.map((s) => {
              const expiryState = certExpiryState(s.pivot?.expires_at);
              const accentColor =
                expiryState === 'expired'
                  ? palette.danger
                  : expiryState === 'expiring'
                    ? palette.warning
                    : null;
              return (
                <View
                  key={s.id}
                  style={[
                    styles.skillChip,
                    {
                      backgroundColor: accentColor
                        ? `${accentColor}11`
                        : palette.surface,
                      borderColor: accentColor ?? palette.border,
                    },
                  ]}>
                  <Mono size={11} color={palette.text} weight="600">
                    {s.name}
                  </Mono>
                  {s.pivot?.level ? (
                    <Mono size={9} color={palette.textFaint} letterSpacing={0.4} weight="700">
                      L{s.pivot.level}
                    </Mono>
                  ) : null}
                  {accentColor ? (
                    <View
                      style={[
                        styles.expiryTag,
                        { backgroundColor: accentColor },
                      ]}>
                      <Mono size={8.5} color="#fff" weight="700" letterSpacing={0.5}>
                        {expiryState === 'expired'
                          ? t('EXPIRED').toUpperCase()
                          : t('EXPIRES SOON').toUpperCase()}
                      </Mono>
                    </View>
                  ) : null}
                </View>
              );
            })}
          </View>
        </View>
      ) : null}

      {/* This week — placeholder until aggregate endpoint ships */}
      <View style={{ gap: 8 }}>
        <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
          {t('THIS WEEK').toUpperCase()}
        </Mono>
        <View style={styles.statsRow}>
          <StatTile label={t('HOURS').toUpperCase()} value="—" palette={palette} />
          <StatTile label={t('BATCHES').toUpperCase()} value="—" palette={palette} />
          <StatTile label={t('ISSUES').toUpperCase()} value="—" palette={palette} />
        </View>
      </View>
    </View>
  );
}

function KV({
  icon,
  label,
  value,
  palette,
  divider,
}: {
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  label: string;
  value: string;
  palette: typeof Colors.light;
  divider?: boolean;
}) {
  return (
    <View
      style={[
        styles.kvRow,
        divider ? { borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: palette.border } : null,
      ]}>
      <FontAwesome name={icon} size={14} color={palette.textFaint} />
      <Mono size={10} color={palette.textFaint} letterSpacing={0.4} style={{ flex: 1 }}>
        {label.toUpperCase()}
      </Mono>
      <Mono size={12} color={palette.text} weight="700">
        {value}
      </Mono>
    </View>
  );
}

/** Classifies a cert expiry ISO date into the three states the chip uses.
 *  Backend `CertificationExpiryCheck` artisan command runs daily and warns
 *  at 30 days — mirror that threshold here so the badge fires at the same
 *  time the admin gets the email. */
function certExpiryState(iso?: string | null): 'expired' | 'expiring' | 'ok' {
  if (!iso) return 'ok';
  try {
    const expiry = new Date(iso).getTime();
    const now = Date.now();
    if (expiry < now) return 'expired';
    if (expiry - now < 30 * 24 * 60 * 60 * 1000) return 'expiring';
    return 'ok';
  } catch {
    return 'ok';
  }
}

function StatTile({
  label,
  value,
  palette,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
}) {
  return (
    <View style={[styles.statTile, { backgroundColor: palette.surface, borderColor: palette.border }]}>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.5}>
        {label}
      </Mono>
      <Text style={[styles.statValue, { color: palette.text, fontFamily: MONO }]}>
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  identity: { flexDirection: 'row', alignItems: 'center', gap: 14 },
  avatar: {
    width: 64,
    height: 64,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
  },
  name: { fontSize: 18, fontWeight: '700', letterSpacing: -0.2 },
  shiftPill: {
    alignSelf: 'flex-start',
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 3,
    paddingHorizontal: 8,
    borderRadius: 999,
    marginTop: 8,
  },
  shiftDot: { width: 6, height: 6, borderRadius: 3 },
  kvRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 12,
    paddingHorizontal: 14,
  },
  chipRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  skillChip: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 6,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
  },
  expiryTag: { paddingVertical: 2, paddingHorizontal: 5, borderRadius: 4 },
  statsRow: { flexDirection: 'row', gap: 8 },
  statTile: { flex: 1, padding: 12, borderRadius: 10, borderWidth: 1 },
  statValue: { fontSize: 20, fontWeight: '700', letterSpacing: -0.4, marginTop: 4 },
});
