// First run. One screen, one decision: install an example company, or start
// empty. Both are on screen together — a first-run screen that only offers
// "yes" is a wall rather than a choice.
import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Button } from '@openmes/ui';
import { __ } from '../../lib/i18n';

export default function Welcome() {
    const { datasets = [], csrf_token } = usePage().props;
    const [dataset, setDataset] = useState(datasets[0]?.key ?? null);

    return (
        <div className="min-h-screen bg-om-bg flex items-start justify-center py-14 px-4">
            <Head title={__('Welcome to OpenMES')} />

            <div className="w-full max-w-3xl">
                <header className="mb-8">
                    <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink">
                        {__('Welcome to OpenMES')}
                    </h1>
                    <p className="text-om-muted text-[13.5px] mt-2 max-w-2xl">
                        {__('The system is empty. Install an example company to have something to look at — lines, products, routings, orders and shift history — or start empty and build your own.')}
                    </p>
                </header>

                <form method="POST" action="/onboarding">
                    <input type="hidden" name="_token" value={csrf_token} />

                    <div className="grid gap-3 sm:grid-cols-2 mb-6">
                        {datasets.map((set) => (
                            <label
                                key={set.key}
                                className={`flex gap-3 p-4 rounded-om border cursor-pointer transition-colors
                                            ${dataset === set.key
                                                ? 'border-om-accent bg-om-chip'
                                                : 'border-om-line hover:bg-om-chip'}`}
                            >
                                <input
                                    type="radio"
                                    name="dataset"
                                    value={set.key}
                                    checked={dataset === set.key}
                                    onChange={() => setDataset(set.key)}
                                    className="mt-1 shrink-0"
                                />
                                <span className="min-w-0">
                                    <span className="block text-[13px] font-medium text-om-ink">{__(set.label)}</span>
                                    <span className="block text-[11px] uppercase tracking-wide text-om-faint mt-0.5">{__(set.industry)}</span>
                                    <span className="block text-om-muted text-[12.5px] mt-1.5">{__(set.description)}</span>
                                </span>
                            </label>
                        ))}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button type="submit" variant="accent" disabled={! dataset}>
                            {__('Install this example company')}
                        </Button>
                        <span className="text-om-faint text-[12.5px]">
                            {__('Loading takes up to a minute.')}
                        </span>
                    </div>
                </form>

                {/* The way out, given the same weight as the offer rather than
                    hidden as a link in the corner. */}
                <div className="mt-8 pt-6 border-t border-om-line">
                    <form method="POST" action="/onboarding/skip">
                        <input type="hidden" name="_token" value={csrf_token} />
                        <Button type="submit" variant="secondary">
                            {__('Start with an empty system')}
                        </Button>
                        <p className="text-om-muted text-[12.5px] mt-2">
                            {__('Nothing is installed and you go straight to the dashboard. You can load an example company at any time from Settings → Data.')}
                        </p>
                    </form>
                </div>
            </div>
        </div>
    );
}
