import { Head, usePage } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { unitAliasFields, unitAliasInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function UnitAliasEdit() {
    const { unitAlias, units = [] } = usePage().props;

    return (
        <>
            <Head title={__('Edit Unit Alias')} />
            <ResourceForm
                title={__('Edit Unit Alias')}
                action={`/admin/unit-aliases/${unitAlias.id}`}
                method="put"
                fields={unitAliasFields(units)}
                initial={unitAliasInitial(unitAlias)}
                cancelHref="/admin/unit-aliases"
            />
        </>
    );
}

UnitAliasEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
