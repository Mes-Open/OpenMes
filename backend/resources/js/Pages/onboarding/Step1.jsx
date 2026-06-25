import { Head, useForm } from '@inertiajs/react';
import OnboardingLayout from '../../layouts/OnboardingLayout';
import { __ } from '../../lib/i18n';

/**
 * Onboarding Step 1 — Create a Production Line.
 * POST /onboarding/step/1 → OnboardingController@storeStep1
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
            <h2 className="text-2xl font-bold text-gray-800 mb-2">{__('Create a Production Line')}</h2>
            <p className="text-gray-600 mb-6">
                {__('A production line is a physical area where manufacturing happens. Start by creating your first one.')}
            </p>

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                            {__('Line Name')} <span className="text-red-500">*</span>
                        </label>
                        <input
                             id="name"
                             type="text"
                             value={data.name}
                             onChange={(e) => setData('name', e.target.value)}
                             required
                             placeholder={__('e.g. Injection Line 1')}
                             className={`w-full rounded-lg border px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition ${errors.name ? 'border-red-500' : 'border-gray-300'}`}
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="code" className="block text-sm font-medium text-gray-700 mb-1">
                            {__('Code')} <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="code"
                            type="text"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            required
                            placeholder={__('e.g. INJ-01')}
                            className={`w-full rounded-lg border px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition ${errors.code ? 'border-red-500' : 'border-gray-300'}`}
                        />
                        {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
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
                            placeholder={__('Optional description')}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                        />
                    </div>
                </div>

                <div className="flex justify-end mt-6">
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-touch btn-primary disabled:opacity-50"
                    >
                        {processing ? __('Saving…') : __('Next: Product Type →')}
                    </button>
                </div>
            </form>
        </>
    );
}

Step1.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
