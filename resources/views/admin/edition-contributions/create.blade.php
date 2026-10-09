@extends('layouts.admin')

@section('title', 'Record Contribution')

@section('content')
    @php
        $selectedContributor = old('contributor_id', request('contributor_id'));
        // Reopen the "not in the list" panel when it was being used (a failed save, the duplicate warning).
        $newContributorOpen = old('new_name') || old('new_village') || old('new_phone') || old('new_address') || $errors->has('confirm_duplicate');
    @endphp

    <x-crud.back :href="route('admin.edition-contributions.index')">Contributions</x-crud.back>

    <x-crud.form :action="route('admin.edition-contributions.store')" :cancel="route('admin.edition-contributions.index')" submit="Save contribution">
        <div class="crud-grid">
            <div class="crud-main">
                <x-admin.card title="Who gave">
                    <x-form.select
                        name="edition_id"
                        label="Edition"
                        placeholder="Select edition"
                        :options="$editions->pluck('name', 'id')"
                        :value="old('edition_id', request('edition_id'))"
                        :autofocus="! request('edition_id')"
                    />

                    {{--
                        Pick an existing contributor: a type-to-find box above a plain <select>. The <select> is the
                        real field, so the page still works if the script does not run (the whole list is there).
                    --}}
                    <div class="fld" id="contributor-picker">
                        <label for="contributor-filter" class="fld-label">Contributor</label>
                        <input
                            type="search"
                            id="contributor-filter"
                            class="fld-control mb-2"
                            placeholder="Type a name or village to find them&hellip;"
                            autocomplete="off"
                            aria-controls="contributor_id"
                        />
                        {{-- Shown by the script once somebody is chosen; the list itself stays hidden until typing starts. --}}
                        <p id="contributor-chosen" class="mb-2 hidden items-center gap-2 text-[13px] text-slate-700">
                            <span>Chosen: <strong class="font-semibold text-slate-900" data-chosen-name></strong></span>
                            <button type="button" id="contributor-change" class="crud-link">Change</button>
                        </p>
                        <select
                            id="contributor_id"
                            name="contributor_id"
                            class="fld-control"
                            aria-describedby="contributor-empty"
                            @if($errors->has('contributor_id')) aria-invalid="true" @endif
                        >
                            {{-- An empty first choice, so the browser never picks the first person by itself. --}}
                            <option value="" @selected(! $selectedContributor)>Select a contributor</option>
                            @foreach($contributors as $contributor)
                                <option value="{{ $contributor->id }}" @selected((string) $selectedContributor === (string) $contributor->id)>{{ $contributor->label() }}</option>
                            @endforeach
                        </select>
                        <p id="contributor-empty" class="fld-help hidden" role="status"></p>
                        @error('contributor_id')
                            <p class="fld-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="committee-dues-preview" class="mb-3.5 hidden rounded-lg border border-sky-100 bg-sky-50 p-3 text-[12px] text-sky-900">
                        <span class="crud-pill crud-pill-blue">Committee Member</span>
                        <dl class="mt-2 grid grid-cols-3 gap-2">
                            <div><dt class="text-sky-600">Target</dt><dd class="text-sm font-bold tabular-nums" data-dues-target>&mdash;</dd></div>
                            <div><dt class="text-sky-600">Paid So Far</dt><dd class="text-sm font-bold tabular-nums" data-dues-paid>&mdash;</dd></div>
                            <div><dt class="text-sky-600">Remaining</dt><dd class="text-sm font-bold tabular-nums" data-dues-remaining>&mdash;</dd></div>
                        </dl>
                    </div>

                    @if($canAddContributor)
                        {{--
                            First-time giver: describe them right here instead of leaving this form to add them under
                            Contributors first. A <details> element, so it opens and closes without any script.
                            Filling any of these means "new contributor"; the server refuses to also have one picked.
                        --}}
                        <details id="new-contributor" class="mb-1 rounded-lg border border-slate-200 bg-white" @if($newContributorOpen) open @endif>
                            <summary class="cursor-pointer select-none px-3 py-2.5 text-[13px] font-semibold text-brand">+ Not in the list? Add a new contributor</summary>

                            <div class="border-t border-slate-100 px-3 pt-3">
                                <div class="crud-cols">
                                    <x-form.input name="new_name" label="Name" :value="''" maxlength="255" autocomplete="off" />
                                    <x-form.input name="new_village" label="Village" :value="''" maxlength="100" placeholder="e.g. Shirur" autocomplete="off" help="Needed for a new contributor: it tells two people with the same name apart." />
                                </div>
                                <div class="crud-cols">
                                    <x-form.input name="new_phone" label="Phone (optional)" type="tel" inputmode="tel" :value="''" maxlength="20" autocomplete="off" />
                                    <x-form.input name="new_address" label="Address (optional)" :value="''" maxlength="255" autocomplete="off" />
                                </div>

                                @if($canAddToCommittee)
                                    <label class="mb-4 flex items-center gap-2 text-[13px] text-slate-700">
                                        <input type="checkbox" name="add_to_committee" value="1" class="rounded border-slate-300" @checked(old('add_to_committee'))>
                                        Also add them to this edition's committee
                                    </label>
                                @endif

                                {{-- "Already in the list?": filled by the server after a refused save, and live by the script. --}}
                                <div id="duplicate-box" class="crud-note crud-note-warn mb-4 {{ $errors->has('confirm_duplicate') ? '' : 'hidden' }}" role="alert">
                                    <p id="duplicate-text">{{ $errors->first('confirm_duplicate') }}</p>
                                    <ul id="duplicate-list" class="mt-1 list-disc pl-5"></ul>
                                    <label class="mt-2 flex items-center gap-2 text-[13px] font-medium text-slate-800">
                                        <input type="checkbox" id="confirm_duplicate" name="confirm_duplicate" value="1" class="rounded border-slate-300" @checked(old('confirm_duplicate'))>
                                        This is a different person
                                    </label>
                                </div>
                            </div>
                        </details>
                    @endif
                </x-admin.card>

                <x-admin.card title="The money">
                    <div class="crud-cols">
                        <x-form.input
                            name="amount"
                            label="Amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            inputmode="decimal"
                            required
                            :autofocus="(bool) request('edition_id')"
                            help="Any amount greater than {{ money(0, 0) }} is accepted — a committee member's target above may be reached across several separate contributions."
                        />

                        <x-form.input name="contributed_at" label="Date" type="date" required />
                    </div>

                    <x-form.input name="notes" label="Notes" :value="old('notes')" />
                </x-admin.card>
            </div>

            <div class="crud-aside">
                <p class="crud-note crud-note-brand">Each contribution also adds an <strong class="font-semibold text-slate-700">income</strong> line to the ledger, and a receipt you can print or download.</p>
                @if($canAddContributor)
                    <p class="crud-note mt-3">Giving for the first time? Use <strong class="font-semibold text-slate-700">Add a new contributor</strong> under the list: they are saved together with this contribution.</p>
                @endif
            </div>
        </div>
    </x-crud.form>

    {{--
        Small, page-local scripts (nothing shared). Everything here only makes the form quicker: with the
        script off, the full list, the "new contributor" panel and the server-side checks all still work.
    --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editionSelect = document.getElementById('edition_id');
            const contributorSelect = document.getElementById('contributor_id');
            const filterInput = document.getElementById('contributor-filter');
            const emptyNote = document.getElementById('contributor-empty');
            const panel = document.getElementById('committee-dues-preview');

            if (! editionSelect || ! contributorSelect || ! panel) return;

            // ---- Committee dues preview for the chosen contributor -------------------------------------------
            let lastPreview = null;

            const refresh = async () => {
                const editionId = editionSelect.value;
                const contributorId = contributorSelect.value;

                // The same edition + contributor as the last look needs no second request (typing in the
                // find box calls this on every key).
                if (`${editionId}:${contributorId}` === lastPreview) return;
                lastPreview = `${editionId}:${contributorId}`;

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

            // ---- Type-to-find in the contributor list ---------------------------------------------------------
            const everyone = Array.from(contributorSelect.options).filter((option) => option.value !== '').map((option) => ({
                value: option.value,
                label: option.textContent.trim(),
                haystack: option.textContent.trim().toLowerCase(),
            }));

            // With the script on, nothing but the find box shows until somebody types (without it the plain
            // dropdown below stays). Who is chosen is shown as one line with a Change button.
            const chosenLine = document.getElementById('contributor-chosen');
            const chosenName = chosenLine.querySelector('[data-chosen-name]');

            const syncChosen = () => {
                const option = contributorSelect.options[contributorSelect.selectedIndex];

                if (contributorSelect.value !== '' && option) {
                    chosenName.textContent = option.textContent.trim();
                    chosenLine.classList.remove('hidden');
                    chosenLine.classList.add('flex');
                } else {
                    chosenLine.classList.add('hidden');
                    chosenLine.classList.remove('flex');
                }
            };

            const clearChoice = () => {
                contributorSelect.value = '';
                syncChosen();
            };

            contributorSelect.classList.add('hidden');
            syncChosen();

            document.getElementById('contributor-change').addEventListener('click', () => {
                clearChoice();
                refresh();
                filterInput.focus();
            });

            const details = document.getElementById('new-contributor');
            const newName = document.getElementById('new_name');
            const newVillage = document.getElementById('new_village');
            const newFields = ['new_name', 'new_village', 'new_phone', 'new_address'].map((id) => document.getElementById(id)).filter(Boolean);

            const showList = (query) => {
                const words = query.toLowerCase().split(/\s+/).filter(Boolean);
                const keep = contributorSelect.value;
                let shown = 0;

                contributorSelect.innerHTML = '';

                // The empty choice stays (so nobody is picked by accident) but is never shown as a row.
                const none = new Option('Select a contributor', '', false, keep === '');
                none.hidden = true;
                contributorSelect.add(none);

                everyone.forEach((person) => {
                    if (words.every((word) => person.haystack.includes(word))) {
                        contributorSelect.add(new Option(person.label, person.value, false, person.value === keep));
                        shown++;
                    }
                });

                // Nothing shows until somebody types; then the matches appear as a six-row list.
                if (query.trim() !== '' && shown > 0) {
                    contributorSelect.size = 6;
                    contributorSelect.classList.remove('hidden');
                } else {
                    contributorSelect.removeAttribute('size');
                    contributorSelect.classList.add('hidden');
                }

                emptyNote.textContent = '';
                emptyNote.classList.add('hidden');

                if (shown === 0 && query.trim() !== '') {
                    emptyNote.append(document.createTextNode(`Nobody matches "${query.trim()}". `));

                    if (details) {
                        const add = document.createElement('button');
                        add.type = 'button';
                        add.className = 'crud-link font-semibold';
                        add.textContent = `Add "${query.trim()}" as a new contributor`;
                        add.addEventListener('click', () => {
                            details.open = true;
                            newName.value = query.trim();
                            newName.dispatchEvent(new Event('input', { bubbles: true }));
                            (newVillage.value === '' ? newVillage : newName).focus();
                        });
                        emptyNote.append(add);
                    }

                    emptyNote.classList.remove('hidden');
                }

                refresh();
            };

            filterInput.addEventListener('input', () => showList(filterInput.value));

            // Choosing somebody closes the list again and shows them as "Chosen: ...".
            contributorSelect.addEventListener('change', () => {
                contributorSelect.removeAttribute('size');
                contributorSelect.classList.add('hidden');
                filterInput.value = '';
                emptyNote.classList.add('hidden');
                syncChosen();
            });

            // Both may already be pre-filled (old() after a validation failure, or ?edition_id=&contributor_id=
            // from the Committee tab's "Record contribution" link) - show the preview immediately.
            refresh();

            // The rest is only for a role that may add contributors from this form.
            if (! details) return;

            // ---- Existing contributor OR a new one, never both -----------------------------------------------
            const duplicateBox = document.getElementById('duplicate-box');
            const duplicateText = document.getElementById('duplicate-text');
            const duplicateList = document.getElementById('duplicate-list');
            const confirmDuplicate = document.getElementById('confirm_duplicate');

            const hideDuplicates = () => {
                duplicateBox.classList.add('hidden');
                duplicateList.innerHTML = '';
                confirmDuplicate.checked = false;
            };

            contributorSelect.addEventListener('change', () => {
                if (contributorSelect.value === '') return;

                newFields.forEach((field) => { field.value = ''; });
                hideDuplicates();
                details.open = false;
            });

            details.addEventListener('toggle', () => {
                if (details.open) {
                    clearChoice();
                    refresh();
                }
            });

            // ---- "Is this person already in the list?" while typing ------------------------------------------
            let lookupTimer = null;
            let lookupRound = 0;

            const lookup = async () => {
                const name = newName.value.trim();
                const round = ++lookupRound;

                if (name.length < 2) {
                    hideDuplicates();
                    return;
                }

                try {
                    const query = new URLSearchParams({ name, village: newVillage.value.trim() });
                    const response = await fetch(`{{ route('admin.edition-contributions.contributor-lookup') }}?${query}`, {
                        headers: { Accept: 'application/json' },
                    });

                    if (! response.ok || round !== lookupRound) return;

                    const { matches } = await response.json();

                    if (round !== lookupRound) return;

                    duplicateList.innerHTML = '';

                    if (matches.length === 0) {
                        hideDuplicates();
                        return;
                    }

                    duplicateText.textContent = matches.length === 1
                        ? 'This person may already be in the list:'
                        : 'These people may already be in the list:';

                    matches.forEach((match) => {
                        const item = document.createElement('li');
                        item.append(document.createTextNode(match.label + ' '));

                        if (match.active) {
                            const use = document.createElement('button');
                            use.type = 'button';
                            use.className = 'crud-link font-semibold';
                            use.textContent = 'Use this one';
                            use.addEventListener('click', () => {
                                filterInput.value = '';
                                showList('');
                                contributorSelect.value = match.id;
                                contributorSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                            item.append(use);
                        } else {
                            item.append(document.createTextNode('(switched off - turn them on again under Contributors)'));
                        }

                        duplicateList.append(item);
                    });

                    duplicateBox.classList.remove('hidden');
                } catch (error) {
                    // The save checks for duplicates too; a failed look-up only loses the early warning.
                }
            };

            newFields.forEach((field) => {
                field.addEventListener('input', () => {
                    if (field.value.trim() !== '') {
                        clearChoice();
                        refresh();
                    }
                });
            });

            [newName, newVillage].forEach((field) => {
                field.addEventListener('input', () => {
                    clearTimeout(lookupTimer);
                    lookupTimer = setTimeout(lookup, 350);
                });
            });

        });
    </script>
@endsection
