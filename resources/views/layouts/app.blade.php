<!doctype html>
<html lang="fr" class="">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=1280, initial-scale=1">
<title>{{ $title ?? 'CRM Ultimate' }}</title>
<meta name="csrf-token" content="{{ csrf_token() }}">
{{-- Theme boot: before body to prevent flash --}}
<script>
    (function(){
        var t = localStorage.getItem('theme');
        if (t === 'dark' || (!t && matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Patrick+Hand&family=Caveat:wght@500;600;700&family=Kalam:wght@400;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
{{ $slot }}
</body>
</html>
