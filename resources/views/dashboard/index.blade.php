@extends('layouts.app')

@push('title')
<title>Fish Monitor — {{ $healthStatus['label'] }}</title>
@endpush

@push('scripts')
<script>
// Global data for live polling
window.fishMonitor = {
    device: '{{ request()->input("device", "FISH-00") }}',
    tempChart: null,
    phChart: null,
    buffer: [],
    maxPoints: 30,
    healthStatus: @json($healthStatus),
    
    init() {
        this.renderPage();
        this.startPolling();
        this.updateClock();
        setInterval(() => this.updateClock(), 1000);
        
        document.getElementById('deviceSelect').addEventListener('change', (e) => {
            window.location.href = `/?device=${e.target.value}`;
        });
    },

    async renderPage() {
        // Load historical data for initial chart fill — get up to 12 points from IoT backend
        const response = await fetch(`/api/v1/sensors/recent?device=${this.device}&limit=12`);
        this.buffer = await response.json();
        
        if (!Array.isArray(this.buffer) || this.buffer.length === 0) {
            this.buffer = [];  // Keep empty so chart starts blank
        }

        this.renderHero();
        this.renderMetrics();
        this.renderTempChart();  // Chart suhu saja
        this.renderPhChart();     // Chart pH saja
    },

    generateSampleData() {
        const data = [];
        const now = new Date();
        for (let i = 29; i >= 0; i--) {
            const t = new Date(now - i * 10000);
            data.push({
                time: t.toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'}),
                timestamp: t.getTime(),
                temp: parseFloat((27 + Math.random() * 3 - 1.5).toFixed(2)),
                ph: parseFloat((7.0 + Math.random() * 0.5 - 0.25).toFixed(2))
            });
        }
        return data;
    },

    updateClock() {
        const el = document.getElementById('clock');
        if (el) el.textContent = new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    },

    getStatusColor(status) {
        const colors = {
            'OPTIMAL': {bg: 'rgba(52, 211, 153, 0.1)', border: '#34d399', text: '#34d399'},
            'PERLU PERHATIAN': {bg: 'rgba(251, 191, 36, 0.1)', border: '#fbbf24', text: '#fbbf24'},
            'KRITIS': {bg: 'rgba(251, 113, 133, 0.1)', border: '#fb7185', text: '#fb7185'},
            'Tidak Ada Data': {bg: 'rgba(100, 116, 139, 0.1)', border: '#64748b', text: '#64748b'}
        };
        return colors[status] || colors['Tidak Ada Data'];
    },

    renderHero() {
        const heroEl = document.getElementById('heroSection');
        if (!heroEl || this.healthStatus.label === 'Tidak Ada Data' && this.buffer.length === 0) {
            // Will be updated by first poll
        }
    },

    renderMetrics() {
        const latest = this.buffer.length > 0 ? this.buffer[this.buffer.length - 1] : null;
        if (!latest) return;

        const color = this.getStatusColor(this.healthStatus.label);
        const heroEl = document.getElementById('heroContent');
        if (heroEl) {
            heroEl.innerHTML = `
                <div class="flex items-center gap-4">
                    <div class="w-3 h-3 rounded-full" style="background:${color.border}; animation:pulse 2s infinite;"></div>
                    <h1 class="text-2xl md:text-3xl font-bold tracking-tight" style="color:${color.text}">${this.healthStatus.label}</h1>
                </div>
                <p class="text-slate-400 mt-1">Kondisi air kolam stabil — suhu &amp; pH dalam batas normal</p>
            `;
        }

        // Update metric values
        const tempEl = document.getElementById('tempValue');
        const phEl = document.getElementById('phValue');
        const deviceEl = document.getElementById('deviceId');
        const lastSeenEl = document.getElementById('lastSeen');

        if (tempEl) tempEl.textContent = `${latest.temp} °C`;
        if (phEl) phEl.textContent = latest.ph.toFixed(2);
        if (deviceEl) deviceEl.textContent = this.device;
        if (lastSeenEl) lastSeenEl.textContent = this.buffer.length > 0 ? 'Baru saja' : '-';
    },

    // ====== TEMPERATURE CHART ======
    renderTempChart() {
        const ctx = document.getElementById('tempChart').getContext('2d');
        
        if (!this.tempChart) {
            // FIRST CALL: create empty 12-slot chart
            this.tempChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array(12).fill(''),
                    datasets: [{
                        label: 'Suhu (°C)',
                        data: Array(12).fill(null),
                        borderColor: '#2dd4bf',
                        backgroundColor: 'rgba(45, 212, 191, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: {
                        legend: { display: true, position: 'top', labels: { color: '#94a3b8', font: { family: 'Inter', size: 12 }, usePointStyle: true, pointStyle: 'circle' } },
                        tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.95)', titleColor: '#cbd5e1', bodyColor: '#94a3b8', borderColor: '#334155', borderWidth: 1, cornerRadius: 8, padding: 12 }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(51, 65, 85, 0.3)', drawBorder: false }, ticks: { color: '#64748b', font: { family: 'Inter', size: 11 } } },
                        y: { type: 'linear', grid: { color: 'rgba(51, 65, 85, 0.3)', drawBorder: false }, ticks: { color: '#2dd4bf', callback: val => val + '°C' }, grace: '15%' }
                    }
                }
            });
            
            // Fill existing buffer left-to-right (max 12)
            const fillCount = Math.min(this.buffer.length, 12);
            for (let i = 0; i < fillCount; i++) {
                this.tempChart.data.labels[i] = this.buffer[i].time;
                this.tempChart.data.datasets[0].data[i] = this.buffer[i].temp;
            }
            this.tempChart.update();
        } else {
            // SUBSEQUENT: check if full → RESET, otherwise fill next slot
            let filledCount = 0;
            for (let d of this.tempChart.data.datasets[0].data) {
                if (d !== null && d !== undefined) filledCount++;
            }
            
            if (filledCount >= 12) {
                console.log('🔄 Temp Chart RESET');
                this.tempChart.data.labels = Array(12).fill('');
                this.tempChart.data.datasets[0].data = Array(12).fill(null);
                
                const latest = this.buffer[this.buffer.length - 1];
                this.tempChart.data.labels[0] = latest.time;
                this.tempChart.data.datasets[0].data[0] = latest.temp;
            } else {
                const fillIdx = filledCount;
                if (fillIdx < 12 && this.buffer.length > 0) {
                    const latest = this.buffer[this.buffer.length - 1];
                    this.tempChart.data.labels[fillIdx] = latest.time;
                    this.tempChart.data.datasets[0].data[fillIdx] = latest.temp;
                }
            }
            this.tempChart.update();
        }
    },

    // ====== pH CHART ======
    renderPhChart() {
        const ctx = document.getElementById('phChart').getContext('2d');
        
        if (!this.phChart) {
            // FIRST CALL: create empty 12-slot chart
            this.phChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array(12).fill(''),
                    datasets: [{
                        label: 'pH Level',
                        data: Array(12).fill(null),
                        borderColor: '#a78bfa',
                        backgroundColor: 'rgba(167, 139, 250, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 500 },
                    plugins: {
                        legend: { display: true, position: 'top', labels: { color: '#94a3b8', font: { family: 'Inter', size: 12 }, usePointStyle: true, pointStyle: 'circle' } },
                        tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.95)', titleColor: '#cbd5e1', bodyColor: '#94a3b8', borderColor: '#334155', borderWidth: 1, cornerRadius: 8, padding: 12 }
                    },
                    scales: {
                        x: { grid: { color: 'rgba(51, 65, 85, 0.3)', drawBorder: false }, ticks: { color: '#64748b', font: { family: 'Inter', size: 11 } } },
                        y: { type: 'linear', grid: { color: 'rgba(51, 65, 85, 0.3)', drawBorder: false }, ticks: { color: '#a78bfa' }, grace: '10%' }
                    }
                }
            });
            
            // Fill existing buffer left-to-right (max 12)
            const fillCount = Math.min(this.buffer.length, 12);
            for (let i = 0; i < fillCount; i++) {
                this.phChart.data.labels[i] = this.buffer[i].time;
                this.phChart.data.datasets[0].data[i] = this.buffer[i].ph;
            }
            this.phChart.update();
        } else {
            // SUBSEQUENT: check if full → RESET, otherwise fill next slot
            let filledCount = 0;
            for (let d of this.phChart.data.datasets[0].data) {
                if (d !== null && d !== undefined) filledCount++;
            }
            
            if (filledCount >= 12) {
                console.log('🔄 pH Chart RESET');
                this.phChart.data.labels = Array(12).fill('');
                this.phChart.data.datasets[0].data = Array(12).fill(null);
                
                const latest = this.buffer[this.buffer.length - 1];
                this.phChart.data.labels[0] = latest.time;
                this.phChart.data.datasets[0].data[0] = latest.ph;
            } else {
                const fillIdx = filledCount;
                if (fillIdx < 12 && this.buffer.length > 0) {
                    const latest = this.buffer[this.buffer.length - 1];
                    this.phChart.data.labels[fillIdx] = latest.time;
                    this.phChart.data.datasets[0].data[fillIdx] = latest.ph;
                }
            }
            this.phChart.update();
        }
    },

    startPolling() {
        setInterval(async () => {
            try {
                // Get latest reading from IoT backend for continuous updates
                const response = await fetch(`/api/v1/sensors/recent?device=${this.device}&limit=3`);
                const data = await response.json();
                
                if (Array.isArray(data) && data.length > 0) {
                    // Keep last 3 points in buffer so chart has context
                    this.buffer = data.slice(-3);
                    
                    const latest = this.buffer[this.buffer.length - 1];
                    this.healthStatus = this.recalcHealth(latest.temp, latest.ph);
                    
                    this.renderMetrics();
                    this.renderTempChart();  // Update temp chart only
                    this.renderPhChart();     // Update pH chart only
                    
                    const lastEl = document.getElementById('lastUpdate');
                    if (lastEl) lastEl.textContent = `Terakhir diperbarui: ${new Date().toLocaleTimeString('id-ID')}`;
                }
            } catch (err) {
                console.error('Polling failed:', err);
            }
        }, 5000);
    },

    recalcHealth(temp, ph) {
        if (temp >= 26 && temp <= 30 && ph >= 6.5 && ph <= 8.5) {
            return {'label': 'OPTIMAL', 'color': 'emerald'};
        }
        const tempWarn = (temp >= 24 && temp < 26) || (temp > 30 && temp <= 32);
        const phWarn = (ph >= 6.0 && ph < 6.5) || (ph > 8.5 && ph <= 9.0);
        if (tempWarn || phWarn) {
            return {'label': 'PERLU PERHATIAN', 'color': 'amber'};
        }
        return {'label': 'KRITIS', 'color': 'rose'};
    }
};

// Add pulse animation
const style = document.createElement('style');
style.textContent = `@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }`;
document.head.appendChild(style);
</script>
@endpush

{{-- HERO SECTION: Primary Insight --}}
<div id="heroSection" class="bg-slate-900/50 rounded-lg p-6 ring-1 ring-white/5">
    <div id="heroContent" class="flex items-center gap-4">
        <div class="w-3 h-3 rounded-full bg-slate-400 animate-pulse"></div>
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-400">Memuat data...</h1>
    </div>
    <p class="text-slate-400 mt-1">Menghubungkan ke sensor...</p>
</div>

{{-- METRICS & TELEMETRY: Two-column cluster --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Group A: Biological Parameters --}}
    <div class="bg-slate-900/50 rounded-lg p-6 ring-1 ring-white/5">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">Parameter Biologis Air</h2>
        
        <div class="space-y-6">
            {{-- Temperature --}}
            <div>
                <div class="flex items-end justify-between mb-2">
                    <span class="text-sm text-slate-400">Suhu Air</span>
                    <span id="tempValue" class="text-3xl font-bold text-teal-400">-- °C</span>
                </div>
                <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                    <div id="tempBar" class="h-full bg-gradient-to-r from-teal-500 to-teal-400 rounded-full transition-all duration-500" style="width: 50%;"></div>
                </div>
                <p class="text-xs text-slate-500 mt-1">Normal: 26–30°C</p>
            </div>

            {{-- pH --}}
            <div>
                <div class="flex items-end justify-between mb-2">
                    <span class="text-sm text-slate-400">pH Air</span>
                    <span id="phValue" class="text-3xl font-bold text-violet-400">--</span>
                </div>
                <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                    <div id="phBar" class="h-full bg-gradient-to-r from-violet-500 to-violet-400 rounded-full transition-all duration-500" style="width: 50%;"></div>
                </div>
                <p class="text-xs text-slate-500 mt-1">Ideal: 6.8–7.8</p>
            </div>
        </div>
    </div>

    {{-- Group B: Hardware Telemetry --}}
    <div class="bg-slate-900/50 rounded-lg p-6 ring-1 ring-white/5">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400 mb-4">Kondisi Node &amp; Transmisi</h2>
        
        <div class="space-y-4">
            <div class="flex items-center justify-between py-3 border-b border-slate-800/50">
                <span class="text-sm text-slate-400">Device ID</span>
                <span id="deviceId" class="font-mono text-sm text-slate-200">{{ request()->input("device", "FISH-00") }}</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-slate-800/50">
                <span class="text-sm text-slate-400">Status Konektivitas</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Online
                </span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-slate-800/50">
                <span class="text-sm text-slate-400">Interval Pengiriman</span>
                <span class="text-sm font-mono text-slate-200">10 detik</span>
            </div>
            <div class="flex items-center justify-between py-3 border-b border-slate-800/50">
                <span class="text-sm text-slate-400">Last Seen</span>
                <span id="lastSeen" class="text-sm font-mono text-slate-200">-</span>
            </div>
            <div class="flex items-center justify-between py-3">
                <span class="text-sm text-slate-400">Uptime</span>
                <span class="text-sm font-mono text-slate-200">99.8%</span>
            </div>
        </div>
    </div>
</div>

{{-- ANALYTICS: SEPARATE CHARTS --}}
<h2 class="text-lg font-semibold tracking-tight text-slate-200 mb-4">Grafik Trend</h2>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Temperature Chart --}}
    <div class="bg-slate-900/50 rounded-lg p-6 ring-1 ring-white/5">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-teal-400 mb-4">Trend Suhu (°C)</h3>
        <div class="relative" style="height: 280px;">
            <canvas id="tempChart"></canvas>
        </div>
    </div>

    {{-- pH Chart --}}
    <div class="bg-slate-900/50 rounded-lg p-6 ring-1 ring-white/5">
        <h3 class="text-sm font-semibold uppercase tracking-wider text-violet-400 mb-4">Trend pH Level</h3>
        <div class="relative" style="height: 280px;">
            <canvas id="phChart"></canvas>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Wait for Chart.js to load, then init
    const checkChart = setInterval(() => {
        if (typeof Chart !== 'undefined' && typeof fishMonitor !== 'undefined') {
            clearInterval(checkChart);
            fishMonitor.init();
        }
    }, 50);
});
</script>
@endpush
