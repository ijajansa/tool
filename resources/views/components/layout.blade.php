@props(['title' => null, 'description' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ config('site.name') }}</title>
    <meta name="description" content="{{ $description ?? config('site.tagline') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200/80 bg-white/90 backdrop-blur">
        <div class="site-width flex h-18 items-center justify-between gap-5">
            <a href="{{ route('home') }}" class="group flex items-center gap-3 font-extrabold tracking-tight" aria-label="{{ config('site.name') }} home">
                <span class="grid size-9 place-items-center rounded-xl bg-violet-600 text-white shadow-md shadow-violet-200 transition group-hover:-rotate-3">P</span>
                <span>{{ config('site.name') }}</span>
            </a>
            <nav aria-label="Main navigation" class="flex items-center gap-5 text-sm font-semibold text-slate-600">
                <a href="{{ route('home') }}#tools" class="hover:text-violet-700">All tools</a>
                <span class="hidden items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700 sm:flex"><span class="size-2 rounded-full bg-emerald-500"></span> Private & temporary</span>
            </nav>
        </div>
    </header>
    <main>{{ $slot }}</main>
    <footer class="mt-20 border-t border-slate-200 bg-white">
        <div class="site-width grid gap-8 py-12 md:grid-cols-2">
            <div><p class="font-extrabold">{{ config('site.name') }}</p><p class="mt-2 max-w-md text-sm leading-6 text-slate-500">Free local-first image tools for useful everyday work. No account and no paid processing API.</p></div>
            <div class="md:text-right"><p class="text-sm font-semibold text-slate-700">Your files are yours.</p><p class="mt-2 text-sm leading-6 text-slate-500">Uploads stay private and are automatically deleted after {{ config('image-tools.task_ttl_minutes') }} minutes.</p></div>
        </div>
    </footer>
</body>
</html>
