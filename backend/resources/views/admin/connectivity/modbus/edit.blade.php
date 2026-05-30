@extends('layouts.app')
@section('title', __('Edit Modbus Connection'))
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('admin.connectivity.modbus.show', $connection) }}" class="text-blue-600 hover:text-blue-800 text-sm mb-4 inline-block">← {{ __('Back') }}</a>
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ __('Edit Modbus Connection') }}</h1>
    <form method="POST" action="{{ route('admin.connectivity.modbus.update', $connection) }}" class="card">
        @csrf @method('PUT')
        @include('admin.connectivity.modbus._form')
        <div class="flex justify-between items-center mt-6">
            <form method="POST" action="{{ route('admin.connectivity.modbus.destroy', $connection) }}" onsubmit="return confirm('{{ __('Delete this connection?') }}')">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 text-sm hover:underline">{{ __('Delete') }}</button>
            </form>
            <div class="flex gap-3">
                <a href="{{ route('admin.connectivity.modbus.show', $connection) }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn-touch btn-primary">{{ __('Save Changes') }}</button>
            </div>
        </div>
    </form>
</div>
@endsection
