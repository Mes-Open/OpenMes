import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ShipmentForm from './ShipmentForm';
import { __ } from '../../../lib/i18n';

export default function ShipmentCreate({ basePath }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('New shipment')} />
            <h1 className="text-[26px] font-semibold tracking-[-0.02em] text-om-ink mb-6">{__('New shipment')}</h1>
            <ShipmentForm action={basePath} method="post" initial={{}} submitLabel={__('Create shipment')} cancelHref={basePath} />
        </div>
    );
}

ShipmentCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
