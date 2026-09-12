<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @stack('title')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-slate-200 font-inter antialiased min-h-screen">

    {{-- Top Navigation --}}
    <nav class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-7 h-7 text-teal-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15c2.5-1.5 5-2.5 7.5-1 2 1.2 3.5-.5 4-2.5.5-2.5 2.5-3 4-1s.5 5.5-2 8"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15.5C3.5 17 3 19 4 20.5c1 1.5 3 2 5 1.5 1.5-.4 2-1.5 2-3"/>
                </svg>
                <span class="text-lg font-semibold tracking-tight text-slate-100">permata-iot</span>
            </div>
            <div class="flex items-center gap-4">
                <span id="clock" class="text-sm text-slate-400 font-mono"></span>
                <select id="deviceSelect" class="bg-slate-800 border border-slate-700 rounded-md text-sm px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-teal-500 text-slate-300">
                    <option value="FISH-8A4E" selected>FISH-8A4E</option>
                    <option value="FISH-B2C7">FISH-B2C7</option>
                    <option value="FISH-D9E1">FISH-D9E1</option>
                </select>
            </div>
        </div>
    </nav>

    {{-- Main Content --}}
    <main class="max-w-7xl mx-auto px-6 py-6 space-y-6">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="border-t border-slate-800/60 mt-8">
        <div class="max-w-7xl mx-auto px-6 py-3 text-xs text-slate-500 flex items-center justify-between">
            <span>© 2026 BPPMHKP — Fish Monitor Dashboard v1.0</span>
            <span id="lastUpdate" class="font-mono"></span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    @stack('scripts')
</body>
</html>
