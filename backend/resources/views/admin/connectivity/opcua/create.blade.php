@extends('layouts.app')
@section('title', __('New OPC UA Connection'))
@section('content')
<div class="max-w-3xl mx-auto">
    <a href="{{ route('admin.connectivity.opcua.index') }}" class="text-blue-600 hover:text-blue-800 text-sm mb-4 inline-block">← {{ __('Back') }}</a>
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ __('New OPC UA Connection') }}</h1>
    <form method="POST" action="{{ route('admin.connectivity.opcua.store') }}" class="card">
        @csrf
        @php $connection = new \App\Models\MachineConnection(); @endphp
        @include('admin.connectivity.opcua._form')
        <div class="flex justify-end gap-3 mt-6">
            <a href="{{ route('admin.connectivity.opcua.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Create') }}</button>
        </div>
    </form>
</div>
@endsection
