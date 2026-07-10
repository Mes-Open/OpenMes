import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { customerFields } from './fields';
import { __ } from '../../../lib/i18n';

export default function CustomerEdit({ customer }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit Customer')} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Customer')}</h1>
            <ResourceForm
                action={`/admin/customers/${customer.id}`}
                method="put"
                fields={customerFields()}
                initial={{
                    name: customer.name ?? '',
                    code: customer.code ?? '',
                    tier: customer.tier ?? 'bronze',
                    payment_score: customer.payment_score ?? 0,
                    notes: customer.notes ?? '',
                    is_active: !!customer.is_active,
                }}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/customers"
            />
        </div>
    );
}

CustomerEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
