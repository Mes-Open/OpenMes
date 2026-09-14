<?php

namespace App\Http\Requests\Web\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload of a module ZIP (Admin → Modules → Install). The ZIP's contents — the
 * manifest, entry paths, PHP scanned for dangerous calls — are checked by
 * ModuleManager::installFromZip; this only guards the upload itself.
 */
class InstallModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // TabAccessMiddleware already restricts /admin/modules to users granted
        // the Modules tab (Admin by default).
        return true;
    }

    public function rules(): array
    {
        return [
            // 20 MB, matching the "Max 20 MB" hint on the drop zone.
            'module_zip' => ['required', 'file', 'mimes:zip', 'max:20480'],
        ];
    }
}
