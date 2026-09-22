@extends('layouts.admin')

@section('title', 'Record Contribution')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.edition-contributions.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to contributions
        </a>
    </div>

    <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.edition-contributions.store') }}" novalidate>
            @csrf

            <x-form.select
                name="edition_id"
                label="Edition"
                placeholder="Select edition"
                :options="$editions->pluck('name', 'id')"
                :value="old('edition_id')"
            />

            <div class="mb-3.5">
                <label for="source" class="mb-1 block text-xs font-medium text-neutral-700">Contributor</label>
                <select
                    id="source"
                    name="source"
                    class="w-full rounded-md border bg-white px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('source_id') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
                >
                    <option value="" disabled @selected(! old('source'))>Select a contributor</option>

                    <optgroup label="Committee Members">
                        @foreach($members as $member)
                            <option value="committee:{{ $member->id }}" @selected(old('source') === 'committee:'.$member->id)>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </optgroup>

                    <optgroup label="General Contributors">
                        @foreach($generalContributors as $generalContributor)
                            <option value="contributor:{{ $generalContributor->id }}" @selected(old('source') === 'contributor:'.$generalContributor->id)>
                                {{ $generalContributor->name }}
                            </option>
                        @endforeach
                    </optgroup>
                </select>
                @error('source_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
                @error('source_type')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <x-form.input
                name="amount"
                label="Amount"
                type="number"
                step="0.01"
                min="0.01"
                required
            />
            <p class="-mt-2.5 mb-3.5 text-xs text-neutral-400">
                Committee contributions require at least {{ money(\App\Models\EditionContribution::MINIMUM_AMOUNT, 0) }}; general contributions may be any amount above {{ money(0, 0) }}.
            </p>

            <x-form.input name="contributed_at" label="Date" type="date" required />

            <x-form.input name="notes" label="Notes" :value="old('notes')" />

            <div class="mt-2 flex items-center gap-2">
                <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Save contribution
                </button>
                <a href="{{ route('admin.edition-contributions.index') }}" class="rounded-md border border-neutral-200 px-3 py-2 text-[13px] font-medium text-neutral-600 hover:bg-neutral-50">
                    Cancel
                </a>
            </div>
        </form>
    </div>
@endsection
