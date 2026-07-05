/**
 * Webhook create/edit form — a 1:1 port of the web admin webhook form: name,
 * URL, the subscribed-events checklist (from the event-type catalog), an active
 * toggle and an optional signing secret (write-only; left blank keeps the
 * existing one). Used by both the "new" and "[id]/edit" routes.
 */
import { useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useRouter } from 'expo-router';
import { FontAwesome } from '@expo/vector-icons';
import { useTranslation } from 'react-i18next';

import { colors, fonts, radius } from '@openmes/ui';

import { Button } from '@/components/ui/Button';
import { Field } from '@/components/ui/Field';
import { Mono } from '@/components/ui/Mono';
import { Switch } from '@/components/ui/Switch';
import { LoadingState } from '@/components/ui/StateViews';
import {
  useCreateWebhook,
  useUpdateWebhook,
  useWebhookEventTypes,
  useWebhooks,
} from '@/hooks/queries/useWebhooks';

export function WebhookFormScreen({ id }: { id?: number }) {
  const { t } = useTranslation();
  const router = useRouter();
  const isEdit = typeof id === 'number';

  const list = useWebhooks();
  const existing = isEdit ? list.data?.find((w) => w.id === id) : undefined;
  const eventTypes = useWebhookEventTypes();
  const create = useCreateWebhook();
  const update = useUpdateWebhook();

  const [name, setName] = useState(existing?.name ?? '');
  const [url, setUrl] = useState(existing?.url ?? '');
  const [events, setEvents] = useState<string[]>(existing?.events ?? []);
  const [isActive, setIsActive] = useState(existing?.is_active ?? true);
  const [secret, setSecret] = useState('');
  const [seeded, setSeeded] = useState(!isEdit);

  // Seed once the row arrives (edit deep-link without a warm list cache).
  if (isEdit && !seeded && existing) {
    setName(existing.name);
    setUrl(existing.url);
    setEvents(existing.events ?? []);
    setIsActive(existing.is_active);
    setSeeded(true);
  }

  const toggleEvent = (key: string) =>
    setEvents((cur) => (cur.includes(key) ? cur.filter((e) => e !== key) : [...cur, key]));

  const errors = useMemo(() => {
    const e: Record<string, string> = {};
    if (!name.trim()) e.name = 'Required';
    if (!url.trim()) e.url = 'Required';
    if (events.length === 0) e.events = 'Select at least one event';
    return e;
  }, [name, url, events]);

  const pending = create.isPending || update.isPending;

  const onSave = () => {
    if (Object.keys(errors).length > 0) return;
    const input = {
      name: name.trim(),
      url: url.trim(),
      events,
      is_active: isActive,
      ...(secret.trim() ? { secret: secret.trim() } : {}),
    };
    const onError = (err: Error) => Alert.alert(t('Could not save'), err.message);
    if (isEdit) {
      update.mutate({ id: id as number, input }, { onSuccess: () => router.back(), onError });
    } else {
      create.mutate(input, { onSuccess: () => router.back(), onError });
    }
  };

  if (isEdit && list.isLoading && !existing) return <LoadingState />;

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.h1}>{isEdit ? t('Edit webhook') : t('New webhook')}</Text>

      <Field label="Name" value={name} onChangeText={setName} error={errors.name} required placeholder="My integration" />
      <Field
        label="URL"
        value={url}
        onChangeText={setUrl}
        error={errors.url}
        required
        autoCapitalize="none"
        keyboardType="url"
        placeholder="https://example.com/hook"
        mono
      />

      <View style={{ gap: 8 }}>
        <Mono size={9} color={colors.faint} letterSpacing={0.6}>{t('Events').toUpperCase()} *</Mono>
        {eventTypes.isLoading ? (
          <Text style={styles.muted}>{t('Loading…')}</Text>
        ) : (
          (eventTypes.data ?? []).map((ev) => {
            const checked = events.includes(ev.key);
            return (
              <Pressable key={ev.key} onPress={() => toggleEvent(ev.key)} style={styles.checkRow}>
                <View style={[styles.checkbox, checked && styles.checkboxOn]}>
                  {checked ? <FontAwesome name="check" size={11} color="#fff" /> : null}
                </View>
                <View style={{ flex: 1 }}>
                  <Text style={styles.checkLabel}>{ev.label}</Text>
                  <Mono size={9} color={colors.faint}>{ev.key}</Mono>
                </View>
              </Pressable>
            );
          })
        )}
        {errors.events ? <Text style={styles.error}>{t(errors.events)}</Text> : null}
      </View>

      <View style={styles.activeRow}>
        <View style={{ flex: 1 }}>
          <Text style={styles.checkLabel}>{t('Active')}</Text>
          <Mono size={9} color={colors.faint}>{t('Deliver events to this endpoint')}</Mono>
        </View>
        <Switch value={isActive} onValueChange={setIsActive} />
      </View>

      <Field
        label="Signing secret"
        value={secret}
        onChangeText={setSecret}
        autoCapitalize="none"
        mono
        hint={isEdit ? 'Leave blank to keep the current secret' : 'Leave blank to auto-generate'}
        placeholder="••••••••"
      />

      <View style={styles.actions}>
        <Button title={t('Cancel')} variant="ghost" onPress={() => router.back()} />
        <View style={{ flex: 1 }} />
        <Button title={t('Save')} onPress={onSave} loading={pending} disabled={pending} />
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.bg },
  content: { padding: 18, gap: 16, maxWidth: 640, width: '100%', alignSelf: 'center' },
  h1: { fontSize: 22, fontFamily: fonts.sans.native.semibold, color: colors.ink, letterSpacing: -0.4 },
  muted: { fontSize: 13, color: colors.muted, fontFamily: fonts.sans.native.regular },
  checkRow: { flexDirection: 'row', alignItems: 'center', gap: 12, paddingVertical: 8 },
  checkbox: { width: 22, height: 22, borderRadius: 6, borderWidth: 1.5, borderColor: colors.line, alignItems: 'center', justifyContent: 'center' },
  checkboxOn: { backgroundColor: colors.accent, borderColor: colors.accent },
  checkLabel: { fontSize: 14, fontFamily: fonts.sans.native.medium, color: colors.ink },
  activeRow: { flexDirection: 'row', alignItems: 'center', gap: 12, backgroundColor: colors.card, borderWidth: 1, borderColor: colors.line, borderRadius: radius.md, padding: 14 },
  error: { fontSize: 12, color: colors.blocked, fontFamily: fonts.sans.native.regular },
  actions: { flexDirection: 'row', alignItems: 'center', gap: 10, marginTop: 4 },
});
