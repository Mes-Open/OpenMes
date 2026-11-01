import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { __ } from '../../lib/i18n';
import { monoLabelCls } from './operatorUi';

const inputCls = 'w-full rounded-[10px] border border-om-line bg-om-bg px-3 py-2.5 text-[13px] text-om-ink placeholder:text-om-faint outline-none focus:border-om-accent focus:ring-2 focus:ring-om-accent/20';
const errorCls = 'mt-1 text-[11.5px] text-om-blocked';

/**
 * Report an issue against a work order (design ref: OpenMES Operator.dc.html,
 * issue modal). The type decides whether the order stops — the line's issue
 * types carry `is_blocking` — so the Blocking row says what the chosen type
 * will do rather than letting the operator override it. The title starts as
 * the type's name and stays editable.
 */
export default function ReportIssueDialog({ workOrder, issueTypes = [], onClose }) {
    const form = useForm({ work_order_id: '', issue_type_id: '', title: '', description: '' });
    const typeNames = issueTypes.map((t) => t.name);

    useEffect(() => {
        if (workOrder) form.setData({ work_order_id: String(workOrder.id), issue_type_id: '', title: '', description: '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [workOrder?.id]);

    if (!workOrder) return null;

    const selected = issueTypes.find((t) => String(t.id) === String(form.data.issue_type_id)) ?? null;
    const pickType = (type) => form.setData((prev) => ({
        ...prev,
        issue_type_id: String(type.id),
        // Follow the type while the title is still the previous type's name.
        title: !prev.title || typeNames.includes(prev.title) ? type.name : prev.title,
    }));

    const submit = (e) => {
        e.preventDefault();
        form.post('/operator/issue', { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-[rgba(10,9,8,0.42)]" onClick={onClose} />
            <div className="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4">
                <form
                    onSubmit={submit}
                    role="dialog"
                    aria-modal="true"
                    aria-label={__('Report issue')}
                    className="relative w-full sm:w-[480px] bg-om-card border border-om-line rounded-t-2xl sm:rounded-2xl shadow-[0_30px_70px_-24px_rgba(0,0,0,.45)] px-6 py-[22px]"
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="text-[17px] font-semibold text-om-ink">{__('Report issue')}</div>
                    <div className="font-mono text-[10.5px] text-om-faint mt-[3px] mb-[18px]">
                        {[workOrder.order_no, workOrder.product].filter(Boolean).join(' · ')}
                    </div>

                    <div className={`${monoLabelCls} mb-2`}>{__('Issue type')} <span className="text-om-blocked">*</span></div>
                    <div className="flex flex-wrap gap-[7px] mb-4" role="radiogroup" aria-label={__('Issue type')}>
                        {issueTypes.map((type) => {
                            const on = String(type.id) === String(form.data.issue_type_id);
                            return (
                                <button
                                    key={type.id}
                                    type="button"
                                    role="radio"
                                    aria-checked={on}
                                    onClick={() => pickType(type)}
                                    className={`text-[12.5px] font-medium px-3 py-2 rounded-lg cursor-pointer transition-colors ${
                                        on ? 'bg-om-blocked text-white' : 'bg-om-bg border border-om-line2 text-om-ink hover:border-om-faintest'
                                    }`}
                                >
                                    {type.name}
                                </button>
                            );
                        })}
                    </div>
                    {form.errors.issue_type_id && <p className={`${errorCls} -mt-3 mb-3`}>{form.errors.issue_type_id}</p>}

                    <div className="flex items-center justify-between gap-3 bg-om-bg border border-om-line2 rounded-[10px] px-[13px] py-[11px] mb-3.5">
                        <div>
                            <div className="text-[13px] font-medium text-om-ink">{__('Blocking')}</div>
                            <div className="text-[11px] text-om-muted">
                                {!selected
                                    ? __('Depends on the issue type')
                                    : selected.is_blocking
                                        ? __('Stops this work order until resolved')
                                        : __('Logged without stopping the work order')}
                            </div>
                        </div>
                        <span
                            aria-hidden="true"
                            className={`relative w-10 h-[23px] rounded-full shrink-0 transition-colors ${selected?.is_blocking ? 'bg-om-blocked' : 'bg-om-faintest'}`}
                        >
                            <span className={`absolute top-[2.5px] w-[18px] h-[18px] rounded-full bg-white transition-[left] duration-150 ${selected?.is_blocking ? 'left-[19.5px]' : 'left-[2.5px]'}`} />
                        </span>
                    </div>

                    <div className="mb-3">
                        <div className={`${monoLabelCls} mb-1.5`}>{__('Title')} <span className="text-om-blocked">*</span></div>
                        <input
                            aria-label={__('Title')}
                            type="text"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                            className={inputCls}
                            placeholder={__('Brief summary…')}
                            maxLength={255}
                        />
                        {form.errors.title && <p className={errorCls}>{form.errors.title}</p>}
                    </div>

                    <textarea
                        aria-label={__('Details')}
                        value={form.data.description}
                        onChange={(e) => form.setData('description', e.target.value)}
                        placeholder={__('What happened? (optional)')}
                        maxLength={2000}
                        className={`${inputCls} h-[74px] resize-none mb-4`}
                    />
                    {form.errors.description && <p className={`${errorCls} -mt-3 mb-3`}>{form.errors.description}</p>}

                    <div className="flex gap-[9px] justify-end">
                        <button type="button" onClick={onClose} className="text-[13px] font-medium text-om-ink border border-om-line rounded-[9px] px-4 py-2.5 cursor-pointer hover:bg-om-chip">
                            {__('Cancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing || !form.data.issue_type_id || !form.data.title}
                            className="text-[13px] font-semibold text-white bg-om-blocked rounded-[9px] px-4 py-2.5 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {__('Submit issue')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
