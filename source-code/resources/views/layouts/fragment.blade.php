<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Admin')</title>
    <style>
        .modal-backdrop {
            -webkit-backdrop-filter: blur(4px);
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body>
    <main class="p-6 lg:p-7 relative" style="transition: opacity 0.15s ease-in-out;">
        @stack('styles')
        @yield('content')
        @stack('scripts')
    </main>
</body>
</html>
