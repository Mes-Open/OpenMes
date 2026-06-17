import { FontAwesome } from '@expo/vector-icons';
import { useRouter } from 'expo-router';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { TabletShell } from '@/components/tablet/TabletShell';
import { Mono } from '@/components/ui/Mono';
import Colors, { BRAND, MONO } from '@/constants/Colors';
import { useColorScheme } from '@/components/useColorScheme';
import { useCrews, useSkills, useWageGroups, useWorkers } from '@/hooks/queries/useHr';
import type { Worker } from '@/api/hr';

type Section = 'workers' | 'crews' | 'skills' | 'wage-groups';

/**
 * Tablet HR Command — 3-pane: sub-nav (Workers/Crews/Skills/Wage groups) +
 * workers table + selected worker detail. Matches design TabletHRCommand.
 */
export function TabletHrCommand() {
  const router = useRouter();
  const scheme = useColorScheme() ?? 'light';
  const palette = Colors[scheme];
  const { t } = useTranslation();

  const [section, setSection] = useState<Section>('workers');
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const workersQ = useWorkers({});
  const crewsQ = useCrews(true);
  const skillsQ = useSkills();
  const wagesQ = useWageGroups(true);

  const workers: Worker[] = workersQ.data?.data ?? [];
  const crews = crewsQ.data ?? [];
  const skills = skillsQ.data ?? [];
  const wageGroups = wagesQ.data ?? [];

  const selected: Worker | null = useMemo(
    () => workers.find((w) => w.id === selectedId) ?? workers[0] ?? null,
    [workers, selectedId],
  );

  const onShiftCount = workers.filter((w) => w.is_active).length;

  return (
    <TabletShell
      eyebrow={`${t('HR COMMAND').toUpperCase()} · ${workers.length} ${t('WORKERS').toUpperCase()} · ${crews.length} ${t('CREWS').toUpperCase()}`}
      title={t('People & teams')}
      right={
        <Pressable
          onPress={() => router.push('/hr/workers/new' as never)}
          style={({ pressed }) => [
            styles.newBtn,
            { backgroundColor: BRAND.amber, opacity: pressed ? 0.9 : 1 },
          ]}>
          <FontAwesome name="plus" size={13} color="#1a1208" />
          <Mono size={12} color="#1a1208" weight="700" letterSpacing={0.5}>
            {t('New worker').toUpperCase()}
          </Mono>
        </Pressable>
      }>
      <View style={styles.grid}>
        {/* LEFT — sub-nav + on-shift kpi */}
        <View
          style={[
            styles.panel,
            styles.leftPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
            {t('SECTIONS').toUpperCase()}
          </Mono>
          <View style={{ gap: 4, marginTop: 8 }}>
            <NavTile
              label={t('Workers')}
              count={workers.length}
              icon="user"
              active={section === 'workers'}
              onPress={() => setSection('workers')}
              palette={palette}
            />
            <NavTile
              label={t('Crews')}
              count={crews.length}
              icon="users"
              active={section === 'crews'}
              onPress={() => setSection('crews')}
              palette={palette}
            />
            <NavTile
              label={t('Skills')}
              count={skills.length}
              icon="shield"
              active={section === 'skills'}
              onPress={() => setSection('skills')}
              palette={palette}
            />
            <NavTile
              label={t('Wage groups')}
              count={wageGroups.length}
              icon="money"
              active={section === 'wage-groups'}
              onPress={() => setSection('wage-groups')}
              palette={palette}
            />
          </View>

          <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8} style={{ marginTop: 14 }}>
            {t('CREWS').toUpperCase()}
          </Mono>
          <View style={{ gap: 4, marginTop: 8 }}>
            {crews.slice(0, 6).map((c) => (
              <View
                key={c.id}
                style={[styles.crewChip, { backgroundColor: palette.surfaceAlt }]}>
                <Mono size={11.5} color={palette.textMuted}>{c.name}</Mono>
              </View>
            ))}
          </View>

          <View style={{ flex: 1 }} />

          {/* On-shift KPI block */}
          <View style={[styles.kpiBlock, { backgroundColor: '#F6F5F1' }]}>
            <Mono size={9.5} color="#6F6C66" letterSpacing={0.7}>
              {t('ON-SHIFT NOW').toUpperCase()}
            </Mono>
            <View style={{ flexDirection: 'row', alignItems: 'baseline', gap: 4, marginTop: 4 }}>
              <Mono size={32} color={palette.success} weight="700" letterSpacing={-1}>
                {onShiftCount}
              </Mono>
              <Mono size={14} color="#6F6C66">/{workers.length}</Mono>
            </View>
          </View>
        </View>

        {/* CENTER — workers table */}
        <View
          style={[
            styles.panel,
            { flex: 1, backgroundColor: palette.surface, borderColor: palette.border, overflow: 'hidden' },
          ]}>
          <View style={[styles.tableHead, { borderBottomColor: palette.border }]}>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.6} style={styles.colCode}>
              {t('EMP CODE').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.6} style={styles.colName}>
              {t('Name').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.6} style={styles.colCrew}>
              {t('Crew').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.6} style={styles.colWage}>
              {t('WAGE').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.6} style={styles.colSkills}>
              {t('SKILLS').toUpperCase()}
            </Mono>
            <Mono size={10} color={palette.textFaint} letterSpacing={0.6} style={styles.colState}>
              {t('STATE').toUpperCase()}
            </Mono>
          </View>
          <ScrollView style={{ flex: 1 }}>
            {workers.map((w) => {
              const isSel = selected?.id === w.id;
              return (
                <Pressable
                  key={w.id}
                  onPress={() => setSelectedId(w.id)}
                  style={({ pressed }) => [
                    styles.tableRow,
                    {
                      backgroundColor: isSel ? '#fdf3df' : 'transparent',
                      borderLeftColor: isSel ? BRAND.amber : 'transparent',
                      borderBottomColor: palette.border,
                      opacity: !w.is_active ? 0.55 : pressed ? 0.9 : 1,
                    },
                  ]}>
                  <Mono size={11} color={palette.text} weight="600" style={styles.colCode}>
                    {w.code}
                  </Mono>
                  <View style={[styles.nameCell, styles.colName]}>
                    <View style={[styles.avatar, { backgroundColor: BRAND.amber }]}>
                      <Mono size={11} color="#1a1208" weight="700">
                        {initials(w.name)}
                      </Mono>
                    </View>
                    <Text style={{ fontSize: 13, fontWeight: '600', color: palette.text }} numberOfLines={1}>
                      {w.name}
                    </Text>
                  </View>
                  <Mono size={10.5} color={palette.textMuted} style={styles.colCrew}>
                    {w.crew?.name ?? '—'}
                  </Mono>
                  <View style={[styles.wagePill, { backgroundColor: palette.surfaceAlt }]}>
                    <Mono size={10} color={palette.text} weight="700" letterSpacing={0.4}>
                      {w.wage_group?.name?.toUpperCase() ?? '—'}
                    </Mono>
                  </View>
                  <Mono size={12} color={palette.text} weight="700" style={styles.colSkills}>
                    {w.skills?.length ?? 0}
                  </Mono>
                  <View
                    style={[
                      styles.statePill,
                      {
                        backgroundColor: w.is_active
                          ? `${palette.success}22`
                          : palette.surfaceAlt,
                      },
                    ]}>
                    <Mono
                      size={9.5}
                      color={w.is_active ? palette.success : palette.textFaint}
                      weight="700"
                      letterSpacing={0.5}>
                      {w.is_active ? t('ON').toUpperCase() : t('OFF').toUpperCase()}
                    </Mono>
                  </View>
                </Pressable>
              );
            })}
            {workers.length === 0 ? (
              <Mono size={11} color={palette.textFaint} style={{ textAlign: 'center', padding: 24 }}>
                {t('No workers').toUpperCase()}
              </Mono>
            ) : null}
          </ScrollView>
        </View>

        {/* RIGHT — worker detail */}
        <View
          style={[
            styles.panel,
            styles.rightPanel,
            { backgroundColor: palette.surface, borderColor: palette.border },
          ]}>
          {selected ? (
            <ScrollView contentContainerStyle={{ padding: 18, gap: 14 }}>
              <View>
                <Mono size={10.5} color={BRAND.amber} weight="700" letterSpacing={0.8}>
                  {t('SELECTED').toUpperCase()} · {selected.code}
                </Mono>
                <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12, marginTop: 8 }}>
                  <View style={[styles.avatarLg, { backgroundColor: BRAND.amber }]}>
                    <Mono size={20} color="#1a1208" weight="700">{initials(selected.name)}</Mono>
                  </View>
                  <View>
                    <Text style={{ fontSize: 18, fontWeight: '700', letterSpacing: -0.2, color: palette.text }}>
                      {selected.name}
                    </Text>
                    <Mono size={11} color={palette.textFaint} style={{ marginTop: 2 }}>
                      {selected.workstation?.name?.toUpperCase() ?? t('OPERATOR').toUpperCase()}
                    </Mono>
                  </View>
                </View>
              </View>

              {selected.is_active ? (
                <View style={[styles.onShiftBox, { borderColor: palette.success, backgroundColor: `${palette.success}11` }]}>
                  <View style={{ flexDirection: 'row', alignItems: 'center', gap: 8 }}>
                    <View style={[styles.dot, { backgroundColor: palette.success }]} />
                    <Mono size={10} color={palette.success} weight="700" letterSpacing={0.5}>
                      {t('ON-SHIFT').toUpperCase()}
                      {selected.workstation?.name ? ` · ${selected.workstation.name.toUpperCase()}` : ''}
                    </Mono>
                  </View>
                </View>
              ) : null}

              <View style={styles.kvGrid}>
                <KvTile
                  label={t('CREW').toUpperCase()}
                  value={selected.crew?.name ?? '—'}
                  palette={palette}
                />
                <KvTile
                  label={t('WAGE').toUpperCase()}
                  value={selected.wage_group?.name ?? '—'}
                  palette={palette}
                />
                <KvTile
                  label={t('EMAIL').toUpperCase()}
                  value={selected.email ?? '—'}
                  palette={palette}
                />
                <KvTile
                  label={t('PHONE').toUpperCase()}
                  value={selected.phone ?? '—'}
                  palette={palette}
                />
              </View>

              {selected.skills && selected.skills.length > 0 ? (
                <View>
                  <Mono size={10.5} color={palette.textFaint} letterSpacing={0.8}>
                    {t('SKILLS').toUpperCase()} · {selected.skills.length}
                  </Mono>
                  <View style={styles.skillRow}>
                    {selected.skills.map((s) => (
                      <View
                        key={s.id}
                        style={[
                          styles.skillChip,
                          { backgroundColor: palette.surfaceAlt, borderColor: palette.border },
                        ]}>
                        <Mono size={11} color={palette.text} weight="600">
                          {s.name}
                        </Mono>
                      </View>
                    ))}
                  </View>
                </View>
              ) : null}

              <View style={{ flex: 1 }} />

              <View style={{ flexDirection: 'row', gap: 8 }}>
                <Pressable
                  style={[styles.actionBtn, { backgroundColor: palette.surface, borderColor: palette.border, borderWidth: 1 }]}>
                  <Mono size={11} color={palette.text} weight="700" letterSpacing={0.5}>
                    {t('View history').toUpperCase()}
                  </Mono>
                </Pressable>
                <Pressable
                  onPress={() => router.push(`/hr/workers/${selected.id}` as never)}
                  style={[styles.actionBtn, { backgroundColor: BRAND.amber }]}>
                  <Mono size={11} color="#1a1208" weight="700" letterSpacing={0.5}>
                    {t('Edit worker').toUpperCase()}
                  </Mono>
                </Pressable>
              </View>
            </ScrollView>
          ) : (
            <Mono
              size={11}
              color={palette.textFaint}
              letterSpacing={0.6}
              style={{ textAlign: 'center', padding: 24 }}>
              {t('Select a worker to view details').toUpperCase()}
            </Mono>
          )}
        </View>
      </View>
    </TabletShell>
  );
}

function NavTile({
  label,
  count,
  icon,
  active,
  onPress,
  palette,
}: {
  label: string;
  count: number;
  icon: React.ComponentProps<typeof FontAwesome>['name'];
  active: boolean;
  onPress: () => void;
  palette: typeof Colors.light;
}) {
  return (
    <Pressable
      onPress={onPress}
      style={[
        styles.navTile,
        {
          backgroundColor: active ? '#fdf3df' : 'transparent',
          borderColor: active ? BRAND.amber : 'transparent',
        },
      ]}>
      <FontAwesome name={icon} size={16} color={active ? BRAND.amber : palette.textMuted} />
      <Text
        style={[styles.navLabel, { color: palette.text, fontWeight: active ? '700' : '500' }]}>
        {label}
      </Text>
      <Mono size={11} color={active ? BRAND.amber : palette.textFaint} weight="700">
        {count}
      </Mono>
    </Pressable>
  );
}

function KvTile({
  label,
  value,
  palette,
}: {
  label: string;
  value: string;
  palette: typeof Colors.light;
}) {
  return (
    <View style={[styles.kvTile, { backgroundColor: palette.surfaceAlt }]}>
      <Mono size={9.5} color={palette.textFaint} letterSpacing={0.6}>{label}</Mono>
      <Mono size={12} color={palette.text} weight="700" style={{ marginTop: 4 }} numberOfLines={1}>
        {value}
      </Mono>
    </View>
  );
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .map((p) => p[0])
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

const styles = StyleSheet.create({
  grid: { flex: 1, flexDirection: 'row', gap: 14, minHeight: 0 },
  panel: { borderRadius: 16, borderWidth: 1 },
  leftPanel: { width: 220, padding: 14, gap: 14 },
  rightPanel: { width: 400 },

  newBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    paddingVertical: 8,
    paddingHorizontal: 14,
    borderRadius: 10,
  },

  navTile: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    paddingVertical: 10,
    paddingHorizontal: 12,
    borderRadius: 8,
    borderWidth: 1,
  },
  navLabel: { flex: 1, fontSize: 13 },
  crewChip: { padding: 8, borderRadius: 6, paddingHorizontal: 10 },
  kpiBlock: { padding: 12, borderRadius: 10 },

  tableHead: {
    flexDirection: 'row',
    paddingVertical: 10,
    paddingHorizontal: 16,
    borderBottomWidth: StyleSheet.hairlineWidth,
    gap: 12,
    alignItems: 'center',
  },
  tableRow: {
    flexDirection: 'row',
    paddingVertical: 12,
    paddingHorizontal: 16,
    gap: 12,
    alignItems: 'center',
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderLeftWidth: 3,
  },
  colCode: { width: 130, fontFamily: MONO },
  colName: { flex: 1 },
  colCrew: { width: 140 },
  colWage: { width: 80 },
  colSkills: { width: 60, textAlign: 'right', fontFamily: MONO },
  colState: { width: 50, textAlign: 'right' },

  nameCell: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  avatar: { width: 32, height: 32, borderRadius: 8, alignItems: 'center', justifyContent: 'center' },
  avatarLg: { width: 56, height: 56, borderRadius: 14, alignItems: 'center', justifyContent: 'center' },

  wagePill: { width: 80, paddingVertical: 3, paddingHorizontal: 6, borderRadius: 3, alignItems: 'center' },
  statePill: {
    width: 50,
    paddingVertical: 3,
    paddingHorizontal: 5,
    borderRadius: 3,
    alignItems: 'center',
  },

  onShiftBox: {
    padding: 12,
    borderRadius: 10,
    borderWidth: 1,
  },
  dot: { width: 8, height: 8, borderRadius: 4 },

  kvGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  kvTile: { width: '48%', padding: 10, borderRadius: 8 },

  skillRow: { marginTop: 8, flexDirection: 'row', flexWrap: 'wrap', gap: 6 },
  skillChip: {
    paddingVertical: 5,
    paddingHorizontal: 10,
    borderRadius: 999,
    borderWidth: 1,
  },

  actionBtn: {
    flex: 1,
    height: 44,
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
