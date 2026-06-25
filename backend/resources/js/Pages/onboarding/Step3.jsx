import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import OnboardingLayout from '../../layouts/OnboardingLayout';
import { __ } from '../../lib/i18n';

/**
 * Onboarding Step 3 — Define Process Template.
 * POST /onboarding/step/3 → OnboardingController@storeStep3
 *
 * Dynamic step list with add/remove and drag-to-reorder, replacing Alpine.js x-data.
 */

function DragHandle() {
    return (
        <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M7 2a2 2 0 10.001 4.001A2 2 0 007 2zm0 6a2 2 0 10.001 4.001A2 2 0 007 8zm0 6a2 2 0 10.001 4.001A2 2 0 007 14zm6-8a2 2 0 10-.001-4.001A2 2 0 0013 6zm0 2a2 2 0 10.001 4.001A2 2 0 0013 8zm0 6a2 2 0 10.001 4.001A2 2 0 0013 14z" />
        </svg>
    );
}

let _uid = 0;
const uid = () => ++_uid;

export default function Step3() {
    const [name, setName] = useState('');
    const [steps, setSteps] = useState([{ id: uid(), name: '', estimated_duration_minutes: '' }]);
    const [dragIndex, setDragIndex] = useState(null);
    const [dragOverIndex, setDragOverIndex] = useState(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const addStep = () =>
        setSteps((prev) => [...prev, { id: uid(), name: '', estimated_duration_minutes: '' }]);

    const removeStep = (i) =>
        setSteps((prev) => prev.length > 1 ? prev.filter((_, idx) => idx !== i) : prev);

    const updateStep = (i, field, value) =>
        setSteps((prev) => prev.map((s, idx) => (idx === i ? { ...s, [field]: value } : s)));

    const handleDragStart = (i) => setDragIndex(i);
    const handleDragOver = (e, i) => { e.preventDefault(); setDragOverIndex(i); };
    const handleDrop = (e, i) => {
        e.preventDefault();
        if (dragIndex === null || dragIndex === i) { setDragIndex(null); setDragOverIndex(null); return; }
        setSteps((prev) => {
            const next = [...prev];
            const [moved] = next.splice(dragIndex, 1);
            next.splice(i, 0, moved);
            return next;
        });
        setDragIndex(null);
        setDragOverIndex(null);
    };
    const handleDragEnd = () => { setDragIndex(null); setDragOverIndex(null); };

    const submit = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(
            '/onboarding/step/3',
            { name, steps: steps.map(({ name: n, estimated_duration_minutes: d }) => ({ name: n, estimated_duration_minutes: d })) },
            {
                onError: (errs) => { setErrors(errs); setProcessing(false); },
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <>
            <Head title={__('Step 3 — Process Template')} />
            <h2 className="text-2xl font-bold text-gray-800 mb-2">{__('Define Process Template')}</h2>
            <p className="text-gray-600 mb-6">
                {__('A process template defines the production steps (recipe) for your product. Add each step in the order they happen during production.')}
            </p>

            <form onSubmit={submit}>
                <div className="space-y-5">
                    <div>
                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                            {__('Template Name')} <span className="text-red-500">*</span>
                        </label>
                        <input
                            id="name"
                            type="text"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            required
                            placeholder={__('e.g. Filter Assembly Process')}
                            className={`w-full rounded-lg border px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition ${errors.name ? 'border-red-500' : 'border-gray-300'}`}
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            {__('Production Steps')} <span className="text-red-500">*</span>
                        </label>
                        <p className="text-xs text-gray-500 mb-3">{__('Add steps in order. Drag the handle to reorder.')}</p>
                        {errors.steps && <p className="mb-2 text-sm text-red-600">{errors.steps}</p>}

                        {/* Column headers */}
                        <div className="flex gap-2 mb-2 text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <span className="w-10" />
                            <span className="flex-1">{__('Step Name')}</span>
                            <span className="w-28 text-center">{__('Duration (min)')}</span>
                            <span className="w-8" />
                        </div>

                        {steps.map((step, i) => (
                            <div
                                key={step.id}
                                className={`flex gap-2 mb-2 items-center rounded-lg transition-all
                                    ${dragOverIndex === i && dragIndex !== i ? 'bg-blue-50 border border-blue-200 border-dashed' : ''}
                                    ${dragIndex === i ? 'opacity-50' : ''}`}
                                draggable
                                onDragStart={() => handleDragStart(i)}
                                onDragOver={(e) => handleDragOver(e, i)}
                                onDrop={(e) => handleDrop(e, i)}
                                onDragEnd={handleDragEnd}
                            >
                                {/* Drag handle */}
                                <span
                                    className="flex items-center justify-center w-10 cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 select-none"
                                    title={__('Drag to reorder')}
                                >
                                    <DragHandle />
                                </span>
                                <input
                                    type="text"
                                    value={step.name}
                                    onChange={(e) => updateStep(i, 'name', e.target.value)}
                                    required
                                    placeholder={__('Step :num (e.g. Assembly, Packaging...)', { num: i + 1 })}
                                    className="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition"
                                />
                                <input
                                    type="number"
                                    value={step.estimated_duration_minutes}
                                    onChange={(e) => updateStep(i, 'estimated_duration_minutes', e.target.value)}
                                    placeholder={__('min')}
                                    min="0"
                                    className="w-28 rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition text-center"
                                />
                                {steps.length > 1 ? (
                                    <button
                                        type="button"
                                        onClick={() => removeStep(i)}
                                        className="w-8 text-red-400 hover:text-red-600 text-lg leading-none"
                                    >
                                        &times;
                                    </button>
                                ) : (
                                    <span className="w-8" />
                                )}
                            </div>
                        ))}

                        <button
                            type="button"
                            onClick={addStep}
                            className="text-sm text-blue-600 hover:text-blue-800 mt-2 font-medium"
                        >
                            {__('+ Add another step')}
                        </button>
                    </div>
                </div>

                <div className="flex justify-between mt-6">
                    <Link href="/onboarding/step/2" className="btn-touch btn-secondary">
                        ← Back
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        className="btn-touch btn-primary disabled:opacity-50"
                    >
                        {processing ? __('Saving…') : __('Next: Work Order →')}
                    </button>
                </div>
            </form>
        </>
    );
}

Step3.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
