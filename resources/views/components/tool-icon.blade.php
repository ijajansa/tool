@props(['name'])
<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('wand') <path d="m15 4 5 5L8 21l-5-5L15 4Z"/><path d="m6 15 5 5M14 2v3M21 12h-3M19 4l-2 2"/> @break
        @case('compress') <path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5M3 3l6 6M21 3l-6 6M3 21l6-6M21 21l-6-6"/> @break
        @case('sparkles') <path d="m12 3-1.3 3.7L7 8l3.7 1.3L12 13l1.3-3.7L17 8l-3.7-1.3L12 3ZM5 14l-.8 2.2L2 17l2.2.8L5 20l.8-2.2L8 17l-2.2-.8L5 14ZM19 13l-.8 2.2L16 16l2.2.8L19 19l.8-2.2L22 16l-2.2-.8L19 13Z"/> @break
        @case('id') <rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M5.5 17c.7-2.1 2-3 3.5-3s2.8.9 3.5 3M15 9h4M15 13h4"/> @break
        @case('layers') <path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/> @break
        @default <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>
    @endswitch
</svg>
