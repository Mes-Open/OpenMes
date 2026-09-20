import { Head, Link, usePage } from '@inertiajs/react';
import { Icon } from '@openmes/ui';
import WorkstationForm from './WorkstationForm';
import AppLayout from '../../../layouts/AppLayout';
import { __ } from '../../../lib/i18n';

export default function WorkstationCreate() {
    const { line, customFields = [] } = usePage().props;
    return (
        <div className="max-w-2xl mx-auto">
            <Head title={__('Create Workstation')} />

            <div className="mb-6">
                <Link
                    href={`/admin/lines/${line.id}/workstations`}
                    className="text-om-accent hover:text-om-accent flex items-center gap-2 mb-4 text-sm"
                >
                    <Icon name="arrow-left" size={16} />
                    {__('Back to Workstations')}
                </Link>
                <h1 className="text-3xl font-bold text-om-ink">{__('Create Workstation')}</h1>
                <p className="text-sm text-om-muted mt-1">{line.name}</p>
            </div>

            <WorkstationForm line={line} customFields={customFields} />
        </div>
    );
}

WorkstationCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
