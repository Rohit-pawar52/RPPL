@extends('layouts.admin')

@section('title', 'Failed Job Details')

{{-- Read-only inspection of one failed_jobs row — no retry, no delete.
     $failedJob is the pre-summarized shape from FailedJobViewService:
     only derived safe fields plus the full exception text. The raw
     payload (which can embed serialized model data) is never passed
     here. The exception is echoed with {{ }} inside <pre>, so it is
     always escaped plain text, never interpreted as HTML. --}}
@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.data-cleanup.index', ['tab' => 'system']) }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to Data Cleanup (System)
        </a>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4">
        <h2 class="text-base font-semibold text-slate-900">{{ $failedJob->job_name }}</h2>

        <dl class="mt-4 grid grid-cols-1 gap-3 text-[13px] sm:grid-cols-2">
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400">UUID</dt>
                <dd class="break-words font-mono text-slate-700">{{ $failedJob->uuid }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400">Failed At</dt>
                <dd class="text-slate-700">{{ display_datetime($failedJob->failed_at, 'd M Y, h:i:s A') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400">Connection</dt>
                <dd class="text-slate-700">{{ $failedJob->connection }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-slate-400">Queue</dt>
                <dd class="text-slate-700">{{ $failedJob->queue }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 rounded-lg border border-slate-200 bg-white p-4">
        <h3 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Exception</h3>
        <pre class="max-h-80 overflow-x-auto overflow-y-auto rounded-md bg-slate-50 p-3 font-mono text-[12px] text-slate-700">{{ $failedJob->exception }}</pre>
    </div>
@endsection
