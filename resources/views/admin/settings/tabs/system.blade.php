<form method="POST" action="{{ route('admin.settings.system.update') }}" novalidate>
    @csrf
    @method('PUT')

    <x-form.select
        name="maintenance_mode"
        label="Maintenance Mode"
        :options="['0' => 'Disabled', '1' => 'Enabled']"
        :value="$settings->boolean('system.maintenance_mode') ? '1' : '0'"
    />

    <div class="mb-3.5">
        <label for="maintenance_message" class="mb-1 block text-xs font-medium text-neutral-700">Maintenance Message</label>
        <textarea
            id="maintenance_message"
            name="maintenance_message"
            rows="3"
            maxlength="1000"
            class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('maintenance_message') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
        >{{ old('maintenance_message', $settings->get('system.maintenance_message')) }}</textarea>
        @error('maintenance_message')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid gap-x-4 sm:grid-cols-3">
        <x-form.input name="currency" label="Currency Code" :value="$settings->get('system.currency')" required maxlength="10" />
        <x-form.input name="currency_symbol" label="Currency Symbol" :value="$settings->get('system.currency_symbol')" required maxlength="10" />
        <x-form.input name="display_timezone" label="Display Timezone" :value="$settings->get('system.display_timezone')" required placeholder="Asia/Kolkata" />
    </div>

    <x-form.input
        name="committee_minimum_contribution"
        label="Committee Minimum Contribution"
        type="number"
        step="0.01"
        min="0"
        :value="$settings->get('finance.committee_minimum_contribution')"
        required
    />
    <p class="-mt-3 mb-3.5 text-[11px] text-neutral-400">
        Target total contribution expected from each committee member — reachable across several smaller payments (installments), not a minimum per payment. Changing this changes the displayed dues target for every edition, including past ones; already-recorded contribution amounts are never affected.
    </p>

    @php
        $tournamentDayReminderChecked = old('tournament_day_reminder_enabled', $settings->boolean('notifications.tournament_day_reminder_enabled') ? '1' : '0') === '1';
    @endphp
    <div class="mb-3.5 border-t border-neutral-100 pt-3.5">
        <p class="mb-2 text-xs font-medium text-neutral-700">Tournament Day Morning Reminder</p>

        <input type="hidden" name="tournament_day_reminder_enabled" value="0" />
        <label class="flex items-center gap-2 text-[13px] text-neutral-700">
            <input
                type="checkbox"
                name="tournament_day_reminder_enabled"
                value="1"
                {{ $tournamentDayReminderChecked ? 'checked' : '' }}
                class="theme-focus-ring"
            />
            Send one morning push summarizing each edition's matches on a match day
        </label>

        <div class="mt-2 max-w-40">
            <x-form.input
                name="tournament_day_reminder_time"
                label="Send at (display timezone)"
                type="time"
                :value="$settings->get('notifications.tournament_day_reminder_time')"
            />
        </div>
        <p class="-mt-3 text-[11px] text-neutral-400">
            Sent once per edition per match day, at or after this time. Nothing is sent after 12:00 noon, so choose a morning time.
        </p>
    </div>

    @php
        $failedJobsAutoCleanupChecked = old('failed_jobs_auto_cleanup_enabled', $settings->boolean('system.failed_jobs_auto_cleanup_enabled') ? '1' : '0') === '1';
    @endphp
    <div class="mb-3.5 border-t border-neutral-100 pt-3.5">
        <p class="mb-2 text-xs font-medium text-neutral-700">Failed Job Cleanup</p>

        <input type="hidden" name="failed_jobs_auto_cleanup_enabled" value="0" />
        <label class="flex items-center gap-2 text-[13px] text-neutral-700">
            <input
                type="checkbox"
                name="failed_jobs_auto_cleanup_enabled"
                value="1"
                {{ $failedJobsAutoCleanupChecked ? 'checked' : '' }}
                class="theme-focus-ring"
            />
            Automatically delete old failed jobs
        </label>

        <div class="mt-2 max-w-40">
            <x-form.input
                name="failed_jobs_retention_days"
                label="Retention period (days)"
                type="number"
                min="7"
                max="365"
                :value="$settings->integer('system.failed_jobs_retention_days')"
            />
        </div>
        <p class="-mt-3 text-[11px] text-neutral-400">
            Failed jobs older than this retention period will be automatically removed once a day. Recent failed jobs remain available in Data Cleanup for inspection.
        </p>
    </div>

    <p class="mb-3.5 text-[11px] text-neutral-400">
        Maintenance mode and display timezone are live — enabling maintenance mode immediately blocks the public website, and the display timezone controls how dates/times are shown across the site. Currency/currency symbol are consumed application-wide via the <code>money()</code> helper (dashboard, reports, PDFs, the public registration flow).
    </p>

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save changes
    </button>
</form>
