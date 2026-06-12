import { Head, Link, useForm } from '@inertiajs/react';
import { Button, TextField } from '@openmes/ui';
import OnboardingLayout from '../../layouts/OnboardingLayout';

/**
 * Onboarding Step 4 — Create First Work Order.
 * POST /onboarding/step/4 → OnboardingController@storeStep4
 *
 * Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
 */

const currentYear = new Date().getFullYear();
const DEFAULT_ORDER_NO = `WO-${currentYear}-001`;

export default function Step4() {
    const form = useForm({ order_no: DEFAULT_ORDER_NO, planned_qty: '100', description: '' });
    const { data, setData, post, processing, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        post('/onboarding/step/4');
    };

    return (
        <>
            <Head title="Step 4 — Work Order" />
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2">Step 4/4</div>
            <h2 className="text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2">Create First Work Order</h2>
            <p className="text-sm text-om-muted mb-6">
                A work order represents a production batch to manufacture. Create your first one.
            </p>

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <TextField
                        label={<>Order Number <span className="text-om-accent">*</span></>}
                        id="order_no"
                        mono
                        value={data.order_no}
                        onChange={(v) => setData('order_no', v)}
                        error={errors.order_no}
                        required
                    />

                    <TextField
                        label={<>Planned Quantity <span className="text-om-accent">*</span></>}
                        id="planned_qty"
                        type="number"
                        value={data.planned_qty}
                        onChange={(v) => setData('planned_qty', v)}
                        error={errors.planned_qty}
                        required
                        step="0.01"
                        min="0.01"
                    />

                    <TextField
                        label="Description"
                        id="description"
                        multiline
                        rows={2}
                        value={data.description}
                        onChange={(v) => setData('description', v)}
                        placeholder="Optional notes"
                    />
                </div>

                <div className="flex items-center justify-between mt-6">
                    <Link href="/onboarding/step/3" className="text-om-muted hover:text-om-ink text-[13px] transition-colors">
                        ← Back
                    </Link>
                    <Button type="submit" variant="accent" loading={processing}>
                        {processing ? 'Saving…' : 'Complete Setup'}
                    </Button>
                </div>
            </form>
        </>
    );
}

Step4.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
