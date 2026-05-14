@extends('layouts.app')

@section('title', __('New Company'))

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('Companies'), 'url' => route('admin.companies.index')],
    ['label' => __('New Company'), 'url' => null],
]" />

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">{{ __('New Company') }}</h1>
            <p class="text-gray-600 mt-1">{{ __('Add a supplier, customer, or both') }}</p>
        </div>
        <a href="{{ route('admin.companies.index') }}" class="btn-touch btn-secondary">{{ __('← Back') }}</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('admin.companies.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">{{ __('Code') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="code" value="{{ old('code') }}"
                           class="form-input w-full" placeholder="e.g. COMP-001" required maxlength="50">
                    @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Name') }} <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="form-input w-full" placeholder="Company name" required maxlength="200">
                    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Tax ID') }}</label>
                    <input type="text" name="tax_id" value="{{ old('tax_id') }}"
                           class="form-input w-full" placeholder="e.g. PL1234567890" maxlength="50">
                    @error('tax_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Type') }} <span class="text-red-500">*</span></label>
                    <select name="type" class="form-input w-full" required>
                        <option value="">{{ __('— Select type —') }}</option>
                        <option value="supplier" @selected(old('type') === 'supplier')>{{ __('Supplier') }}</option>
                        <option value="customer" @selected(old('type') === 'customer')>{{ __('Customer') }}</option>
                        <option value="both" @selected(old('type') === 'both')>{{ __('Both') }}</option>
                    </select>
                    @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="form-input w-full" placeholder="contact@company.com" maxlength="200">
                    @error('email') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="form-label">{{ __('Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="form-input w-full" placeholder="+48 123 456 789" maxlength="50">
                    @error('phone') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="form-label">{{ __('Address') }}</label>
                    <textarea name="address" rows="3" class="form-input w-full" maxlength="1000"
                              placeholder="Street, city, postal code, country">{{ old('address') }}</textarea>
                    @error('address') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="form-label mb-0">{{ __('Active') }}</span>
                    </label>
                </div>
            </div>

            <div class="flex gap-3 justify-end mt-6">
                <a href="{{ route('admin.companies.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn-touch btn-primary">{{ __('Create Company') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
