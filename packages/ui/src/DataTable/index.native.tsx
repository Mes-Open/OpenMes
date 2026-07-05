/**
 * DataTable — native twin of the web DataTable (Geist White §12).
 *
 * Mirrors the web chrome 1:1: toolbar (global search + column-visibility menu),
 * bordered table card with an UPPERCASE mono header row, hairline-separated
 * rows, a right-aligned actions column (icon trio + labeled variant buttons),
 * and the pager footer ("1–N / T" + ‹ page ›).
 *
 * The web twin wraps TanStack Table; that isn't available in RN, so this twin
 * accepts the simple column model the web ResourceTable pages declare:
 *   columns: [{ key, label, render?(row), align?: 'left'|'right', width?, flex? }]
 *   actions?: (row) => [{ label, icon?: 'edit'|'delete'|'activate'|'deactivate',
 *                         onPress, href-less; variant?: 'primary'|'secondary'|
 *                         'danger'|'warning' }]
 * Search is client-side over `searchKeys` (defaults to all string fields).
 * All user-facing strings arrive via props; only glyphs (‹ › ▾ ✓) are baked in.
 */
import React, { useMemo, useState } from 'react';
import {
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
  Platform,
} from 'react-native';

import Svg, { Path } from 'react-native-svg';

import { colors, fonts, radius } from '../tokens';

// react-native-web draws the browser's default focus outline on <input>;
// suppress it — the design's own focus ring (accent border/halo) replaces it.
const WEB_OUTLINE_RESET = Platform.OS === 'web' ? ({ outlineStyle: 'none' } as unknown as object) : null;

export interface DataTableColumn<Row> {
  key: string;
  label: string;
  render?: (row: Row) => React.ReactNode;
  align?: 'left' | 'right';
  width?: number;
  flex?: number;
}

export interface DataTableAction {
  label: string;
  icon?: 'edit' | 'delete' | 'activate' | 'deactivate';
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'danger' | 'warning';
}

export interface DataTableProps<Row> {
  data: Row[];
  columns: DataTableColumn<Row>[];
  getKey?: (row: Row) => string | number;
  onRowPress?: (row: Row) => void;
  actions?: (row: Row) => DataTableAction[];
  /** Toolbar/footer feature toggles — default on, like the web twin. */
  searchable?: boolean;
  searchPlaceholder?: string;
  /** Row fields searched by the global search (defaults to all own string/number fields). */
  searchKeys?: string[];
  columnToggle?: boolean;
  columnsLabel?: string;
  columnsMenuLabel?: string;
  /** Extra toolbar controls rendered between the search box and the Columns menu. */
  toolbarExtra?: React.ReactNode;
  /** Width of the actions column (default 150). Widen for rows with many actions. */
  actionsWidth?: number;
  paginated?: boolean;
  pageSize?: number;
  emptyText?: string;
}

// SVG paths copied verbatim from the web ResourceTable (components/ResourceTable.jsx
// ICON_PATH) so the icon trio renders pixel-identical on both platforms.
const ICON_PATH: Record<NonNullable<DataTableAction['icon']>, string> = {
  edit: 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
  delete: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
  deactivate: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
  activate: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
};

function ActionIcon({ icon }: { icon: NonNullable<DataTableAction['icon']> }) {
  const color = icon === 'delete' ? colors.blocked : colors.muted;
  return (
    <Svg width={20} height={20} viewBox="0 0 24 24" fill="none">
      <Path d={ICON_PATH[icon]} stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
    </Svg>
  );
}

export function DataTable<Row extends object>({
  data,
  columns,
  getKey = (row) => String((row as { id?: string | number }).id ?? JSON.stringify(row)),
  onRowPress,
  actions,
  searchable = true,
  searchPlaceholder = '',
  searchKeys,
  columnToggle = true,
  columnsLabel = 'Columns',
  columnsMenuLabel = '',
  toolbarExtra,
  actionsWidth = 150,
  paginated = true,
  pageSize = 12,
  emptyText = '—',
}: DataTableProps<Row>) {
  const [query, setQuery] = useState('');
  const [page, setPage] = useState(0);
  const [menuOpen, setMenuOpen] = useState(false);
  const [hidden, setHidden] = useState<Set<string>>(new Set());

  const visibleColumns = columns.filter((c) => !hidden.has(c.key));

  const filtered = useMemo(() => {
    const q = query.trim().toLowerCase();
    if (!q) return data;
    const keys = searchKeys ?? Object.keys(data[0] ?? {});
    return data.filter((row) =>
      keys.some((k) => {
        const v = (row as Record<string, unknown>)[k];
        return (typeof v === 'string' || typeof v === 'number') && String(v).toLowerCase().includes(q);
      }),
    );
  }, [data, query, searchKeys]);

  const effectivePageSize = paginated ? pageSize : Number.MAX_SAFE_INTEGER;
  const pageCount = Math.max(1, Math.ceil(filtered.length / effectivePageSize));
  const pageIndex = Math.min(page, pageCount - 1);
  const pageRows = filtered.slice(pageIndex * effectivePageSize, (pageIndex + 1) * effectivePageSize);
  const rangeStart = filtered.length === 0 ? 0 : pageIndex * effectivePageSize + 1;
  const rangeEnd = pageIndex * effectivePageSize + pageRows.length;

  return (
    <View>
      {/* Toolbar — search + column-visibility menu, like the web twin. */}
      {(searchable || columnToggle || toolbarExtra) && (
        <View style={styles.toolbar}>
          {searchable ? (
            <View style={styles.search}>
              <View style={styles.searchGlyph} />
              <TextInput
                value={query}
                onChangeText={(v) => {
                  setQuery(v);
                  setPage(0);
                }}
                placeholder={searchPlaceholder}
                placeholderTextColor={colors.faint}
                style={[styles.searchInput, WEB_OUTLINE_RESET]}
              />
            </View>
          ) : null}
          {toolbarExtra}
          {columnToggle ? (
            <View>
              <Pressable onPress={() => setMenuOpen((o) => !o)} style={styles.columnsBtn}>
                <Text style={styles.columnsBtnText}>{columnsLabel}</Text>
                <Text style={styles.columnsCaret}>▾</Text>
              </Pressable>
              {menuOpen ? (
                <View style={styles.columnsMenu}>
                  {columnsMenuLabel ? (
                    <Text style={styles.columnsMenuLabel}>{columnsMenuLabel.toUpperCase()}</Text>
                  ) : null}
                  {columns.map((c) => {
                    const on = !hidden.has(c.key);
                    return (
                      <Pressable
                        key={c.key}
                        onPress={() =>
                          setHidden((cur) => {
                            const next = new Set(cur);
                            if (on) next.add(c.key);
                            else next.delete(c.key);
                            return next;
                          })
                        }
                        style={styles.columnsMenuRow}>
                        <View style={[styles.check, on && styles.checkOn]}>
                          {on ? <Text style={styles.checkMark}>✓</Text> : null}
                        </View>
                        <Text style={styles.columnsMenuText}>{c.label}</Text>
                      </Pressable>
                    );
                  })}
                </View>
              ) : null}
            </View>
          ) : null}
        </View>
      )}

      {/* Table card */}
      <View style={styles.card}>
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ minWidth: '100%' }}>
          <View style={{ minWidth: '100%' }}>
            {/* Header */}
            <View style={[styles.row, styles.headerRow]}>
              {visibleColumns.map((c) => (
                <View key={c.key} style={cellStyle(c)}>
                  <Text style={[styles.headerText, c.align === 'right' && { textAlign: 'right' }]}>
                    {c.label.toUpperCase()}
                  </Text>
                </View>
              ))}
              {actions ? <View style={{ width: actionsWidth }} /> : null}
            </View>

            {/* Rows */}
            {pageRows.length === 0 ? (
              <View style={styles.emptyRow}>
                <Text style={styles.emptyText}>{emptyText}</Text>
              </View>
            ) : (
              pageRows.map((row) => (
                <Pressable
                  key={getKey(row)}
                  onPress={onRowPress ? () => onRowPress(row) : undefined}
                  disabled={!onRowPress}
                  style={({ pressed }) => [styles.row, styles.dataRow, pressed && onRowPress ? { opacity: 0.6 } : null]}>
                  {visibleColumns.map((c) => (
                    <View key={c.key} style={cellStyle(c)}>
                      {c.render ? (
                        wrapCell(c.render(row), c.align)
                      ) : (
                        <Text numberOfLines={1} style={[styles.cellText, c.align === 'right' && { textAlign: 'right' }]}>
                          {String((row as Record<string, unknown>)[c.key] ?? '—')}
                        </Text>
                      )}
                    </View>
                  ))}
                  {actions ? (
                    <View style={[styles.actionsCell, { width: actionsWidth }]}>
                      {actions(row).map((a, i) =>
                        a.icon ? (
                          <Pressable
                            key={i}
                            onPress={(e) => {
                              e.stopPropagation?.();
                              a.onPress();
                            }}
                            hitSlop={6}
                            style={styles.iconBtn}
                            accessibilityLabel={a.label}>
                            <ActionIcon icon={a.icon} />
                          </Pressable>
                        ) : (
                          <Pressable
                            key={i}
                            onPress={(e) => {
                              e.stopPropagation?.();
                              a.onPress();
                            }}
                            style={[styles.actionBtn, actionVariant(a.variant)]}>
                            <Text style={[styles.actionBtnText, actionVariantText(a.variant)]}>{a.label}</Text>
                          </Pressable>
                        ),
                      )}
                    </View>
                  ) : null}
                </Pressable>
              ))
            )}

            {/* Footer — "1-N / T" + pager, like web. */}
            {paginated ? (
              <View style={styles.footer}>
                <Text style={styles.footerRange}>{`${rangeStart}-${rangeEnd} / ${filtered.length}`}</Text>
                <View style={{ flex: 1 }} />
                <Pressable
                  disabled={pageIndex === 0}
                  onPress={() => setPage((p) => Math.max(0, p - 1))}
                  style={[styles.pagerBtn, pageIndex === 0 && { opacity: 0.35 }]}>
                  <Text style={styles.pagerGlyph}>‹</Text>
                </Pressable>
                <View style={[styles.pagerBtn, styles.pagerCurrent]}>
                  <Text style={styles.pagerCurrentText}>{pageIndex + 1}</Text>
                </View>
                <Pressable
                  disabled={pageIndex >= pageCount - 1}
                  onPress={() => setPage((p) => Math.min(pageCount - 1, p + 1))}
                  style={[styles.pagerBtn, pageIndex >= pageCount - 1 && { opacity: 0.35 }]}>
                  <Text style={styles.pagerGlyph}>›</Text>
                </Pressable>
              </View>
            ) : null}
          </View>
        </ScrollView>
      </View>
    </View>
  );
}

function cellStyle(c: DataTableColumn<never>): object {
  if (c.width) return { width: c.width, paddingRight: 10 };
  return { flex: c.flex ?? 1, minWidth: 60, paddingRight: 10 };
}

function wrapCell(node: React.ReactNode, align?: 'left' | 'right') {
  if (typeof node === 'string' || typeof node === 'number') {
    return (
      <Text numberOfLines={1} style={[styles.cellText, align === 'right' && { textAlign: 'right' }]}>
        {node}
      </Text>
    );
  }
  return <View style={align === 'right' ? { alignItems: 'flex-end' } : undefined}>{node}</View>;
}

function actionVariant(v?: DataTableAction['variant']): object {
  switch (v) {
    case 'primary':
      return { backgroundColor: colors.ink };
    case 'danger':
      return { backgroundColor: colors.blockedBg };
    case 'warning':
      return { backgroundColor: colors.downtimeBg };
    default:
      return { backgroundColor: colors.chip };
  }
}

function actionVariantText(v?: DataTableAction['variant']): object {
  switch (v) {
    case 'primary':
      return { color: colors.bg };
    case 'danger':
      return { color: colors.blocked };
    case 'warning':
      return { color: colors.downtime };
    default:
      return { color: colors.ink };
  }
}

const styles = StyleSheet.create({
  toolbar: { flexDirection: 'row', alignItems: 'center', gap: 12, marginBottom: 12, zIndex: 20 },
  search: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 9,
    maxWidth: 300,
    flex: 1,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.bg,
    paddingHorizontal: 12,
    paddingVertical: 8,
  },
  searchGlyph: { width: 13, height: 13, borderRadius: 7, borderWidth: 2, borderColor: colors.faint },
  searchInput: { flex: 1, minWidth: 0, fontSize: 13, color: colors.ink, fontFamily: fonts.sans.native.regular, padding: 0 },
  columnsBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 7,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    paddingHorizontal: 13,
    paddingVertical: 8,
  },
  columnsBtnText: { fontSize: 12.5, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  columnsCaret: { fontSize: 10, color: colors.faint },
  columnsMenu: {
    position: 'absolute',
    top: 42,
    left: 0,
    zIndex: 30,
    width: 178,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.line,
    backgroundColor: colors.card,
    padding: 6,
    shadowColor: '#000',
    shadowOpacity: 0.15,
    shadowRadius: 22,
    shadowOffset: { width: 0, height: 12 },
    elevation: 8,
  },
  columnsMenuLabel: {
    paddingHorizontal: 9,
    paddingTop: 7,
    paddingBottom: 6,
    fontFamily: fonts.mono.native.medium,
    fontSize: 9,
    letterSpacing: 1,
    color: colors.faint,
  },
  columnsMenuRow: { flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: 6, paddingHorizontal: 9, paddingVertical: 8 },
  columnsMenuText: { fontSize: 13, color: colors.ink, fontFamily: fonts.sans.native.regular },
  check: {
    width: 17,
    height: 17,
    borderRadius: 5,
    borderWidth: 2,
    borderColor: colors.faintest,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkOn: { backgroundColor: colors.accent, borderWidth: 0 },
  checkMark: { fontSize: 10, lineHeight: 12, fontWeight: '700', color: '#FFFFFF' },
  card: {
    borderWidth: 1,
    borderColor: colors.line,
    borderRadius: radius.md,
    backgroundColor: colors.card,
    overflow: 'hidden',
  },
  row: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16 },
  headerRow: { paddingVertical: 10, borderBottomWidth: 1, borderBottomColor: colors.line, backgroundColor: colors.panel },
  headerText: { fontFamily: fonts.mono.native.medium, fontSize: 9, letterSpacing: 0.8, color: colors.faint },
  dataRow: { paddingVertical: 13, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  cellText: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  emptyRow: { padding: 16 },
  emptyText: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular },
  actionsCell: { flexDirection: 'row', alignItems: 'center', justifyContent: 'flex-end', gap: 8 },
  iconBtn: { padding: 6, borderRadius: radius.sm },
  actionBtn: { borderRadius: radius.sm, paddingHorizontal: 12, paddingVertical: 6 },
  actionBtnText: { fontSize: 12.5, fontFamily: fonts.sans.native.semibold },
  footer: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: 16, paddingVertical: 10, borderTopWidth: 1, borderTopColor: colors.line2 },
  footerRange: { fontFamily: fonts.mono.native.regular, fontSize: 10.5, color: colors.faint },
  pagerBtn: {
    height: 26,
    minWidth: 26,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
    marginLeft: 6,
  },
  pagerGlyph: { fontSize: 14, color: colors.muted },
  pagerCurrent: { backgroundColor: colors.ink },
  pagerCurrentText: { fontSize: 11, color: colors.bg, fontFamily: fonts.mono.native.medium },
});
