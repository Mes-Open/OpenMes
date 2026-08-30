import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, TextInput, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { format, parseISO } from 'date-fns';
import { router } from 'expo-router';

import { colors, fonts, radius, spacing } from '@openmes/ui';

import { Mono } from '@/components/ui/Mono';
import type { PlannerBacklogOrder, PlannerBoard } from '@/api/schedule';
import { useScheduleChanges, useUndoScheduleChange } from '@/hooks/queries/useSchedule';

import { priorityMeta, statusOf, tierBadge, tierColor, tierLabel, TIER_VALUES } from './plannerTheme';

/**
 * The right-hand rail: unscheduled orders grouped by priority, and the audit
 * trail with per-entry undo. Mirrors the web planner's BacklogRail.
 *
 * Undo is entirely server-side (a ScheduleChangeLog row) — there's no local undo
 * stack to keep in sync, and an undo is itself logged so it can be undone again.
 */

const RAIL_W = 300;
/** Priority groups, most urgent first — the same order the web renders. */
const GROUP_ORDER = ['Urgent', 'High', 'Medium', 'Low', 'Lowest'] as const;
const GROUP_PRIORITY: Record<string, number> = { Urgent: 5, High: 4, Medium: 3, Low: 2, Lowest: 1 };

type Tab = 'backlog' | 'changes';

interface Props {
  board: PlannerBoard;
  canEdit: boolean;
}

export function BacklogRail({ board, canEdit }: Props) {
  const { t } = useTranslation();
  const [tab, setTab] = useState<Tab>('backlog');
  const [q, setQ] = useState('');
  const [pf, setPf] = useState<string>('all');
  const [tierFilter, setTierFilter] = useState<string>('');

  const changes = useScheduleChanges(tab === 'changes');
  const undo = useUndoScheduleChange();

  // Filter, then bucket by priority label — same rules as the web rail.
  const groups = useMemo(() => {
    const needle = q.trim().toLowerCase();
    const items = board.backlogOrders
      .filter((o) => {
        if (pf !== 'all' && priorityMeta(o.priority).label !== pf) return false;
        if (tierFilter && o.customer_tier !== tierFilter) return false;
        if (!needle) return true;
        return [o.order_no, o.product_name, o.customer_name]
          .filter(Boolean)
          .some((v) => String(v).toLowerCase().includes(needle));
      })
      .slice()
      .sort((a, b) => (b.priority ?? 0) - (a.priority ?? 0));

    const out: Record<string, PlannerBacklogOrder[]> = {};
    items.forEach((o) => {
      const l = priorityMeta(o.priority).label;
      (out[l] = out[l] ?? []).push(o);
    });
    return { out, total: items.length };
  }, [board.backlogOrders, q, pf, tierFilter]);

  const runUndo = (id: number) =>
    undo.mutate(id, { onError: (e: Error) => Alert.alert(t('Could not undo'), e.message) });

  return (
    <View style={styles.rail}>
      <View style={styles.header}>
        <View style={styles.tabs}>
          {(['backlog', 'changes'] as Tab[]).map((k) => (
            <Pressable key={k} onPress={() => setTab(k)} style={[styles.tab, tab === k && styles.tabOn]}>
              <Mono size={10} weight="700" color={tab === k ? colors.bg : colors.muted}>
                {k === 'backlog' ? `${t('Backlog')} · ${board.backlogOrders.length}` : t('Changes')}
              </Mono>
            </Pressable>
          ))}
        </View>

        {tab === 'backlog' ? (
          <>
            <View style={styles.searchBox}>
              <View style={styles.searchRing} />
              <TextInput
                value={q}
                onChangeText={setQ}
                placeholder={t('Search backlog')}
                placeholderTextColor={colors.faint}
                style={styles.search}
              />
            </View>

            <View style={styles.filters}>
              {[
                { label: t('All'), v: 'all' },
                { label: t('Urgent'), v: 'Urgent' },
                { label: t('High'), v: 'High' },
                { label: t('Med'), v: 'Medium' },
              ].map((f) => (
                <Pressable
                  key={f.v}
                  onPress={() => setPf(f.v)}
                  style={[styles.filter, pf === f.v && styles.filterOn]}>
                  <Mono size={9} color={pf === f.v ? colors.bg : colors.muted}>
                    {f.label}
                  </Mono>
                </Pressable>
              ))}
            </View>

            {/* Customer-tier filter */}
            <View style={styles.tierRow}>
              <Pressable
                onPress={() => setTierFilter('')}
                style={[styles.tierChip, tierFilter === '' ? styles.tierChipOn : styles.tierChipOff]}>
                <Mono size={8.5} color={tierFilter === '' ? colors.bg : colors.muted}>
                  {t('All tiers')}
                </Mono>
              </Pressable>
              {TIER_VALUES.map((tier) => {
                const badge = tierBadge(tier)!;
                const on = tierFilter === tier;
                return (
                  <Pressable
                    key={tier}
                    onPress={() => setTierFilter(on ? '' : tier)}
                    style={[
                      styles.tierChip,
                      { backgroundColor: badge.bg, borderColor: on ? colors.faint : 'transparent', borderWidth: on ? 1.5 : 1 },
                    ]}>
                    <Mono size={8.5} color={badge.fg}>
                      {t(tierLabel(tier))}
                    </Mono>
                  </Pressable>
                );
              })}
            </View>
          </>
        ) : null}
      </View>

      {tab === 'backlog' ? (
        <ScrollView contentContainerStyle={styles.list}>
          {groups.total === 0 ? (
            <Mono size={10} color={colors.faint} style={styles.empty}>
              {board.backlogOrders.length === 0
                ? t('Backlog clear — all orders scheduled.')
                : t('No orders match.')}
            </Mono>
          ) : (
            GROUP_ORDER.filter((g) => groups.out[g]).map((g) => (
              <View key={g} style={styles.group}>
                <View style={styles.groupHead}>
                  <View
                    style={[styles.groupDot, { backgroundColor: priorityMeta(GROUP_PRIORITY[g]).color }]}
                  />
                  <Mono size={9} color={colors.muted} upper letterSpacing={0.8}>
                    {t(g)}
                  </Mono>
                  <Mono size={9} color={colors.faint}>
                    {groups.out[g].length}
                  </Mono>
                  <View style={styles.groupRule} />
                </View>

                {groups.out[g].map((o) => {
                  const pm = priorityMeta(o.priority);
                  const status = statusOf(o.status);
                  const badge = tierBadge(o.customer_tier);
                  const dot = tierColor(o.customer_tier);
                  return (
                    <View key={o.id} style={styles.card}>
                      <View style={styles.cardTop}>
                        {dot ? <View style={[styles.priDot, { backgroundColor: dot }]} /> : null}
                        <Mono size={10.5} weight="700" color={colors.ink} numberOfLines={1}>
                          {o.order_no}
                        </Mono>
                        <View style={styles.spacer} />
                        <View style={[styles.pill, { backgroundColor: status.bg }]}>
                          <Mono size={7.5} weight="700" color={status.fg} upper letterSpacing={0.5}>
                            {t(status.label)}
                          </Mono>
                        </View>
                      </View>

                      <Mono size={10} color={colors.ink} numberOfLines={1}>
                        {o.product_name || '—'}
                      </Mono>

                      {o.customer_name ? (
                        <View style={styles.custRow}>
                          <Mono size={9} color={colors.muted} numberOfLines={1} style={styles.custName}>
                            {o.customer_name}
                          </Mono>
                          {badge ? (
                            <View style={[styles.pill, { backgroundColor: badge.bg }]}>
                              <Mono size={7.5} color={badge.fg}>
                                {t(tierLabel(o.customer_tier))}
                              </Mono>
                            </View>
                          ) : null}
                        </View>
                      ) : null}

                      <View style={styles.metaRow}>
                        <Mono size={9} color={colors.faint}>
                          {o.planned_qty ?? '—'} {t('pcs')}
                        </Mono>
                        <Mono size={9} color={colors.faint}>
                          ·
                        </Mono>
                        <Mono size={9} color={pm.color}>
                          {t(pm.label)}
                        </Mono>
                      </View>
                    </View>
                  );
                })}
              </View>
            ))
          )}
        </ScrollView>
      ) : (
        <ScrollView contentContainerStyle={styles.list}>
          {changes.isLoading ? (
            <Mono size={10} color={colors.faint} style={styles.empty}>
              {t('Loading…')}
            </Mono>
          ) : (changes.data?.length ?? 0) === 0 ? (
            <Mono size={10} color={colors.faint} style={styles.empty}>
              {t('No changes yet.')}
            </Mono>
          ) : (
            changes.data!.map((c) => (
              <View key={c.id} style={styles.card}>
                <View style={styles.cardTop}>
                  <Mono size={10} weight="700" color={colors.ink} numberOfLines={1}>
                    {c.order_no ?? `#${c.work_order_id}`}
                  </Mono>
                  <View style={styles.spacer} />
                  <Mono size={8} color={colors.faint}>
                    {format(parseISO(c.created_at), 'dd/MM, HH:mm')}
                  </Mono>
                </View>
                <Mono size={8.5} color={colors.muted} numberOfLines={2}>
                  {c.action === 'undo' ? t('Undone') : t('Rescheduled')}
                  {c.user ? ` · ${c.user}` : ''}
                </Mono>
                {canEdit && !c.undone_at ? (
                  <Pressable onPress={() => runUndo(c.id)} style={styles.undoBtn} hitSlop={6}>
                    <Mono size={9} weight="700" color={colors.accent}>
                      {t('Undo')}
                    </Mono>
                  </Pressable>
                ) : c.undone_at ? (
                  <Mono size={8} color={colors.faint}>
                    {t('Already undone')}
                  </Mono>
                ) : null}
              </View>
            ))
          )}
        </ScrollView>
      )}

      {canEdit ? (
        <View style={styles.footer}>
          <Pressable style={[styles.action, styles.actionPrimary]} onPress={() => router.push('/work-orders/new')}>
            <Mono size={10} weight="700" color={colors.bg}>
              {t('+ New order')}
            </Mono>
          </Pressable>
          <Pressable style={[styles.action, styles.actionGhost]} onPress={() => router.push('/(drawer)/admin/csv-import')}>
            <Mono size={10} color={colors.muted}>
              {t('Import CSV')}
            </Mono>
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  rail: {
    width: RAIL_W,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.panel,
    overflow: 'hidden',
  },
  header: {
    padding: spacing.sm,
    gap: spacing.sm,
    borderBottomWidth: 1,
    borderColor: colors.line2,
  },
  tabs: {
    flexDirection: 'row',
    gap: 3,
    padding: 3,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  tab: { flex: 1, alignItems: 'center', paddingVertical: 5, borderRadius: radius.sm - 2 },
  tabOn: { backgroundColor: colors.ink },
  searchBox: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.sm,
    paddingHorizontal: 9,
    backgroundColor: colors.card,
  },
  searchRing: {
    width: 11,
    height: 11,
    borderRadius: 6,
    borderWidth: 2,
    borderColor: colors.faint,
  },
  search: {
    flex: 1,
    paddingVertical: 6,
    fontSize: 11,
    color: colors.ink,
    fontFamily: fonts.mono.native.regular,
  },
  filters: { flexDirection: 'row', gap: 4 },
  filter: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: 5,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
  },
  filterOn: { backgroundColor: colors.ink, borderColor: colors.ink },
  tierRow: { flexDirection: 'row', flexWrap: 'wrap', gap: 4 },
  tierChip: { paddingHorizontal: 6, paddingVertical: 2, borderRadius: 4, borderWidth: 1 },
  tierChipOn: { backgroundColor: colors.ink, borderColor: colors.ink },
  tierChipOff: { backgroundColor: colors.chip, borderColor: 'transparent' },
  list: { padding: spacing.sm, gap: 6, paddingBottom: 8 },
  empty: { textAlign: 'center', marginTop: 28 },
  group: { gap: 6, marginBottom: 6 },
  groupHead: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 4 },
  groupDot: { width: 6, height: 6, borderRadius: 2 },
  groupRule: { flex: 1, height: 1, backgroundColor: colors.line2 },
  card: {
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.sm,
    backgroundColor: colors.card,
    padding: 8,
    gap: 3,
  },
  cardTop: { flexDirection: 'row', alignItems: 'center', gap: 5 },
  priDot: { width: 6, height: 6, borderRadius: 3 },
  pill: { borderRadius: 3, paddingHorizontal: 4, paddingVertical: 1 },
  custRow: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  custName: { flexShrink: 1, maxWidth: 130 },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: 5, marginTop: 1 },
  spacer: { flex: 1 },
  undoBtn: { alignSelf: 'flex-start', marginTop: 3 },
  footer: {
    flexDirection: 'row',
    gap: 5,
    padding: spacing.sm,
    borderTopWidth: 1,
    borderColor: colors.line2,
  },
  action: { flex: 1, alignItems: 'center', paddingVertical: 8, borderRadius: radius.sm },
  actionPrimary: { backgroundColor: colors.ink },
  actionGhost: { backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line },
});
