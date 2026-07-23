import { Head, useForm } from '@inertiajs/react';
import { Button, TextField } from '@openmes/ui';
import OnboardingLayout from '../../layouts/OnboardingLayout';
import { __ } from '../../lib/i18n';

/**
 * Onboarding Step 1 — Create a Production Line.
 * POST /onboarding/step/1 → OnboardingController@storeStep1
 *
 * Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
 */
export default function Step1() {
    const form = useForm({ name: '', code: '', description: '' });
    const { data, setData, post, processing, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        post('/onboarding/step/1');
    };

    return (
        <>
            <Head title={__('Step 1 — Production Line')} />
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2">Step 2/5</div>
            <h2 className="text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2">{__('Create a Production Line')}</h2>
            <p className="text-sm text-om-muted mb-6">
                {__('A production line is a physical area where manufacturing happens. Start by creating your first one.')}
            </p>

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <TextField
                        label={<>{__('Line Name')} <span className="text-om-accent">*</span></>}
                        id="name"
                        value={data.name}
                        onChange={(v) => setData('name', v)}
                        error={errors.name}
                        required
                        placeholder={__('e.g. Injection Line 1')}
                    />

                    <TextField
                        label={<>{__('Code')} <span className="text-om-accent">*</span></>}
                        id="code"
                        value={data.code}
                        onChange={(v) => setData('code', v)}
                        error={errors.code}
                        required
                        placeholder={__('e.g. INJ-01')}
                    />

                    <TextField
                        label={__('Description')}
                        id="description"
                        multiline
                        rows={2}
                        value={data.description}
                        onChange={(v) => setData('description', v)}
                        placeholder={__('Optional description')}
                    />
                </div>

                <div className="flex justify-end mt-6">
                    <Button type="submit" variant="accent" loading={processing}>
                        {processing ? __('Saving…') : __('Next: Product Type →')}
                    </Button>
                </div>
            </form>
        </>
    );
}

Step1.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
