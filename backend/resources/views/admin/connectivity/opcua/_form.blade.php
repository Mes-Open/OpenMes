@php $o = $connection->opcuaConnection ?? null; @endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div class="sm:col-span-2">
        <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $connection->name) }}" class="form-input w-full" required>
        @error('name')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="form-label">{{ __('Description') }}</label>
        <input type="text" name="description" value="{{ old('description', $connection->description) }}" class="form-input w-full">
    </div>
    <div class="sm:col-span-2">
        <label class="form-label">{{ __('Endpoint URL') }} <span class="text-red-500">*</span></label>
        <input type="text" name="endpoint_url" value="{{ old('endpoint_url', $o?->endpoint_url) }}" class="form-input w-full" placeholder="opc.tcp://192.168.1.10:4840" required>
        @error('endpoint_url')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="form-label">{{ __('Security policy') }}</label>
        <select name="security_policy" class="form-input w-full">
            <option value="None" @selected(old('security_policy', $o?->security_policy)==='None')>None</option>
            <option value="Basic256Sha256" @selected(old('security_policy', $o?->security_policy)==='Basic256Sha256')>Basic256Sha256</option>
        </select>
    </div>
    <div>
        <label class="form-label">{{ __('Security mode') }}</label>
        <select name="security_mode" class="form-input w-full">
            <option value="None" @selected(old('security_mode', $o?->security_mode)==='None')>None</option>
            <option value="Sign" @selected(old('security_mode', $o?->security_mode)==='Sign')>Sign</option>
            <option value="SignAndEncrypt" @selected(old('security_mode', $o?->security_mode)==='SignAndEncrypt')>SignAndEncrypt</option>
        </select>
    </div>
    <div>
        <label class="form-label">{{ __('Authentication') }}</label>
        <select name="auth_mode" class="form-input w-full">
            <option value="anonymous" @selected(old('auth_mode', $o?->auth_mode)==='anonymous')>Anonymous</option>
            <option value="username" @selected(old('auth_mode', $o?->auth_mode)==='username')>Username/Password</option>
            <option value="certificate" @selected(old('auth_mode', $o?->auth_mode)==='certificate')>Certificate</option>
        </select>
    </div>
    <div>
        <label class="form-label">{{ __('Publishing interval (ms)') }}</label>
        <input type="number" name="publishing_interval_ms" value="{{ old('publishing_interval_ms', $o?->publishing_interval_ms ?? 1000) }}" class="form-input w-full" required>
    </div>
    <div>
        <label class="form-label">{{ __('Username') }}</label>
        <input type="text" name="username" value="{{ old('username', $o?->username) }}" class="form-input w-full" autocomplete="off">
    </div>
    <div>
        <label class="form-label">{{ __('Password') }} <span class="text-gray-400 text-xs">({{ __('leave blank to keep current') }})</span></label>
        <input type="password" name="password" class="form-input w-full" autocomplete="new-password">
    </div>
    <div class="sm:col-span-2">
        <label class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-blue-600" {{ old('is_active', $connection->is_active ?? true) ? 'checked' : '' }}>
            <span class="text-sm text-gray-700">{{ __('Active') }}</span>
        </label>
    </div>
</div>
