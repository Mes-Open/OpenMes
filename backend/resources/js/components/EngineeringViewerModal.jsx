import { Modal } from '@openmes/ui';
import { __ } from '../lib/i18n';

/**
 * Sandboxed viewer for an interactive-HTML engineering package (#179). Shows the
 * short-lived signed viewer URL inside an `<iframe sandbox="allow-scripts">` —
 * WITHOUT `allow-same-origin`, so the package runs at an opaque origin: it cannot
 * read the app's cookies/localStorage, call the app API as the viewer, navigate
 * the top window, open popups, or submit forms. Shared by the admin panel and the
 * operator work-order view. `viewer` is `{ url, title }` or null/undefined.
 */
export default function EngineeringViewerModal({ viewer, onClose }) {
    if (!viewer) return null;

    return (
        <Modal
            open
            onClose={onClose}
            title={viewer.title}
            subtitle={__('Interactive viewer — sandboxed')}
            className="max-w-5xl"
        >
            <div className="flex flex-col gap-2">
                <iframe
                    title={viewer.title}
                    src={viewer.url}
                    sandbox="allow-scripts"
                    className="w-full h-[70vh] rounded-om-sm border border-om-line bg-white"
                />
            </div>
        </Modal>
    );
}
