{{-- Minimal standalone layout. Point `content-studio.layout` to your own
     layout to render the blog inside your site. That layout must yield
     "content" and render @stack('head') inside <head>. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @stack('head')
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite('resources/css/app.css')
    @endif
</head>
<body class="bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    @yield('content')
</body>
</html>
