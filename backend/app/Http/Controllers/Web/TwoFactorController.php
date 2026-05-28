<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show 2FA setup page with QR code.
     */
    public function enable(Request $request)
    {
        $user = auth()->user();

        if ($user->two_factor_enabled) {
            return redirect()->route('settings.profile')
                ->with('info', 'Two-factor authentication is already enabled.');
        }

        // Generate secret (or reuse pending one from session)
        $secret = $request->session()->get('2fa_setup_secret');
        if (!$secret) {
            $secret = $this->google2fa->generateSecretKey();
            $request->session()->put('2fa_setup_secret', $secret);
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            'OpenMES',
            $user->username,
            $secret
        );

        // Generate QR code image as data URI
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($qrCodeUrl)
            ->encoding(new Encoding('UTF-8'))
            ->size(250)
            ->margin(10)
            ->build();

        $qrCodeDataUri = $result->getDataUri();

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qrCodeDataUri' => $qrCodeDataUri,
        ]);
    }

    /**
     * Confirm 2FA setup — validate first code.
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'code' => 'required|string|digits:6',
        ]);

        $secret = $request->session()->get('2fa_setup_secret');
        if (!$secret) {
            return back()->withErrors(['code' => 'Setup session expired. Please start again.']);
        }

        $valid = $this->google2fa->verifyKey($secret, $request->input('code'), 1);

        if (!$valid) {
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        $user = auth()->user();

        // Generate 8 recovery codes
        $recoveryCodes = collect(range(1, 8))->map(fn() => Str::random(10))->values();
        $hashedCodes = $recoveryCodes->map(fn($c) => Hash::make($c))->toArray();

        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashedCodes)),
        ]);

        $request->session()->forget('2fa_setup_secret');

        return view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable 2FA (requires current password).
     */
    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return redirect()->route('settings.profile')
            ->with('success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $user = auth()->user();

        if (!Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password.']);
        }

        if (!$user->two_factor_enabled) {
            return back()->with('error', '2FA is not enabled.');
        }

        $recoveryCodes = collect(range(1, 8))->map(fn() => Str::random(10))->values();
        $hashedCodes = $recoveryCodes->map(fn($c) => Hash::make($c))->toArray();

        $user->update([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($hashedCodes)),
        ]);

        return view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $recoveryCodes,
        ]);
    }
}
