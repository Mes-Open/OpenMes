import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Button, TextField } from '@openmes/ui';
import OnboardingLayout from '../../layouts/OnboardingLayout';

/**
 * Onboarding Step 3 — Define Process Template.
 * POST /onboarding/step/3 → OnboardingController@storeStep3
 *
 * Dynamic step list with add/remove and drag-to-reorder, replacing Alpine.js x-data.
 *
 * Geist White restyle: light-only v1 — om-* tokens, @openmes/ui controls.
 */

// Geist White input idiom for the inline step-row fields.
const ROW_INPUT_CLASS =
    'bg-om-bg border border-om-line rounded-om-sm px-3 py-2.5 text-[13px] text-om-ink outline-none placeholder:text-om-faint focus:border-om-accent focus:ring-[3px] focus:ring-[rgba(234,90,43,.12)] transition-colors';

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
            <Head title="Step 3 — Process Template" />
            <div className="font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-2">Step 3/4</div>
            <h2 className="text-xl font-semibold tracking-[-0.02em] text-om-ink mb-2">Define Process Template</h2>
            <p className="text-sm text-om-muted mb-6">
                A process template defines the production steps (recipe) for your product. Add each step in the order
                they happen during production.
            </p>

            <form onSubmit={submit}>
                <div className="space-y-5">
                    <TextField
                        label={<>Template Name <span className="text-om-accent">*</span></>}
                        id="name"
                        value={name}
                        onChange={setName}
                        error={errors.name}
                        required
                        placeholder="e.g. Filter Assembly Process"
                    />

                    <div>
                        <label className="block font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint mb-[7px]">
                            Production Steps <span className="text-om-accent">*</span>
                        </label>
                        <p className="text-[12.5px] text-om-muted mb-3">Add steps in order. Drag the handle to reorder.</p>
                        {errors.steps && <p className="mb-2 text-[11.5px] text-om-blocked">{errors.steps}</p>}

                        {/* Column headers */}
                        <div className="flex gap-2 mb-2 font-mono text-[9.5px] uppercase tracking-[0.08em] text-om-faint">
                            <span className="w-10" />
                            <span className="flex-1">Step Name</span>
                            <span className="w-28 text-center">Duration (min)</span>
                            <span className="w-8" />
                        </div>

                        {steps.map((step, i) => (
                            <div
                                key={step.id}
                                className={`flex gap-2 mb-2 items-center rounded-om-sm transition-all
                                    ${dragOverIndex === i && dragIndex !== i ? 'bg-om-selected border border-om-accent/40 border-dashed' : ''}
                                    ${dragIndex === i ? 'opacity-50' : ''}`}
                                draggable
                                onDragStart={() => handleDragStart(i)}
                                onDragOver={(e) => handleDragOver(e, i)}
                                onDrop={(e) => handleDrop(e, i)}
                                onDragEnd={handleDragEnd}
                            >
                                {/* Drag handle */}
                                <span
                                    className="flex items-center justify-center w-10 cursor-grab active:cursor-grabbing text-om-faintest hover:text-om-muted select-none"
                                    title="Drag to reorder"
                                >
                                    <DragHandle />
                                </span>
                                <input
                                    type="text"
                                    value={step.name}
                                    onChange={(e) => updateStep(i, 'name', e.target.value)}
                                    required
                                    placeholder={`Step ${i + 1} (e.g. Assembly, Packaging...)`}
                                    className={`flex-1 ${ROW_INPUT_CLASS}`}
                                />
                                <input
                                    type="number"
                                    value={step.estimated_duration_minutes}
                                    onChange={(e) => updateStep(i, 'estimated_duration_minutes', e.target.value)}
                                    placeholder="min"
                                    min="0"
                                    className={`w-28 text-center ${ROW_INPUT_CLASS}`}
                                />
                                {steps.length > 1 ? (
                                    <button
                                        type="button"
                                        onClick={() => removeStep(i)}
                                        className="w-8 text-om-blocked/60 hover:text-om-blocked text-lg leading-none"
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
                            className="text-[13px] text-om-accent hover:underline mt-2 font-medium"
                        >
                            + Add another step
                        </button>
                    </div>
                </div>

                <div className="flex items-center justify-between mt-6">
                    <Link href="/onboarding/step/2" className="text-om-muted hover:text-om-ink text-[13px] transition-colors">
                        ← Back
                    </Link>
                    <Button type="submit" variant="accent" loading={processing}>
                        {processing ? 'Saving…' : 'Next: Work Order →'}
                    </Button>
                </div>
            </form>
        </>
    );
}

Step3.layout = (page) => <OnboardingLayout>{page}</OnboardingLayout>;
