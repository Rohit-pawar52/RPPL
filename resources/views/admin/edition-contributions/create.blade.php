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
                :value="old('edition_id', request('edition_id'))"
            />

            <x-form.select
                name="contributor_id"
                label="Contributor"
                placeholder="Select a contributor"
                :options="$contributors->pluck('name', 'id')"
                :value="old('contributor_id', request('contributor_id'))"
            />

            <div id="committee-dues-preview" class="mb-3.5 hidden rounded-md border border-blue-100 bg-blue-50 p-3 text-[12px] text-blue-900">
                <span class="inline-flex items-center rounded-full bg-blue-600 px-2 py-0.5 text-[11px] font-medium text-white">Committee Member</span>
                <dl class="mt-2 grid grid-cols-3 gap-2">
                    <div><dt class="text-blue-500">Target</dt><dd class="font-medium" data-dues-target>&mdash;</dd></div>
                    <div><dt class="text-blue-500">Paid So Far</dt><dd class="font-medium" data-dues-paid>&mdash;</dd></div>
                    <div><dt class="text-blue-500">Remaining</dt><dd class="font-medium" data-dues-remaining>&mdash;</dd></div>
                </dl>
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
                Any amount greater than {{ money(0, 0) }} is accepted — a committee member's target above may be reached across several separate contributions.
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

    {{--
        Small, page-local, purpose-built preview — not a shared/generic
        AJAX pattern. Fires only when BOTH selects have a value; shows
        nothing (and hides the panel again) whenever either is cleared or
        the pair isn't a committee membership.
    --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editionSelect = document.getElementById('edition_id');
            const contributorSelect = document.getElementById('contributor_id');
            const panel = document.getElementById('committee-dues-preview');

            if (! editionSelect || ! contributorSelect || ! panel) return;

            const refresh = async () => {
                const editionId = editionSelect.value;
                const contributorId = contributorSelect.value;

                if (! editionId || ! contributorId) {
                    panel.classList.add('hidden');
                    return;
                }

                try {
                    const response = await fetch(`{{ route('admin.edition-contributions.dues-preview') }}?edition_id=${editionId}&contributor_id=${contributorId}`, {
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok) {
                        panel.classList.add('hidden');
                        return;
                    }

                    const data = await response.json();

                    if (! data.is_committee_member) {
                        panel.classList.add('hidden');
                        return;
                    }

                    panel.querySelector('[data-dues-target]').textContent = data.dues.target;
                    panel.querySelector('[data-dues-paid]').textContent = data.dues.paid;
                    panel.querySelector('[data-dues-remaining]').textContent = data.dues.remaining;
                    panel.classList.remove('hidden');
                } catch (error) {
                    panel.classList.add('hidden');
                }
            };

            editionSelect.addEventListener('change', refresh);
            contributorSelect.addEventListener('change', refresh);

            // Both may already be pre-filled (old() after a validation
            // failure, or ?edition_id=&contributor_id= from the
            // Committee tab's "Record contribution" link) — show the
            // preview immediately rather than waiting for a change event.
            refresh();
        });
    </script>
@endsection
