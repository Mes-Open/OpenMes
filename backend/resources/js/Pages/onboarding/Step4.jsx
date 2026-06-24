import { Head, Link, useForm } from '@inertiajs/react';
import OnboardingLayout from '../../layouts/OnboardingLayout';
import { __ } from '../../lib/i18n';

/**
 * Onboarding Step 4 — Create First Work Order.
 * POST /onboarding/step/4 → OnboardingController@storeStep4
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
            <Head title={__('Step 4 — Work Order')} />
            <h2 className="text-2xl font-bold text-gray-800 mb-2">{__('Create First Work Order')}</h2>
            <p className="text-gray-600 mb-6">
                {__('A work order represents a production batch to manufacture. Create your first one.')}
            </p>

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <div>
                        <label htmlFor="order_no" className="block text-sm font-medium text-gray-700 mb-1">
                            {__('Order Number')} <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="order_no"
                            type="text"
                            value={data.order_no}
                            onChange={(e) => setData('order_no', e.target.value)}
                            required
                            className={`w-full rounded-lg border px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition ${errors.order_no ? 'border-red-500' : 'border-gray-300'}`}
                        />
                        {errors.order_no && <p className="mt-1 text-sm text-red-600">{errors.order_no}</p>}
                    </div>

                    <div>
                        <label htmlFor="planned_qty" className="block text-sm font-medium text-gray-700 mb-1">
                            {__('Planned Quantity')} <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="planned_qty"
                            type="number"
                            value={data.planned_qty}
                            onChange={(e) => setData('planned_qty', e.target.value)}
                            required
                            step="0.01"
                            min="0.01"
                            className={`w-full rounded-lg border px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition ${errors.planned_qty ? 'border-red-500' : 'border-gray-300'}`}
                        />
                        {errors.planned_qty && <p className="mt-1 text-sm text-red-600">{errors.planned_qty}</p>}
                    </div>

                    <div>
                        <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                            {__('Description')}
                        </label>
                        <textarea
                            id="description"
                            rows={2}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder={__('Optional notes')}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                        />
                    </div>
                </div>

                <div className="flex justify-between mt-6">
                    <Link href="/onboarding/step/3" className="btn-touch btn-secondary">
                        ← Back
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-touch btn-primary disabled:opacity-50"
                    >
                        {processing ? __('Saving…') : __('Complete Setup')}
                    </button>
                </div>
            </form>
        </>
    );
}

Step4.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
