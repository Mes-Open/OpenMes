import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { skillFields, skillInitial } from './fields';
import { __ } from '../../../lib/i18n';

export default function SkillEdit({ skill }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={__('Edit :name', { name: skill.name })} />
            <h1 className="text-3xl font-bold text-om-ink mb-6">{__('Edit Skill')}</h1>
            <ResourceForm
                action={`/admin/skills/${skill.id}`}
                method="put"
                fields={skillFields()}
                initial={skillInitial(skill)}
                submitLabel={__('Save Changes')}
                cancelHref="/admin/skills"
            />
        </div>
    );
}

SkillEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
