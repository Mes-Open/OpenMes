import { Head } from '@inertiajs/react';
import AppLayout from '../../../layouts/AppLayout';
import ResourceForm from '../../../components/ResourceForm';
import { SKILL_FIELDS } from './fields';

export default function SkillCreate() {
    return (
        <div className="max-w-7xl mx-auto">
            <Head title="New Skill" />
            <h1 className="text-3xl font-bold text-gray-800 mb-6">New Skill</h1>
            <ResourceForm
                action="/admin/skills"
                method="post"
                fields={SKILL_FIELDS}
                initial={{ code: '', name: '', description: '' }}
                submitLabel="Create"
                cancelHref="/admin/skills"
            />
        </div>
    );
}

SkillCreate.layout = (page) => <AppLayout>{page}</AppLayout>;
