<x-layout description="Free private image tools for background removal, compression, enhancement, passport photos, transparency, and WebP conversion.">
    <section class="overflow-hidden bg-white">
        <div class="site-width relative py-20 text-center sm:py-28">
            <div class="hero-blob hero-blob-one"></div><div class="hero-blob hero-blob-two"></div>
            <p class="eyebrow mx-auto">No account · Free local processing</p>
            <h1 class="relative mx-auto mt-6 max-w-4xl text-5xl font-black leading-[1.02] tracking-[-.045em] text-slate-950 sm:text-7xl">Make every image <span class="text-gradient">work harder.</span></h1>
            <p class="relative mx-auto mt-7 max-w-2xl text-lg leading-8 text-slate-600">Fast, focused image tools for shops, teams, and everyday projects. Your uploads stay private and disappear automatically.</p>
            <a href="#tools" class="btn-primary relative mt-9 inline-flex">Explore free tools <span aria-hidden="true">↓</span></a>
        </div>
    </section>

    <section id="tools" class="site-width scroll-mt-8 py-16">
        <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
            <div><p class="eyebrow">The toolkit</p><h2 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">What do you need to make?</h2></div>
            <label class="search-box"><span class="sr-only">Search tools</span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="tool-search" type="search" placeholder="Search tools…" autocomplete="off"></label>
        </div>
        <div id="category-filters" class="mt-6 flex flex-wrap gap-2" aria-label="Filter tools by category"><button class="filter-chip is-active" data-category="all" type="button">All</button>@foreach($categories as $category)<button class="filter-chip" data-category="{{ strtolower($category) }}" type="button">{{ $category }}</button>@endforeach</div>
        <div id="tool-grid" class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach($tools as $slug => $tool)
                <a href="{{ route('tools.show', $slug) }}" class="tool-card" data-category="{{ strtolower($tool['category']) }}" data-search="{{ strtolower($tool['name'].' '.$tool['short'].' '.$tool['category']) }}">
                    <div class="tool-icon"><x-tool-icon :name="$tool['icon']" class="size-7" /></div>
                    <div class="mt-6 flex items-center justify-between gap-3"><span class="chip">{{ $tool['category'] }}</span><span class="text-xs font-bold text-slate-400">{{ $tool['output'] }}</span></div>
                    <h3 class="mt-4 text-xl font-extrabold tracking-tight">{{ $tool['name'] }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-500">{{ $tool['short'] }}</p>
                    <span class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-violet-700">Open tool <span class="transition group-hover:translate-x-1">→</span></span>
                </a>
            @endforeach
        </div>
        <p id="no-tools" class="mt-10 hidden rounded-2xl border border-dashed border-slate-300 p-10 text-center text-slate-500">No tools match that search yet.</p>
    </section>

    <section class="site-width"><div class="privacy-banner"><div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-emerald-100 text-emerald-700">✓</div><div><h2 class="font-extrabold">Privacy without a settings maze</h2><p class="mt-1 text-sm leading-6 text-slate-600">Files are stored in private, random locations only while your task runs, then automatically removed after {{ config('image-tools.task_ttl_minutes') }} minutes.</p></div></div></section>
</x-layout>
