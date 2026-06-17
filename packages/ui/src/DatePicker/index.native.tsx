/**
 * Calendar + DatePicker — Geist White system (design ref: OpenMES Components.dc.html §14).
 * Native twin of index.web.jsx — identical props API. `Calendar` is the inline
 * month grid (Monday-first, leading blanks, weekends faint, today = chip + accent
 * dot, selected = accent fill); `DatePicker` opens it in an RN Modal over the
 * scrim. Values are ISO `YYYY-MM-DD` strings.
 */
import React, { useEffect, useMemo, useState } from 'react';
import {
    Modal,
    Pressable,
    StyleSheet,
    Text,
    View,
    type StyleProp,
    type ViewStyle,
} from 'react-native';

import { colors, fonts, radius } from '../tokens';

const MONTHS = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WEEKDAYS = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

const pad = (n: number) => String(n).padStart(2, '0');
const toISO = (y: number, m: number, d: number) => `${y}-${pad(m + 1)}-${pad(d)}`;
function parseISO(s?: string | null): { y: number; m: number; d: number } | null {
    if (!s) return null;
    const [y, m, d] = String(s).split('-').map(Number);
    if (!y || !m || !d) return null;
    return { y, m: m - 1, d };
}
function todayISO(): string {
    const t = new Date();
    return toISO(t.getFullYear(), t.getMonth(), t.getDate());
}
const inRange = (iso: string, min?: string, max?: string) => (!min || iso >= min) && (!max || iso <= max);

export function formatDateLong(iso?: string | null): string {
    const p = parseISO(iso);
    return p ? `${p.d} ${MONTHS_SHORT[p.m]} ${p.y}` : '';
}

interface Cell {
    blank: boolean;
    key: string;
    d?: number;
    iso?: string;
    weekend?: boolean;
}

function buildCells(year: number, month: number): Cell[] {
    const firstDow = (new Date(year, month, 1).getDay() + 6) % 7; // Mon = 0
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells: Cell[] = [];
    for (let i = 0; i < firstDow; i++) cells.push({ blank: true, key: `b${i}` });
    for (let d = 1; d <= daysInMonth; d++) {
        const dow = new Date(year, month, d).getDay();
        cells.push({ blank: false, key: `d${d}`, d, iso: toISO(year, month, d), weekend: dow === 0 || dow === 6 });
    }
    return cells;
}

export interface CalendarProps {
    value?: string | null;
    onChange?: (iso: string | null) => void;
    min?: string;
    max?: string;
    hideToday?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function Calendar({ value, onChange, min, max, hideToday = false, style }: CalendarProps) {
    const selected = value || null;
    const initial = parseISO(selected) ?? parseISO(todayISO())!;
    const [view, setView] = useState({ y: initial.y, m: initial.m });

    useEffect(() => {
        const p = parseISO(selected);
        if (p && (p.y !== view.y || p.m !== view.m)) setView({ y: p.y, m: p.m });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected]);

    const cells = useMemo(() => buildCells(view.y, view.m), [view.y, view.m]);
    const today = todayISO();
    const todayShort = useMemo(() => {
        const t = parseISO(today)!;
        return `${t.d} ${MONTHS_SHORT[t.m]}`;
    }, [today]);

    const step = (delta: number) =>
        setView((v) => {
            const dt = new Date(v.y, v.m + delta, 1);
            return { y: dt.getFullYear(), m: dt.getMonth() };
        });

    return (
        <View style={[styles.calendar, style]}>
            <View style={styles.header}>
                <Pressable accessibilityRole="button" accessibilityLabel="Previous month" onPress={() => step(-1)} style={styles.navBtn}>
                    <Text style={styles.navGlyph}>‹</Text>
                </Pressable>
                <Text style={styles.monthLabel}>{MONTHS[view.m]} {view.y}</Text>
                <Pressable accessibilityRole="button" accessibilityLabel="Next month" onPress={() => step(1)} style={styles.navBtn}>
                    <Text style={styles.navGlyph}>›</Text>
                </Pressable>
            </View>
            <View style={styles.weekRow}>
                {WEEKDAYS.map((w) => (
                    <View key={w} style={styles.cell}>
                        <Text style={styles.weekday}>{w}</Text>
                    </View>
                ))}
            </View>
            <View style={styles.grid}>
                {cells.map((c) => {
                    if (c.blank) return <View key={c.key} style={[styles.cell, styles.blank]} />;
                    const iso = c.iso!;
                    const isSelected = iso === selected;
                    const isToday = iso === today;
                    const disabled = !inRange(iso, min, max);
                    return (
                        <View key={c.key} style={styles.cell}>
                            <Pressable
                                accessibilityRole="button"
                                accessibilityLabel={iso}
                                accessibilityState={{ selected: isSelected, disabled }}
                                disabled={disabled}
                                onPress={() => onChange?.(iso)}
                                style={[styles.day, isSelected && styles.daySelected, !isSelected && isToday && styles.dayToday]}
                            >
                                <Text
                                    style={[
                                        styles.dayText,
                                        disabled && styles.dayDisabled,
                                        !disabled && !isSelected && !isToday && c.weekend && styles.dayWeekend,
                                        !disabled && isToday && !isSelected && styles.dayTodayText,
                                        isSelected && styles.daySelectedText,
                                    ]}
                                >
                                    {c.d}
                                </Text>
                                {isToday && !isSelected && <View style={styles.todayDot} />}
                            </Pressable>
                        </View>
                    );
                })}
            </View>
            {!hideToday && (
                <View style={styles.footer}>
                    <Pressable onPress={() => { if (inRange(today, min, max)) onChange?.(today); }}>
                        <Text style={styles.todayBtn}>Today</Text>
                    </Pressable>
                    <View style={styles.legend}>
                        <View style={styles.legendDot} />
                        <Text style={styles.legendText}>{todayShort} = today</Text>
                    </View>
                </View>
            )}
        </View>
    );
}

export interface DatePickerProps {
    value?: string | null;
    onChange?: (iso: string | null) => void;
    label?: string;
    placeholder?: string;
    min?: string;
    max?: string;
    format?: (iso?: string | null) => string;
    disabled?: boolean;
    style?: StyleProp<ViewStyle>;
}

export function DatePicker({
    value,
    onChange,
    label,
    placeholder = 'Select date',
    min,
    max,
    format = formatDateLong,
    disabled = false,
    style,
}: DatePickerProps) {
    const [open, setOpen] = useState(false);
    const display = value ? format(value) : '';

    return (
        <View style={style}>
            {label != null && <Text style={styles.fieldLabel}>{label}</Text>}
            <Pressable
                accessibilityRole="button"
                accessibilityState={{ disabled, expanded: open }}
                disabled={disabled}
                onPress={() => setOpen(true)}
                style={[styles.trigger, disabled && styles.triggerDisabled]}
            >
                <Text style={[styles.triggerLabel, !display && styles.triggerPlaceholder]}>{display || placeholder}</Text>
                <View style={styles.glyph}>
                    <View style={styles.glyphTickLeft} />
                    <View style={styles.glyphTickRight} />
                </View>
            </Pressable>
            <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
                <Pressable style={styles.scrim} onPress={() => setOpen(false)}>
                    <Pressable style={styles.popover}>
                        <Calendar
                            value={value}
                            onChange={(iso) => { onChange?.(iso); if (iso) setOpen(false); }}
                            min={min}
                            max={max}
                        />
                    </Pressable>
                </Pressable>
            </Modal>
        </View>
    );
}

const styles = StyleSheet.create({
    calendar: {
        width: 280,
    },
    header: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: 14,
    },
    navBtn: {
        width: 28,
        height: 28,
        borderRadius: 7,
        borderWidth: 1,
        borderColor: colors.line,
        alignItems: 'center',
        justifyContent: 'center',
    },
    navGlyph: {
        fontSize: 14,
        color: colors.muted,
        lineHeight: 16,
    },
    monthLabel: {
        fontSize: 14,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    weekRow: {
        flexDirection: 'row',
        marginBottom: 6,
    },
    grid: {
        flexDirection: 'row',
        flexWrap: 'wrap',
    },
    cell: {
        width: `${100 / 7}%`,
        alignItems: 'center',
    },
    blank: {
        height: 34,
    },
    weekday: {
        fontSize: 9.5,
        letterSpacing: 0.4,
        fontFamily: fonts.mono.native.regular,
        color: colors.faint,
        height: 24,
        lineHeight: 24,
    },
    day: {
        height: 34,
        width: 34,
        borderRadius: radius.sm,
        alignItems: 'center',
        justifyContent: 'center',
    },
    daySelected: {
        backgroundColor: colors.accent,
    },
    dayToday: {
        backgroundColor: colors.chip,
    },
    dayText: {
        fontSize: 12.5,
        fontFamily: fonts.mono.native.regular,
        color: colors.ink,
    },
    dayWeekend: {
        color: colors.faint,
    },
    dayDisabled: {
        color: colors.faintest,
    },
    dayTodayText: {
        color: colors.ink,
        fontFamily: fonts.mono.native.semibold,
    },
    daySelectedText: {
        color: '#FFFFFF',
        fontFamily: fonts.mono.native.semibold,
    },
    todayDot: {
        position: 'absolute',
        bottom: 4,
        width: 3,
        height: 3,
        borderRadius: 2,
        backgroundColor: colors.accent,
    },
    footer: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginTop: 14,
        paddingTop: 13,
        borderTopWidth: 1,
        borderTopColor: colors.line2,
    },
    todayBtn: {
        fontSize: 12.5,
        fontFamily: fonts.sans.native.semibold,
        color: colors.accent,
    },
    legend: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 6,
    },
    legendDot: {
        width: 7,
        height: 7,
        borderRadius: 4,
        backgroundColor: colors.accent,
    },
    legendText: {
        fontSize: 10,
        fontFamily: fonts.mono.native.regular,
        color: colors.faint,
    },
    // trigger (design §14)
    fieldLabel: {
        textTransform: 'uppercase',
        fontSize: 9.5,
        letterSpacing: 0.8,
        fontFamily: fonts.mono.native.regular,
        color: colors.faint,
        marginBottom: 8,
    },
    trigger: {
        flexDirection: 'row',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: 10,
        backgroundColor: colors.bg,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.sm,
        paddingVertical: 10,
        paddingHorizontal: 13,
    },
    triggerDisabled: {
        opacity: 0.6,
    },
    triggerLabel: {
        fontSize: 13,
        fontFamily: fonts.mono.native.regular,
        color: colors.ink,
        flexShrink: 1,
    },
    triggerPlaceholder: {
        color: colors.faint,
    },
    glyph: {
        width: 15,
        height: 15,
        borderRadius: 3,
        borderWidth: 1.6,
        borderColor: colors.faint,
    },
    glyphTickLeft: {
        position: 'absolute',
        top: -3,
        left: 2,
        width: 2,
        height: 4,
        borderRadius: 2,
        backgroundColor: colors.faint,
    },
    glyphTickRight: {
        position: 'absolute',
        top: -3,
        right: 2,
        width: 2,
        height: 4,
        borderRadius: 2,
        backgroundColor: colors.faint,
    },
    scrim: {
        flex: 1,
        backgroundColor: colors.scrim,
        alignItems: 'center',
        justifyContent: 'center',
        padding: 24,
    },
    popover: {
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: radius.md,
        padding: 16,
        shadowColor: '#000',
        shadowOffset: { width: 0, height: 18 },
        shadowRadius: 24,
        shadowOpacity: 0.18,
        elevation: 12,
    },
});
