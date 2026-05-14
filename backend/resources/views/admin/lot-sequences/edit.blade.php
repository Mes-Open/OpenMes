@extends('layouts.app')

@section('title', __('Edit LOT Sequence') . ' - ' . $lotSequence->name)

@section('content')
<x-breadcrumbs :items="[
    ['label' => __('Dashboard'), 'url' => route('admin.dashboard')],
    ['label' => __('LOT Sequences'), 'url' => route('admin.lot-sequences.index')],
    ['label' => $lotSequence->name, 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">{{ __('Edit LOT Sequence') }}</h1>

    <form method="POST" action="{{ route('admin.lot-sequences.update', $lotSequence) }}" class="card">
        @csrf @method('PUT')
        @include('admin.lot-sequences._form')
        <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
            <a href="{{ route('admin.lot-sequences.index') }}" class="btn-touch btn-secondary">{{ __('Cancel') }}</a>
            <button type="submit" class="btn-touch btn-primary">{{ __('Update Sequence') }}</button>
        </div>
    </form>
</div>
@endsection
