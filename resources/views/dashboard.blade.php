<!DOCTYPE html>
<html lang="en" class="watchtower-root">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Watchtower</title>

    {{-- Distinctive typography for the dashboard only. Loaded from Google Fonts;
         this is the package's own UI and never touches the host asset pipeline. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600;700;800&family=IBM+Plex+Mono:wght@400;500;600&display=swap">

    {{-- Compiled, self-contained assets served from the package's dist/ dir.
         Watchtower never touches the host application's asset pipeline. --}}
    <link rel="stylesheet" href="{{ route('watchtower.assets.css') }}">

    <script>
        window.Watchtower = @json($config);
        window.Watchtower.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        window.Watchtower.basePath = "{{ url(config('watchtower.path', 'watchtower')) }}";
        window.Watchtower.apiBase = "{{ url(config('watchtower.path', 'watchtower').'/api') }}";
    </script>
</head>
<body>
    <div id="watchtower-app"></div>
    <script src="{{ route('watchtower.assets.js') }}"></script>
</body>
</html>
