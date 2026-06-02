import { Head, Link, useForm } from '@inertiajs/react';
import OnboardingLayout from '../../layouts/OnboardingLayout';

/**
 * Onboarding Step 2 — Add a Product Type.
 * POST /onboarding/step/2 → OnboardingController@storeStep2
 */
export default function Step2() {
    const form = useForm({ name: '', code: '', unit_of_measure: 'pcs' });
    const { data, setData, post, processing, errors } = form;

    const submit = (e) => {
        e.preventDefault();
        post('/onboarding/step/2');
    };

    return (
        <>
            <Head title="Step 2 — Product Type" />
            <h2 className="text-2xl font-bold text-gray-800 mb-2">Add a Product Type</h2>
            <p className="text-gray-600 mb-6">What product does this line produce? Define the product type.</p>

            <form onSubmit={submit}>
                <div className="space-y-4">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                            Product Name <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder="e.g. Air Filter"
                            className={`w-full rounded-lg border px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition ${errors.name ? 'border-red-500' : 'border-gray-300'}`}
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="code" className="block text-sm font-medium text-gray-700 mb-1">
                            Code <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="code"
                            type="text"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            required
                            placeholder="e.g. FILTER"
                            className={`w-full rounded-lg border px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition ${errors.code ? 'border-red-500' : 'border-gray-300'}`}
                        />
                        {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                    </div>

                    <div>
                        <label htmlFor="unit_of_measure" className="block text-sm font-medium text-gray-700 mb-1">
                            Unit of Measure
                        </label>
                        <input
                            id="unit_of_measure"
                            type="text"
                            value={data.unit_of_measure}
                            onChange={(e) => setData('unit_of_measure', e.target.value)}
                            placeholder="pcs, kg, m..."
                            className="w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                        />
                    </div>
                </div>

                <div className="flex justify-between mt-6">
                    <Link href="/onboarding/step/1" className="btn-touch btn-secondary">
                        ← Back
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-touch btn-primary disabled:opacity-50"
                    >
                        {processing ? 'Saving…' : 'Next: Process Template →'}
                    </button>
                </div>
            </form>
        </>
    );
}

Step2.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
