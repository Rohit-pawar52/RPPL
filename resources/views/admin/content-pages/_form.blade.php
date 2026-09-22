{{-- Shared by every tab in index.blade.php — one form partial for all
     three fixed content pages. $page is always a real, existing record
     (see DemoContentPageSeeder); there is no "create" state. --}}
<div class="mb-3 flex items-center justify-between">
    <p class="text-xs text-neutral-500">
        Public URL:
        <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener" class="theme-link hover:underline">
            {{ $page->publicUrl() }}
        </a>
    </p>
</div>

<form method="POST" action="{{ route('admin.content-pages.update', $page) }}" novalidate>
    @csrf
    @method('PUT')

    <x-form.input name="title" label="Title" :value="$page->title" required maxlength="150" />

    <div class="mb-3.5">
        <label for="content" class="mb-1 block text-xs font-medium text-neutral-700">Content</label>
        <textarea
            id="content"
            name="content"
            rows="16"
            maxlength="20000"
            class="w-full rounded-md border px-3 py-2 font-mono text-[12px] leading-relaxed focus:outline-none focus:ring-2 {{ $errors->has('content') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
        >{{ old('content', $page->content) }}</textarea>
        <p class="mt-1 text-[11px] text-neutral-400">
            Markdown — headings (## Heading), paragraphs, lists (- item), links ([text](url)), and **bold**/*italic* emphasis. Raw HTML is not supported and will display as plain text.
        </p>
        @error('content')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <x-form.select
        name="is_active"
        label="Status"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$page->is_active ? '1' : '0'"
    />
    <p class="-mt-2.5 mb-3.5 text-[11px] text-neutral-400">
        Inactive pages are removed from the footer and return a 404 if visited directly.
    </p>

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save changes
    </button>
</form>
