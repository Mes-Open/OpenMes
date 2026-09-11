import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import SerialSequenceForm, { serialSequenceInitial } from './SerialSequenceForm';
import { __ } from '../../../lib/i18n';

export default function SerialSequenceCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New Serial Sequence')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('New Serial Sequence')}</h1>
            <SerialSequenceForm
                action="/admin/serial-sequences"
                method="post"
                initial={serialSequenceInitial(null)}
                submitLabel={__('Create')}
            />
        </div>
    );
}

SerialSequenceCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
