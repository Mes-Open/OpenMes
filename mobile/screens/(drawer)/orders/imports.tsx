/**
 * CSV Import — a 1:1 port of the web admin importer (Pages/admin/CsvImport.jsx +
 * CsvImportMapping.jsx). Two steps:
 *   1. Upload — pick a CSV, choose a duplicate strategy, optionally load a saved
 *      mapping profile and/or assign every row to one production line.
 *   2. Map columns — assign each CSV column to a system field (with auto-detect),
 *      preview the data, optionally save the mapping as a profile, then run the
 *      import. Recent imports + saved profiles show alongside, like the web.
 */
import { useMemo, useState } from 'react';
import { ScrollView, StyleSheet, Switch, Text, TextInput, View } from 'react-native';
import { FontAwesome } from '@expo/vector-icons';
import { format, formatDistanceToNowStrict, isValid, parseISO } from 'date-fns';
import * as DocumentPicker from 'expo-document-picker';
import { useTranslation } from 'react-i18next';

import { Dropdown, colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Mono } from '@/components/ui/Mono';
import { useLines } from '@/hooks/queries/useUsers';
import {
  useCsvImportMappings,
  useCsvImports,
  useExecuteCsvImport,
  useUploadCsv,
} from '@/hooks/queries/useCsvImports';
import type { CsvColumnMap, CsvImport, CsvImportStrategy, CsvUploadResult } from '@/api/csvImports';

const IGNORE = '_ignore';

/** Mappable system fields (keys match the API). order_no/planned_qty/product_type_code
 *  are required; line_code is required unless a target line is chosen. */
const SYSTEM_FIELDS: { key: string; label: string; required?: boolean }[] = [
  { key: 'order_no', label: 'Order Number', required: true },
  { key: 'planned_qty', label: 'Quantity (Planned)', required: true },
  { key: 'product_type_code', label: 'Product Type Code', required: true },
  { key: 'line_code', label: 'Line Code' },
  { key: 'product_name', label: 'Product Name' },
  { key: 'priority', label: 'Priority' },
  { key: 'due_date', label: 'Due Date' },
  { key: 'description', label: 'Description' },
];

/** Header alias → system field, for one-click auto-detection (from the web). */
const AUTO_DETECT: Record<string, string[]> = {
  order_no: ['order_no', 'order no', 'orderno', 'order number', 'order_number', 'wo_no', 'work_order', 'wo no'],
  product_name: ['product_name', 'product name', 'productname', 'product', 'item', 'item name'],
  planned_qty: ['quantity', 'qty', 'planned_qty', 'planned qty', 'amount'],
  line_code: ['line_code', 'line code', 'linecode', 'line', 'production_line'],
  product_type_code: ['product_type_code', 'product type code', 'product_type', 'product type', 'type code', 'type'],
  priority: ['priority', 'prio'],
  due_date: ['due_date', 'due date', 'duedate', 'deadline', 'target date', 'delivery_date'],
  description: ['description', 'desc', 'notes', 'comment', 'remarks'],
};

const STRATEGIES: { value: CsvImportStrategy; label: string }[] = [
  { value: 'update_or_create', label: 'Update if exists, create if new' },
  { value: 'skip_existing', label: 'Skip existing rows' },
  { value: 'error_on_duplicate', label: 'Error on duplicate' },
];

function autoDetect(headers: string[]): Record<string, string> {
  const map: Record<string, string> = {};
  for (const h of headers) {
    const lower = h.trim().toLowerCase();
    const hit = Object.entries(AUTO_DETECT).find(([, aliases]) => aliases.includes(lower));
    map[h] = hit ? hit[0] : IGNORE;
  }
  return map;
}

export function CsvImportsScreen() {
  const { t } = useTranslation();

  // ── Step 1 state ─────────────────────────────────────────────────────────
  const [file, setFile] = useState<{ uri: string; name: string; mimeType?: string } | null>(null);
  const [strategy, setStrategy] = useState<CsvImportStrategy>('update_or_create');
  const [profileId, setProfileId] = useState('');
  const [targetLineId, setTargetLineId] = useState('');

  // ── Step 2 state ─────────────────────────────────────────────────────────
  const [uploaded, setUploaded] = useState<CsvUploadResult | null>(null);
  const [headerMap, setHeaderMap] = useState<Record<string, string>>({});
  const [saveProfile, setSaveProfile] = useState(false);
  const [profileName, setProfileName] = useState('');

  const importsQuery = useCsvImports();
  const mappingsQuery = useCsvImportMappings();
  const linesQuery = useLines();
  const upload = useUploadCsv();
  const execute = useExecuteCsvImport();

  const imports = importsQuery.data ?? [];
  const lines = linesQuery.data ?? [];
  const profiles = mappingsQuery.data ?? [];

  const pickFile = async () => {
    const res = await DocumentPicker.getDocumentAsync({
      type: ['text/csv', 'text/plain', 'text/comma-separated-values', 'application/csv'],
      copyToCacheDirectory: true,
    });
    if (!res.canceled && res.assets?.[0]) {
      const a = res.assets[0];
      setFile({ uri: a.uri, name: a.name, mimeType: a.mimeType });
    }
  };

  const doUpload = async () => {
    if (!file) return;
    const result = await upload.mutateAsync(file);
    setUploaded(result);
    // Seed the mapping from a saved profile if one is selected, else auto-detect.
    const profile = profiles.find((p) => String(p.id) === profileId);
    if (profile?.mapping_config?.columns) {
      const seeded: Record<string, string> = {};
      for (const h of result.headers) seeded[h] = IGNORE;
      for (const [field, cfg] of Object.entries(profile.mapping_config.columns)) {
        if (cfg?.csv_column && result.headers.includes(cfg.csv_column)) seeded[cfg.csv_column] = field;
      }
      setHeaderMap(seeded);
    } else {
      setHeaderMap(autoDetect(result.headers));
    }
  };

  // Build the field→column map the API expects from the per-header selections.
  const columns: CsvColumnMap = useMemo(() => {
    const out: CsvColumnMap = {};
    for (const [header, field] of Object.entries(headerMap)) {
      if (field && field !== IGNORE) out[field] = { csv_column: header };
    }
    return out;
  }, [headerMap]);

  const hasTargetLine = targetLineId !== '';
  const missingRequired = SYSTEM_FIELDS.filter(
    (f) => f.required && !columns[f.key],
  ).map((f) => f.label);
  const lineMissing = !hasTargetLine && !columns.line_code;
  const canRun = missingRequired.length === 0 && !lineMissing;

  const runImport = async () => {
    if (!uploaded || !canRun) return;
    await execute.mutateAsync({
      upload_id: uploaded.upload_id,
      mapping: {
        import_strategy: strategy,
        columns,
        target_line_id: hasTargetLine ? Number(targetLineId) : null,
      },
      save_mapping_template: saveProfile && profileName.trim().length > 0,
      mapping_template_name: profileName.trim() || undefined,
    });
    // Back to step 1; refresh recent imports.
    resetToUpload();
    importsQuery.refetch();
  };

  const resetToUpload = () => {
    setUploaded(null);
    setHeaderMap({});
    setFile(null);
    setSaveProfile(false);
    setProfileName('');
  };

  // ── Render ───────────────────────────────────────────────────────────────
  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{t('CSV Import')}</Text>
      <Text style={styles.sub}>{t('Import work orders from a CSV file with custom column mapping')}</Text>

      {!uploaded ? (
        <UploadStep
          t={t}
          file={file}
          onPick={pickFile}
          strategy={strategy}
          setStrategy={setStrategy}
          profileId={profileId}
          setProfileId={setProfileId}
          profiles={profiles}
          targetLineId={targetLineId}
          setTargetLineId={setTargetLineId}
          lines={lines}
          uploading={upload.isPending}
          uploadError={upload.error as Error | null}
          onUpload={doUpload}
        />
      ) : (
        <MappingStep
          t={t}
          uploaded={uploaded}
          headerMap={headerMap}
          setHeaderMap={setHeaderMap}
          onAutoDetect={() => setHeaderMap(autoDetect(uploaded.headers))}
          onClear={() => setHeaderMap(Object.fromEntries(uploaded.headers.map((h) => [h, IGNORE])))}
          missingRequired={lineMissing ? [...missingRequired, t('Line Code')] : missingRequired}
          canRun={canRun}
          running={execute.isPending}
          runError={execute.error as Error | null}
          onRun={runImport}
          onBack={resetToUpload}
          saveProfile={saveProfile}
          setSaveProfile={setSaveProfile}
          profileName={profileName}
          setProfileName={setProfileName}
        />
      )}

      {/* Recent imports — always visible, like the web sidebar. */}
      <Text style={styles.section}>{t('Recent Imports')}</Text>
      {imports.length === 0 ? (
        <Text style={styles.empty}>{t('No imports yet')}</Text>
      ) : (
        imports.map((item) => <ImportRow key={item.id} item={item} />)
      )}
    </ScrollView>
  );
}

// ── Step 1: Upload ───────────────────────────────────────────────────────────

function UploadStep(props: {
  t: (k: string) => string;
  file: { name: string } | null;
  onPick: () => void;
  strategy: CsvImportStrategy;
  setStrategy: (s: CsvImportStrategy) => void;
  profileId: string;
  setProfileId: (s: string) => void;
  profiles: { id: number; name: string }[];
  targetLineId: string;
  setTargetLineId: (s: string) => void;
  lines: { id: number; name: string; code?: string | null }[];
  uploading: boolean;
  uploadError: Error | null;
  onUpload: () => void;
}) {
  const { t } = props;
  return (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>{t('Upload File')}</Text>

      <Button
        title={props.file ? props.file.name : t('Drop file here or browse')}
        onPress={props.onPick}
        variant="outline"
        leftIcon={<FontAwesome name="cloud-upload" size={16} color={colors.accent} />}
      />
      <Text style={styles.hint}>{t('Max 10 MB · .csv, .txt')}</Text>

      <Field label={t('Duplicate Strategy')}>
        <Dropdown
          value={props.strategy}
          onChange={(v) => props.setStrategy(v as CsvImportStrategy)}
          options={STRATEGIES.map((s) => ({ value: s.value, label: t(s.label) }))}
        />
      </Field>

      <Field label={t('Load Mapping Profile (optional)')}>
        <Dropdown
          value={props.profileId}
          onChange={(v) => props.setProfileId(v as string)}
          placeholder={t('Map columns manually')}
          options={[
            { value: '', label: t('Map columns manually') },
            ...props.profiles.map((p) => ({ value: String(p.id), label: p.name })),
          ]}
        />
      </Field>

      <Field label={t('Assign all rows to Production Line (optional)')}>
        <Dropdown
          value={props.targetLineId}
          onChange={(v) => props.setTargetLineId(v as string)}
          placeholder={t('Use line_code column from file')}
          options={[
            { value: '', label: t('Use line_code column from file') },
            ...props.lines.map((l) => ({ value: String(l.id), label: l.code ? `${l.name} (${l.code})` : l.name })),
          ]}
        />
      </Field>

      {props.uploadError ? <Text style={styles.error}>{props.uploadError.message}</Text> : null}

      <Button
        title={t('Upload & Configure Mapping')}
        onPress={props.onUpload}
        disabled={!props.file || props.uploading}
        loading={props.uploading}
        leftIcon={<FontAwesome name="upload" size={14} color="#fff" />}
      />

      <SystemFieldsReference t={t} />
    </View>
  );
}

// ── Step 2: Column mapping ───────────────────────────────────────────────────

function MappingStep(props: {
  t: (k: string) => string;
  uploaded: CsvUploadResult;
  headerMap: Record<string, string>;
  setHeaderMap: (m: Record<string, string>) => void;
  onAutoDetect: () => void;
  onClear: () => void;
  missingRequired: string[];
  canRun: boolean;
  running: boolean;
  runError: Error | null;
  onRun: () => void;
  onBack: () => void;
  saveProfile: boolean;
  setSaveProfile: (b: boolean) => void;
  profileName: string;
  setProfileName: (s: string) => void;
}) {
  const { t, uploaded, headerMap } = props;
  const fieldOptions = [
    { value: IGNORE, label: t('— Ignore —') },
    ...SYSTEM_FIELDS.map((f) => ({ value: f.key, label: f.required ? `${t(f.label)} *` : t(f.label) })),
  ];

  return (
    <View style={styles.card}>
      <View style={styles.mapHead}>
        <Text style={styles.cardTitle}>{t('Map Columns')}</Text>
        <View style={{ flex: 1 }} />
        <Button title={t('Auto-detect')} onPress={props.onAutoDetect} variant="ghost" size="sm" />
        <Button title={t('Clear all')} onPress={props.onClear} variant="ghost" size="sm" />
      </View>
      <Mono size={10} color={colors.faint}>{`${uploaded.filename} · ${uploaded.total_rows} ${t('rows').toUpperCase()}`}</Mono>

      {uploaded.headers.map((header, i) => (
        <View key={header} style={styles.mapRow}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text numberOfLines={1} style={styles.mapHeader}>{header}</Text>
            <Mono size={9} color={colors.faint} numberOfLines={1}>
              {uploaded.preview[0]?.[i] ?? ''}
            </Mono>
          </View>
          <View style={{ width: 180 }}>
            <Dropdown
              value={headerMap[header] ?? IGNORE}
              onChange={(v) => props.setHeaderMap({ ...headerMap, [header]: v as string })}
              options={fieldOptions}
            />
          </View>
        </View>
      ))}

      {props.missingRequired.length > 0 ? (
        <Text style={styles.error}>
          {t('Required fields not mapped')}: {props.missingRequired.join(', ')}
        </Text>
      ) : null}

      <View style={styles.saveRow}>
        <Switch value={props.saveProfile} onValueChange={props.setSaveProfile} />
        <Text style={styles.saveLabel}>{t('Save mapping profile')}</Text>
      </View>
      {props.saveProfile ? (
        <TextInput
          value={props.profileName}
          onChangeText={props.setProfileName}
          placeholder={t('Profile name')}
          placeholderTextColor={colors.faint}
          style={styles.input}
        />
      ) : null}

      {props.runError ? <Text style={styles.error}>{props.runError.message}</Text> : null}

      <View style={styles.mapActions}>
        <Button title={t('Back')} onPress={props.onBack} variant="ghost" />
        <View style={{ flex: 1 }} />
        <Button
          title={`${t('Run Import')} (${uploaded.total_rows})`}
          onPress={props.onRun}
          disabled={!props.canRun || props.running}
          loading={props.running}
        />
      </View>
    </View>
  );
}

function SystemFieldsReference({ t }: { t: (k: string) => string }) {
  const [open, setOpen] = useState(false);
  return (
    <View style={styles.reference}>
      <Text style={styles.refToggle} onPress={() => setOpen((o) => !o)}>
        {open ? '▾ ' : '▸ '}
        {t('Available system fields reference')}
      </Text>
      {open ? (
        <View style={{ gap: 4, marginTop: 8 }}>
          {SYSTEM_FIELDS.map((f) => (
            <View key={f.key} style={styles.refRow}>
              <Mono size={10} color={colors.accent}>{f.key}</Mono>
              <Text style={styles.refLabel}>{f.required ? `${t(f.label)} *` : t(f.label)}</Text>
            </View>
          ))}
        </View>
      ) : null}
    </View>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <View style={{ gap: 6 }}>
      <Mono size={9} color={colors.faint} letterSpacing={0.6}>{label.toUpperCase()}</Mono>
      {children}
    </View>
  );
}

// ── Recent import row (unchanged from the monitor) ───────────────────────────

const STATUS_META: Record<string, { label: string; color: string; bg: string }> = {
  processing: { label: 'PROCESSING', color: '#8a5a0e', bg: '#FAF0DD' },
  done: { label: 'DONE', color: '#1C9A55', bg: '#E6F4EA' },
  failed: { label: 'FAILED', color: '#c0392b', bg: '#FBEAE6' },
  pending: { label: 'PENDING', color: '#6F6C66', bg: '#F1EFEA' },
};

function statusKeyFor(status: string | undefined): string {
  switch ((status ?? '').toLowerCase()) {
    case 'completed':
      return 'done';
    case 'failed':
      return 'failed';
    case 'processing':
      return 'processing';
    default:
      return 'pending';
  }
}

function ImportRow({ item }: { item: CsvImport }) {
  const meta = STATUS_META[statusKeyFor(item.status)];
  const created = item.created_at ? parseISO(item.created_at) : null;
  const ago = created && isValid(created) ? formatDistanceToNowStrict(created, { addSuffix: false }) : '';
  const total = item.total_rows ?? 0;

  return (
    <View style={styles.importCard}>
      <FontAwesome name="file-text-o" size={16} color={colors.muted} />
      <View style={{ flex: 1, minWidth: 0 }}>
        <Mono size={12} color={colors.ink} numberOfLines={1}>{item.filename || `Import #${item.id}`}</Mono>
        <Mono size={9} color={colors.faint} letterSpacing={0.4}>
          {[total > 0 ? `${total} ROWS` : null, ago ? ago.toUpperCase() : null, created && isValid(created) ? format(created, 'd MMM').toUpperCase() : null]
            .filter(Boolean)
            .join(' · ')}
        </Mono>
      </View>
      <View style={[styles.statusPill, { backgroundColor: meta.bg }]}>
        <Mono size={9} color={meta.color} weight="700" letterSpacing={0.5}>{meta.label}</Mono>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, paddingBottom: 40, gap: 6, maxWidth: 760, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  sub: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular, marginBottom: 10 },
  card: { backgroundColor: colors.card, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, padding: 16, gap: 14 },
  cardTitle: { fontSize: 15, fontFamily: fonts.sans.native.semibold, color: colors.ink },
  hint: { fontSize: 11, color: colors.faint, fontFamily: fonts.sans.native.regular, marginTop: -8 },
  mapHead: { flexDirection: 'row', alignItems: 'center', gap: 4 },
  mapRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 6, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line2 },
  mapHeader: { fontSize: 13, fontFamily: fonts.sans.native.medium, color: colors.ink },
  saveRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  saveLabel: { fontSize: 13, color: colors.ink, fontFamily: fonts.sans.native.regular },
  input: { borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, paddingHorizontal: 12, paddingVertical: 10, fontSize: 14, color: colors.ink, backgroundColor: colors.bg },
  mapActions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
  reference: { borderTopWidth: 1, borderTopColor: colors.line2, paddingTop: 12 },
  refToggle: { fontSize: 12, color: colors.muted, fontFamily: fonts.sans.native.medium },
  refRow: { flexDirection: 'row', alignItems: 'center', gap: 10 },
  refLabel: { fontSize: 12, color: colors.muted, fontFamily: fonts.sans.native.regular },
  section: { fontSize: 11, color: colors.faint, fontFamily: fonts.mono.native.medium, letterSpacing: 0.8, marginTop: 22, marginBottom: 6 },
  empty: { fontSize: 13, color: colors.faint, fontFamily: fonts.sans.native.regular, paddingVertical: 8 },
  importCard: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, padding: 12, marginBottom: 8 },
  statusPill: { paddingVertical: 3, paddingHorizontal: 6, borderRadius: 4 },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
});
