/**
 * Design-system gallery — dev-only screen (route: /dev/components) exercising
 * @openmes/ui on native. Mirrors the web gallery at backend /dev/components.
 * Specimen strings are sample data on purpose (not product UI — no i18n).
 */
import React, { useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { Redirect } from 'expo-router';

import {
    ActionMenu,
    Badge,
    Button,
    Calendar,
    Checkbox,
    ConfirmDialog,
    DatePicker,
    Dropdown,
    IconButton,
    InlineAlert,
    Modal,
    OnlineDot,
    ProgressBar,
    QuantityStepper,
    RadioGroup,
    SegmentedControl,
    Skeleton,
    StatusPill,
    Switch,
    Tabs,
    TextField,
    ToastProvider,
    colors,
    fonts,
    useToast,
} from '@openmes/ui';
import {
    ActionSheet,
    BigStepper,
    BottomSheet,
    FAB,
    LargeTitleHeader,
    SearchField,
    SnackbarHost,
    SwipeRow,
    TopAppBar,
    WheelPicker,
    useSnackbar,
} from '@openmes/ui/native';

function Section({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <View style={styles.section}>
            <Text style={styles.sectionLabel}>{label}</Text>
            <View style={styles.sectionBody}>{children}</View>
        </View>
    );
}

function GalleryBody() {
    const toast = useToast();
    const { show } = useSnackbar();
    const [on, setOn] = useState(true);
    const [checked, setChecked] = useState(true);
    const [lot, setLot] = useState('26-0512-A');
    const [qty, setQty] = useState(250);
    const [big, setBig] = useState(12);
    const [size, setSize] = useState('small');
    const [mode, setMode] = useState('batch');
    const [tab, setTab] = useState('details');
    const [status, setStatus] = useState('all');
    const [search, setSearch] = useState('');
    const [shift, setShift] = useState('z2');
    const [due, setDue] = useState<string | null>('2026-05-26');
    const [day, setDay] = useState<string | null>('2026-05-26');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [modalOpen, setModalOpen] = useState(false);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [actionsOpen, setActionsOpen] = useState(false);

    return (
        <View style={styles.screen}>
            <TopAppBar title="WO-2026-001" subtitle="HEPA-13 STANDARD" onBack={() => {}} onMenu={() => setActionsOpen(true)} />
            <ScrollView contentContainerStyle={styles.scroll}>
                <LargeTitleHeader title="Component gallery" contextLabel="A-SHIFT · 06:00" avatarInitials="AK" />

                <Section label="02 — BUTTONS">
                    <View style={styles.row}>
                        <Button variant="primary">Primary</Button>
                        <Button variant="accent">Accent</Button>
                        <Button variant="secondary">Secondary</Button>
                    </View>
                    <View style={styles.row}>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="danger">Danger</Button>
                        <Button variant="secondary" loading>Saving…</Button>
                        <IconButton variant="primary" accessibilityLabel="Add">+</IconButton>
                    </View>
                </Section>

                <Section label="03 — SWITCHES & CHECKBOX">
                    <View style={styles.row}>
                        <Switch checked={on} onChange={setOn} />
                        <Checkbox checked={checked} onChange={setChecked} label="Checked" />
                    </View>
                </Section>

                <Section label="04 — INPUTS">
                    <TextField label="Lot number" mono value={lot} onChange={setLot} />
                    <QuantityStepper value={qty} onChange={setQty} min={0} />
                    <SearchField value={search} onChange={setSearch} placeholder="Search work orders" />
                    <BigStepper value={big} onChange={setBig} min={0} />
                </Section>

                <Section label="14 — DATE PICKER">
                    <DatePicker label="Due date" value={due} onChange={setDue} />
                    <Calendar value={day} onChange={setDay} />
                </Section>

                <Section label="15 — SKELETON">
                    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 12 }}>
                        <Skeleton circle height={40} />
                        <View style={{ flex: 1, gap: 8 }}>
                            <Skeleton width="70%" height={13} />
                            <Skeleton width="45%" height={11} />
                        </View>
                    </View>
                </Section>

                <Section label="05 — SELECTION & TABS">
                    <SegmentedControl
                        options={[{ value: 'small', label: 'Small' }, { value: 'medium', label: 'Medium' }, { value: 'large', label: 'Large' }]}
                        value={size}
                        onChange={setSize}
                    />
                    <RadioGroup
                        options={[{ value: 'batch', label: 'Per batch' }, { value: 'shift', label: 'Per shift' }]}
                        value={mode}
                        onChange={setMode}
                    />
                    <Tabs
                        tabs={[{ value: 'details', label: 'Details' }, { value: 'routing', label: 'Routing' }, { value: 'history', label: 'History' }]}
                        value={tab}
                        onChange={setTab}
                    />
                    <WheelPicker
                        options={[{ value: 'z1', label: 'Z1 · 06–14' }, { value: 'z2', label: 'Z2 · 14–22' }, { value: 'z3', label: 'Z3 · 22–06' }]}
                        value={shift}
                        onChange={(v) => setShift(String(v))}
                    />
                </Section>

                <Section label="06 — STATUS & BADGES">
                    <View style={styles.row}>
                        <StatusPill status="running" label="RUNNING" />
                        <StatusPill status="pending" label="PENDING" />
                        <StatusPill status="blocked" label="BLOCKED" />
                    </View>
                    <View style={styles.row}>
                        <Badge variant="danger">3</Badge>
                        <Badge variant="neutral">12</Badge>
                        <Badge variant="outline">HIGH</Badge>
                        <OnlineDot label="ONLINE" />
                    </View>
                    <ProgressBar value={43} />
                </Section>

                <Section label="07 — MENUS & DROPDOWN">
                    <ActionMenu
                        trigger={<Button variant="ghost">Actions ⋯</Button>}
                        items={[
                            { key: 'edit', label: 'Edit order', onSelect: () => {} },
                            { key: 'dup', label: 'Duplicate', onSelect: () => {} },
                            { divider: true },
                            { key: 'del', label: 'Delete', destructive: true, onSelect: () => {} },
                        ]}
                    />
                    <Dropdown
                        options={[{ value: 'all', label: 'All status' }, { value: 'running', label: 'Running' }, { value: 'blocked', label: 'Blocked' }]}
                        value={status}
                        onChange={(v) => setStatus(v as string)}
                    />
                </Section>

                <Section label="08 — INLINE ALERTS">
                    <InlineAlert severity="success" title="Batch #3 closed">108 pcs accepted · LOT printed.</InlineAlert>
                    <InlineAlert severity="error" title="Production blocked">Media tear — WO-2026-006 halted.</InlineAlert>
                </Section>

                <Section label="09/10 — OVERLAYS">
                    <View style={styles.row}>
                        <Button variant="secondary" onPress={() => toast({ severity: 'success', title: 'Output saved', body: '+12 pcs logged' })}>Toast</Button>
                        <Button variant="secondary" onPress={() => show({ message: 'Output saved · +12 pcs', actionLabel: 'Undo', onAction: () => {} })}>Snackbar</Button>
                    </View>
                    <View style={styles.row}>
                        <Button variant="danger" onPress={() => setConfirmOpen(true)}>Confirm</Button>
                        <Button variant="primary" onPress={() => setModalOpen(true)}>Modal</Button>
                        <Button variant="accent" onPress={() => setSheetOpen(true)}>Bottom sheet</Button>
                    </View>
                </Section>

                <Section label="10 — SWIPE ROW (swipe left)">
                    <SwipeRow
                        actions={[
                            { key: 'hold', label: 'Hold', color: colors.downtime, onPress: () => show({ message: 'Held' }) },
                            { key: 'block', label: 'Block', color: colors.blocked, onPress: () => show({ message: 'Marked blocked' }) },
                        ]}
                    >
                        <View style={styles.swipeContent}>
                            <Text style={styles.swipeTitle}>HEPA Slim</Text>
                            <Text style={styles.swipeMeta}>WO-2026-005 · 120 PCS</Text>
                        </View>
                    </SwipeRow>
                </Section>

                <View style={{ height: 120 }} />
            </ScrollView>

            <FAB onPress={() => setSheetOpen(true)} accessibilityLabel="New work order" />

            <ConfirmDialog
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={() => setConfirmOpen(false)}
                title="Blocking issue"
                confirmLabel="Report"
                cancelLabel="Dismiss"
            >
                Media tear on infeed roll. Production is halted until cleared.
            </ConfirmDialog>

            <Modal
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                title="New work order"
                subtitle="LINE WSZ-01"
                footer={
                    <>
                        <Button variant="secondary" onPress={() => setModalOpen(false)}>Cancel</Button>
                        <Button variant="primary" onPress={() => setModalOpen(false)}>Create</Button>
                    </>
                }
            >
                <TextField label="Qty" mono value="250" onChange={() => {}} />
            </Modal>

            <BottomSheet
                open={sheetOpen}
                onClose={() => setSheetOpen(false)}
                title="Log output"
                subtitle="WO-2026-001 · STEP 4/6"
                footer={<Button variant="primary" onPress={() => setSheetOpen(false)}>Confirm +12 pcs</Button>}
            >
                <RadioGroup
                    horizontal={false}
                    options={[{ value: 'current', label: 'Add to current batch' }, { value: 'new', label: 'Start new batch' }]}
                    value="current"
                    onChange={() => {}}
                />
            </BottomSheet>

            <ActionSheet
                open={actionsOpen}
                onClose={() => setActionsOpen(false)}
                title="WO-2026-007 ACTIONS"
                options={[
                    { key: 'edit', label: 'Edit order', onSelect: () => {} },
                    { key: 'dup', label: 'Duplicate', onSelect: () => {} },
                    { key: 'del', label: 'Delete order', destructive: true, onSelect: () => {} },
                ]}
            />
        </View>
    );
}

export default function ComponentGalleryScreen() {
    if (!__DEV__) {
        return <Redirect href="/" />;
    }
    return (
        <ToastProvider>
            <SnackbarHost>
                <GalleryBody />
            </SnackbarHost>
        </ToastProvider>
    );
}

const styles = StyleSheet.create({
    screen: {
        flex: 1,
        backgroundColor: colors.bg,
    },
    scroll: {
        padding: 16,
    },
    section: {
        backgroundColor: colors.card,
        borderWidth: 1,
        borderColor: colors.line,
        borderRadius: 12,
        padding: 16,
        marginTop: 14,
    },
    sectionLabel: {
        fontFamily: fonts.mono.native.regular,
        fontSize: 10,
        letterSpacing: 1.2,
        color: colors.faint,
        marginBottom: 12,
    },
    sectionBody: {
        gap: 12,
    },
    row: {
        flexDirection: 'row',
        alignItems: 'center',
        gap: 9,
        flexWrap: 'wrap',
    },
    swipeContent: {
        backgroundColor: colors.card,
        paddingVertical: 13,
        paddingHorizontal: 14,
    },
    swipeTitle: {
        fontSize: 14,
        fontFamily: fonts.sans.native.semibold,
        color: colors.ink,
    },
    swipeMeta: {
        fontFamily: fonts.mono.native.regular,
        fontSize: 10,
        color: colors.faint,
        marginTop: 3,
    },
});
