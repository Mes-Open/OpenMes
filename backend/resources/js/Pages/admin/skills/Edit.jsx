import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { SKILL_FIELDS } from './fields';

export default function SkillEdit({ skill }) {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title={`Edit ${skill.name}`} />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">Edit Skill</h1>
            <ResourceForm
                action={`/admin/skills/${skill.id}`}
                method="put"
                fields={SKILL_FIELDS}
                initial={{
                    code: skill.code ?? '',
                    name: skill.name ?? '',
                    description: skill.description ?? '',
                }}
                submitLabel="Save Changes"
                cancelHref="/admin/skills"
            />
        </div>
    );
}

SkillEdit.layout = (page) => <AppLayout>{page}</AppLayout>;
