import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import LotSequenceForm, { lotSequenceInitial } from './LotSequenceForm';
import { __ } from '../../../lib/i18n';

export default function LotSequenceCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New LOT Sequence')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New LOT Sequence')}</h1>
            <LotSequenceForm
                action="/admin/lot-sequences"
                method="post"
                initial={lotSequenceInitial(null)}
                submitLabel={__('Create')}
            />
        </div>
    );
}

LotSequenceCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
