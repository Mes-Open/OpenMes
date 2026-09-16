import { useForm } from '@inertiajs/react';
import { Button, Checkbox, Modal } from '@openmes/ui';
import { __ } from '../../../lib/i18n';

export default function CreateTemplateDrawer({ productType, open, onClose }) {
    const form = useForm({ name: '', is_active: true });
    return <Modal open={open} onClose={onClose} side="right" width={560}
        title={__('Create Process Template')} subtitle={productType.name} closeLabel={__('Close')} keepMounted>
        <form className="space-y-5" onSubmit={e => {
            e.preventDefault();
            form.post(`/admin/product-types/${productType.id}/process-templates`);
        }}>
            <div>
                <label htmlFor="template-name" className="form-label">{__('Template Name')}</label>
                <input id="template-name" className="form-input w-full" required autoFocus
                    value={form.data.name} onChange={e => form.setData('name', e.target.value)}
                    placeholder={__('e.g., Standard Assembly Process, Quality Inspection v2')} />
                {form.errors.name && <p className="mt-1 text-sm text-om-blocked">{form.errors.name}</p>}
                <p className="mt-2 text-sm text-om-muted">{__("Version number will be assigned automatically. After creating the template, you'll be able to add production steps.")}</p>
            </div>
            <Checkbox checked={form.data.is_active} onChange={v => form.setData('is_active', v)}
                label={__('Active (template is ready for use in work orders)')} />
            <div className="sticky -bottom-4 z-10 -mx-[18px] -mb-4 flex gap-3 border-t border-om-line2 bg-om-panel px-[18px] py-[14px]">
                <Button type="submit" loading={form.processing} disabled={form.processing}>{__('Create Template')}</Button>
                <Button type="button" variant="outline" onClick={onClose}>{__('Cancel')}</Button>
            </div>
        </form>
    </Modal>;
}
