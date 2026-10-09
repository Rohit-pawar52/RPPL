{{-- Shared by every tab in index.blade.php — one form partial for all
     three fixed content pages. $page is always a real, existing record
     (see DemoContentPageSeeder); there is no "create" state. --}}
<x-crud.form :action="route('admin.content-pages.update', $page)" method="PUT" :cancel="route('admin.content-pages.index', ['tab' => request('tab', $page->type)])" :submit="__('Save changes')">
    <div class="crud-grid">
        <div class="crud-main">
            <x-admin.card :title="__('Content')">
                <x-form.input name="title" :label="__('Title')" :value="$page->title" required maxlength="150" />

                <x-form.textarea name="content" :label="__('Content')" :value="$page->content" rows="18" maxlength="20000" :help="__('Markdown — headings (## Heading), paragraphs, lists (- item), links ([text](url)), and **bold**/*italic* emphasis. Raw HTML is not supported and will display as plain text.')" />
            </x-admin.card>
        </div>

        <div class="crud-aside">
            <x-admin.card :title="__('Page')">
                <p class="crud-fact-label">{{ __('Public URL') }}</p>
                <p class="mt-1 break-all text-[13px]">
                    <a href="{{ $page->publicUrl() }}" target="_blank" rel="noopener" class="crud-link inline-flex items-center gap-1">
                        {{ $page->publicUrl() }}
                        <x-crud.glyph name="external" class="h-3.5 w-3.5 shrink-0" />
                    </a>
                </p>

                <div class="mt-4">
                    <x-form.select
                        name="is_active"
                        :label="__('Status')"
                        :options="['1' => __('Active'), '0' => __('Inactive')]"
                        :value="$page->is_active ? '1' : '0'"
                        :help="__('Inactive pages are removed from the footer and return a 404 if visited directly.')"
                    />
                </div>
            </x-admin.card>
        </div>
    </div>
</x-crud.form>
