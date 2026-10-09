@extends('layouts.admin')

@section('title', __('Failed Job Details'))

{{-- Read-only inspection of one failed_jobs row — no retry, no delete.
     $failedJob is the pre-summarized shape from FailedJobViewService:
     only derived safe fields plus the full exception text. The raw
     payload (which can embed serialized model data) is never passed
     here. The exception is echoed with {{ }} inside <pre>, so it is
     always escaped plain text, never interpreted as HTML. --}}
@section('content')
    <x-crud.back :href="route('admin.data-cleanup.index', ['tab' => 'system'])">{{ __('Data Cleanup (System)') }}</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-admin.card title="{{ $failedJob->job_name }}">
            <dl class="crud-facts">
                <x-crud.fact :label="__('UUID')" class="col-span-2"><span class="break-all font-mono text-[13px]">{{ $failedJob->uuid }}</span></x-crud.fact>
                <x-crud.fact :label="__('Failed At')">{{ display_datetime($failedJob->failed_at, 'd M Y, h:i:s A') ?? '—' }}</x-crud.fact>
                <x-crud.fact :label="__('Connection')">{{ $failedJob->connection }}</x-crud.fact>
                <x-crud.fact :label="__('Queue')">{{ $failedJob->queue }}</x-crud.fact>
            </dl>
        </x-admin.card>

        <x-admin.card :title="__('Exception')">
            <pre class="max-h-96 overflow-auto rounded-lg bg-navy-950 p-4 font-mono text-[12px] leading-relaxed text-slate-200">{{ $failedJob->exception }}</pre>
        </x-admin.card>
    </div>
@endsection
