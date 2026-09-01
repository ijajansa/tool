<x-layout :title="$definition['name']" :description="$definition['description']" :structured-faq="$content['faq']">
    <section class="border-b border-slate-200 bg-white">
        <div class="site-width py-12 sm:py-16">
            <nav aria-label="Breadcrumb" class="text-sm font-semibold text-slate-500"><a class="hover:text-violet-700" href="{{ route('home') }}#tools">All tools</a><span aria-hidden="true"> / </span><a class="hover:text-violet-700" href="{{ route('categories.show', $definition['category']) }}">{{ $definition['category'] }}</a><span aria-hidden="true"> / </span><span aria-current="page">{{ $definition['name'] }}</span></nav>
            <div class="mt-7 flex items-start gap-5"><div class="tool-icon shrink-0"><x-tool-icon :name="$definition['icon']" class="size-7" /></div><div><span class="chip">{{ $definition['category'] }}</span><h1 class="mt-3 text-3xl font-black tracking-tight sm:text-5xl">{{ $definition['name'] }}</h1><p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">{{ $definition['description'] }}</p></div></div>
        </div>
    </section>
    <section class="site-width py-10">
        <div class="grid gap-7 lg:grid-cols-[minmax(0,1fr)_340px]">
            <form id="tool-form" class="panel overflow-hidden" method="post" action="{{ route('tasks.store', $tool) }}" enctype="multipart/form-data" data-multiple="{{ $definition['multiple'] ? 'true' : 'false' }}">
                @csrf
                <div class="p-5 sm:p-8">
                    <div id="drop-zone" class="drop-zone" tabindex="0" role="button" aria-describedby="upload-help">
                        <input class="sr-only" id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" {{ $definition['multiple'] ? 'multiple' : '' }} required>
                        <div class="grid size-14 place-items-center rounded-2xl bg-violet-100 text-2xl text-violet-700">↑</div>
                        <p class="mt-4 font-extrabold">Drop {{ $definition['multiple'] ? 'images' : 'an image' }} here</p>
                        <p class="mt-1 text-sm text-slate-500">or <span class="font-bold text-violet-700">browse your device</span></p>
                        <p id="upload-help" class="mt-3 text-xs text-slate-400">JPG, PNG or WebP · up to {{ round($maxUploadKb / 1024, 1) }} MB each</p>
                    </div>
                    <div id="file-list" class="mt-4 hidden"></div>
                    <div id="preview-wrap" class="preview-grid mt-6 hidden"><figure><figcaption>Before</figcaption><div class="preview-stage checker"><img id="before-preview" alt="Uploaded image preview"></div></figure><figure><figcaption>After</figcaption><div class="preview-stage checker"><div id="after-placeholder" class="px-5 text-center text-sm text-slate-400">Your result will appear here</div><img id="after-preview" class="hidden" alt="Processed image preview"></div></figure></div>
                </div>
                <div class="border-t border-slate-200 bg-slate-50 p-5 sm:p-8">
                    @include('tools.forms.'.$tool)
                    <div id="form-errors" class="mt-5 hidden rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700" role="alert"></div>
                    @if(config('image-tools.captcha.enabled'))<div class="cf-turnstile mt-5" data-sitekey="{{ config('image-tools.captcha.site_key') }}"></div>@endif
                    <button id="submit-button" class="btn-primary mt-6 w-full justify-center" type="submit"><span>Process image</span><span aria-hidden="true">→</span></button>
                </div>
            </form>
            <aside class="space-y-5">
                <div id="status-card" class="panel p-6" aria-live="polite"><div class="flex items-center gap-3"><span id="status-dot" class="status-dot idle"></span><h2 id="status-title" class="font-extrabold">Ready when you are</h2></div><p id="status-message" class="mt-3 text-sm leading-6 text-slate-500">Choose an image, adjust your settings, and start processing.</p><div id="progress" class="mt-4 hidden h-2 overflow-hidden rounded-full bg-slate-100"><div class="progress-bar h-full rounded-full bg-violet-600"></div></div><div id="result-stats" class="mt-5 hidden grid grid-cols-2 gap-3"></div><div id="downloads" class="mt-5 space-y-2"></div></div>
                <div class="panel p-6"><p class="text-sm font-extrabold">Private by default</p><p class="mt-2 text-sm leading-6 text-slate-500">Your original and results are private, randomly named, and deleted after {{ config('image-tools.task_ttl_minutes') }} minutes.</p></div>
                <div class="panel p-6"><p class="text-sm font-extrabold">Tip</p><p class="mt-2 text-sm leading-6 text-slate-500">For the cleanest result, start with a sharp, well-lit image where the subject is easy to distinguish.</p></div>
            </aside>
        </div>
    </section>
    @include('tools.content')
</x-layout>
