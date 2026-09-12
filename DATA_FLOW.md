# 🐟 Permata-IoT: Data Flow Architecture

## System Overview

```
┌───────────────────────────────────────────────────────────────────────┐
│                       ESP32 (Sensor Node)                             │
│                                                                       │
│   DS18B20 → GPIO4  ──(10s interval)──→ HTTP POST                     │
│   LCD 16x2 → I2C SDA/GPIO21, SCL/GPIO22                            │
│   Device ID: FISH-XXXX (MAC-based random)                           │
└───────────────────┬───────────────────────────────────────────────────┘
                    │ HTTPS POST
                    │ Payload: { deviceId, temperature, ph }
                    ▼
┌───────────────────────────────────────────────────────────────────────┐
│               Remote IoT Endpoint                                       │
│                                                                       │
│   https://iot.bppmhkp.online/api/sensor/data                          │
│   (ESP32 target URL)                                                   │
│                                                                       │
│         ┌─────────────────────────────────────────────────────────┐   │
│         │  Node.js IoT Backend (port 3000)                        │   │
│         │  Location: root/iot-fish-monitoring/src/server.js       │   │
│         │                                                         │   │
│         │  GET  /api/v1/sensors/history/:id    → historical data  │   │
│         │  GET  /api/v1/sensors/all              → device list    │   │
│         │  POST /api/v1/sensors                → ingest new record│   │
│         │                                                         │   │
│         │         MySQL Remote                                      │   │
│         │         Host: 192.168.9.151:3306                         │   │
│         │         Database: fish_monitoring                        │   │
│         │         User: iot_user                                    │   │
│         └─────────────────────────────────────────────────────────┘   │
└───────────────────────────────────────────────────────────────────────┘


┌───────────────────────────────────────────────────────────────────────┐
│               Browser Client                                            │
│                                                                       │
│   http://192.168.9.152:8001/dashboard                                 │
│   - Alpine.js + Chart.js + Tailwind CSS                               │
│   - Two separate charts: Temperature (°C) & pH Level                  │
│   - 12-slot progress window: left→right fill, auto-reset on full      │
│   - Polling every 5 seconds                                           │
│                                                                       │
│         GET /api/v1/sensors/recent?device=FISH-00&limit=12            │
│         ↓                                                             │
│         ↓ CORS resolved via Laravel proxy (server-side bridge)        │
└───────────────────┬───────────────────────────────────────────────────┘
                    │ HTTP GET
                    ▼
┌───────────────────────────────────────────────────────────────────────┐
│               Laravel Dashboard (permata-iot)                         │
│                                                                       │
│   Server: 192.168.9.152:8001                                          │
│   Nginx Proxy: 192.168.9.151 (reverse proxy for :8001)               │
│                                                                       │
│         App\Http\Controllers\Api\ApiProxyController                   │
│                                                                       │
│         .env Configuration:                                           │
│         ├─ IOT_BACKEND_URL=https://iot.bppmhkp.online (PRIMARY)       │
│         ├─ IOT_LOCAL_URL=http://localhost:3000 (FALLBACK 1)           │
│         ├─ IOT_API_KEY='' (optional auth)                              │
│         └─ IOT_TIMEOUT=5 (seconds)                                     │
│                                                                       │
│         Routes (routes/api.php):                                      │
│         ├─ POST /sensor/data          → ingest()                      │
│         ├─ GET  /v1/sensors/recent    → recent()                      │
│         └─ GET  /devices              → devices()                     │
│                                                                       │
│         Config Service File:                                          │
│         config/services.php → 'iot' section reads from .env           │
└───────────────────────────────────────────────────────────────────────┘


## Request Flow: GET Recent Sensors

Dashboard Frontend
  │
  │ GET /api/v1/sensors/recent?device=FISH-00&limit=12
  ↓
ApiProxyController.recent()
  │
  ├─ [Step 1] Try PRIMARY backend (IOT_BACKEND_URL)
  │   ├─ curl localhost:3000/api/v1/sensors/history/FISH-00
  │   ├─ If SUCCESS: normalize response → return JSON []
  │   │   Format: [{ time, date, timestamp, temp, ph }, ...]
  │   └─ If FAIL: go to Step 2
  │
  ├─ [Step 2] Try FALLBACK 1 (IOT_LOCAL_URL — same machine Node.js)
  │   └─ Same endpoint, different host
  │
  ├─ [Step 3] Try FALLBACK 2 (IoT remote fallback via services.php)
  │
  └─ [Step 4] Ultimate fallback: local SQLite
      └─ Query: SensorLog::where('device_id', $device)->latest()->limit($limit)


## Response Normalization (API Proxy Layer)

Input from IoT Node.js:
{
  "data": [
    { "deviceId": "FISH-00", "temperature": 28.5, "ph_value": 7.2, "created_at": "2026-09-12T06:43:00Z" },
    { "deviceId": "FISH-00", "temperature": 27.8, "ph_value": 6.85, "created_at": "2026-09-12T06:43:10Z" }
  ]
}

↓ ApiProxyController normalizes to:

[
  { "time": "06:43:00", "date": "2026-09-12", "timestamp": 1789195380, "temp": 28.5, "ph": 7.2 },
  { "time": "06:43:10", "date": "2026-09-12", "timestamp": 1789195390, "temp": 27.8, "ph": 6.85 }
]

↓ Returned as JSON array to frontend.



## Request Flow: Ingest New Telemetry

Dashboard User/Admin or External System
  │
  │ POST /api/sensor/data
  │ { deviceId, temperature, ph }
  ↓
ApiProxyController.ingest()
  │
  ├─ Validate input (required, numeric ranges)
  ├─ Create local record: SensorLog::create([...])
  │
  └─ Forward to Primary IoT backend (non-blocking)
      └─ POST IOT_BACKEND_URL/api/v1/sensors (same payload)


## UI Components Structure

resources/views/dashboard/index.blade.php
  ├── HERO SECTION
  │   └─ Status indicator (OPTIMAL / PERLU PERHATIAN / KRITIS)
  │
  ├── METRICS & TELEMETRY (2-column grid)
  │   ├─ Group A: Biological Parameters
  │   │   ├─ Temperature value + progress bar
  │   │   └─ pH value + progress bar
  │   └─ Group B: Hardware Telemetry
  │       ├─ Device ID
  │       ├─ Connectivity status
  │       ├─ Last Seen timestamp
  │       └─ Uptime percentage
  │
  ├── CHARTS ROW (2-column grid)
  │   ├─ Trend Suhu (°C)
  │   │   └─ canvas#tempChart (Chart.js line chart, teal color)
  │   │       Auto-scale Y-axis (no fixed min/max)
  │   │       12 slots, fills left→right, resets when full
  │   │       Updates every 5s via setInterval poll
  │   │
  │   └─ Trend pH Level
  │       └─ canvas#phChart (Chart.js line chart, violet color)
  │           Auto-scale Y-axis
  │           12 slots, fills left→right, resets when full
  │           Updates every 5s via setInterval poll
  │
  └─ DEVICE SELECTION
      └─ Dropdown: select device (default: FISH-00)
          On change → redirect to ?device={selected}



## Deployment Target: Cross-City Migration

Current Setup (Local Dev Server):
  Machine A (192.168.9.152):
    ├─ Node.js IoT Backend ← port 3000 (primary data source)
    ├─ Laravel Dashboard ← port 8001 (frontend proxy)
    └─ Nginx Reverse Proxy ← port 443 (SSL termination)
  
  Machine B (192.168.9.151):
    └─ MySQL Remote Database ← port 3306 (fish_monitoring schema)

Target Production (Two Cities):
  City A (Client Access):
    ├─ Laravel Dashboard ONLY (no IoT backend needed)
    └─ All telemetry requests proxied to: IOT_BACKEND_URL
  
  City B (Data Center):
    ├─ IoT Node.js Backend ← port 3000 / HTTPS subdomain
    └─ MySQL Database ← still hosted here
  
  Migration Steps:
    1. Deploy Laravel permata-iot on City A server
    2. Set IOT_BACKEND_URL=https://iot.yourdomain.com/api
    3. php artisan config:clear
    4. Done — no code changes needed


## Troubleshooting Reference

| Symptom | Cause | Fix |
|---------|-------|-----|
| Chart blinking/blanking | new Chart() + destroy() every poll | Singleton ref, mutasi array data, chart.update() |
| Chart fills right-to-left | No boundary check at 12 slots | filledCount >= 12 → reset, then fill[0]++ |
| Suhu invisible below 20°C | Hardcoded scales.y.min=20 | Remove min/max, auto-scale dynamic |
| No data shows up | Wrong device ID default | Change to "FISH-00" (has real data) |
| 403/CORS errors | cross-origin browser block | Laravel proxy resolves (server→server no CORS) |
| PHP parse error at line 12 | env() invalid in class property | Use __construct() + config() pattern |
| Mixed content blocked | HTTP frontend → HTTPS backend | Single domain via Nginx SSL termination |
