import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Checkbox } from '@openmes/ui';
import OnboardingLayout from '../../layouts/OnboardingLayout';
import { __ } from '../../lib/i18n';

/**
 * Onboarding Step 1 — choose which optional feature modules the MES exposes.
 * POST /onboarding/modules → OnboardingController@storeModules.
 *
 * Three one-click presets (Lightweight / Advanced / Custom) over the existing
 * ModuleRegistry `enabled_modules` mechanism. Everything here is changeable
 * later in Settings → System → Modules; core areas (Dashboard, Orders,
 * Production, Admin) are always on and not listed.
 */
export default function OnboardingModules() {
    const { modules = [], presets = {} } = usePage().props;

    const labelFor = (key) => modules.find((m) => m.key === key)?.label ?? key;
    const advancedSet = presets.advanced ?? [];
    const lightSet = presets.light ?? [];

    const form = useForm({ preset: 'light', enabled_modules: advancedSet });
    const { data, setData, post, processing } = form;

    const choosePreset = (preset) => {
        // Seed the custom checklist from the Advanced set the first time the
        // admin opens it, so Custom starts from a sensible selection.
        setData((prev) => ({ ...prev, preset }));
    };

    const toggleModule = (key, on) => {
        setData('enabled_modules', on
            ? [...data.enabled_modules, key]
            : data.enabled_modules.filter((k) => k !== key));
    };

    const submit = (e) => {
        e.preventDefault();
        post('/onboarding/modules');
    };

    const PresetCard = ({ value, title, summary, children }) => {
        const selected = data.preset === value;
        return (
            <button
                type="button"
                onClick={() => choosePreset(value)}
                className={`w-full text-left border rounded-om p-4 transition-colors ${
                    selected ? 'border-om-accent bg-om-selected' : 'border-om-line hover:border-om-faintest'
                }`}
            >
                <div className="flex items-start gap-3">
                    <span className={`mt-0.5 w-4 h-4 rounded-full flex-shrink-0 border-2 ${
                        selected ? 'border-om-accent bg-om-accent' : 'border-om-line'
                    }`} />
                    <div className="flex-1">
                        <div className="text-[15px] font-semibold text-om-ink">{title}</div>
                        <div className="text-[12.5px] text-om-muted mt-0.5">{summary}</div>
                        {children}
                    </div>
                </div>
            </button>
        );
    };

    return (
        <>
            <Head title={__('Step 1 — Modules')} />
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2">Step 1/5</div>
            <h2 className="text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2">{__('Choose your modules')}</h2>
            <p className="text-sm text-om-muted mb-6">
                {__('Turn on only the feature areas your team needs. Core areas (Dashboard, Orders, Production, Admin) are always on. You can change this anytime in Settings → System → Modules.')}
            </p>

            <form onSubmit={submit}>
                <div className="space-y-3">
                    <PresetCard
                        value="light"
                        title={__('Lightweight')}
                        summary={__('Core production tracking + Reports. The simplest setup — recommended for small shops.')}
                    >
                        <div className="text-[11.5px] text-om-faint mt-1.5">
                            {__('Adds:')} {lightSet.map(labelFor).map((l) => __(l)).join(', ')}
                        </div>
                    </PresetCard>

                    <PresetCard
                        value="advanced"
                        title={__('Advanced')}
                        summary={__('Full shop-floor operations: reports, quality & maintenance, machine connectivity and packaging.')}
                    >
                        <div className="text-[11.5px] text-om-faint mt-1.5">
                            {__('Adds:')} {advancedSet.map(labelFor).map((l) => __(l)).join(', ')}
                        </div>
                    </PresetCard>

                    <PresetCard
                        value="custom"
                        title={__('Custom')}
                        summary={__('Pick exactly which modules to enable.')}
                    />
                </div>

                {data.preset === 'custom' && (
                    <div className="mt-4 space-y-2 border-t border-om-line2 pt-4">
                        <p className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-1">{__('Modules')}</p>
                        {modules.map((m) => (
                            <div key={m.key} className="flex items-start gap-3 border border-om-line rounded-om-sm p-3">
                                <Checkbox
                                    checked={data.enabled_modules.includes(m.key)}
                                    onChange={(next) => toggleModule(m.key, next)}
                                    label={__(m.label)}
                                />
                                <span className="text-[12.5px] text-om-muted">{__(m.description)}</span>
                            </div>
                        ))}
                    </div>
                )}

                <div className="flex justify-end mt-6">
                    <Button type="submit" variant="accent" loading={processing}>
                        {processing ? __('Saving…') : __('Next: Production Line →')}
                    </Button>
                </div>
            </form>
        </>
    );
}

OnboardingModules.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
