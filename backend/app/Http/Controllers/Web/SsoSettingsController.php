<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSsoSettingsRequest;
use App\Support\SsoSettings;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

/**
 * Admin → Settings → Single Sign-On: connect OpenMES to the company's
 * OpenID Connect identity provider.
 */
class SsoSettingsController extends Controller
{
    public function show()
    {
        return Inertia::render('settings/Sso', [
            'settings' => SsoSettings::all(),
            // What the admin registers at the provider as the redirect URI.
            'callbackUrl' => route('sso.callback'),
            'roles' => Role::where('guard_name', 'web')
                ->where('name', '!=', 'Admin')
                ->orderBy('name')
                ->pluck('name')
                ->values(),
        ]);
    }

    public function update(UpdateSsoSettingsRequest $request)
    {
        SsoSettings::save($request->validated());

        return redirect()->route('settings.sso')
            ->with('success', __('Single sign-on settings saved.'));
    }
}
