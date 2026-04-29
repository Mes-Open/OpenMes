@extends('layouts.app')

@section('title', 'Add LOT Sequence')

@section('content')
<x-breadcrumbs :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'LOT Sequences', 'url' => route('admin.lot-sequences.index')],
    ['label' => 'Add Sequence', 'url' => null],
]" />

<div class="max-w-3xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Add LOT Sequence</h1>

    <form method="POST" action="{{ route('admin.lot-sequences.store') }}" class="card">
        @csrf
        @include('admin.lot-sequences._form')
        <div class="flex justify-end gap-3 mt-6 pt-6 border-t">
            <a href="{{ route('admin.lot-sequences.index') }}" class="btn-touch btn-secondary">Cancel</a>
            <button type="submit" class="btn-touch btn-primary">Create Sequence</button>
        </div>
    </form>
</div>
@endsection
