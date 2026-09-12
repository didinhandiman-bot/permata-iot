# Deployment Guide: permata-iot.bppmhkp.online

## Arsitektur Jaringan
- **Frontend Laravel**: Server Merak `192.168.30.140` (listen port 8001)
- **Nginx Proxy**: Server Merak `192.168.9.140` (port 80 & 443)
- **IoT Backend**: Semarang `https://iot.bppmhkp.online/api/v1/sensors/history/:deviceId`

## Langkah Deployment Cepat

### 1. Install Aset Frontend
```bash
cd /home/omdien/permata-iot
npm install axios
npm run build
```

### 2. Konfigurasi `.env`
```bash
APP_URL=https://permata-iot.bppmhkp.online
IOT_BACKEND_URL=https://iot.bppmhkp.online
DB_CONNECTION=sqlite
DB_DATABASE=/home/omdien/permata-iot/database/sensor_logs.sqlite
```

### 3. Jalankan Laravel
```bash
sudo chown -R omdien:odmien .
php artisan serve --host=192.168.30.140 --port=8001
```

### 4. Setup Nginx Reverse Proxy
File konfigurasi tersedia di: `config/nginx/permata-iot.conf`

**PENTING**: Sesuaikan IP proxy_pass dengan interface jaringan yang aktif:
- Jika listen di `127.0.0.1`: `proxy_pass http://127.0.0.1:8001;`
- Jika listen di IP LAN: `proxy_pass http://<LAN_IP>:8001;`

### 5. Aktifkan HTTPS via Certbot
```bash
sudo ln -s /etc/nginx/sites-available/permata-iot /etc/nginx/sites-enabled/
sudo certbot --nginx -d permata-iot.bppmhkp.online
```

### 6. Fix Mixed Content (SSL Trust Proxies)
Edit `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: '*', headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO);
})
```

## Troubleshooting

### Error 504 Gateway Time-out
- Cek apakah PHP artisan serve masih berjalan
- Pastikan IP proxy_pass di Nginx sesuai dengan interface aktif (`127.0.0.1` atau `<LAN_IP>`)
- Log error: `sudo tail -f /var/log/nginx/error.log`

### CSS Tidak Keluar Setelah HTTPS
- Jalankan: `php artisan down && php artisan up`
- Hard refresh browser (Ctrl + Shift + R)

### Device ID Tidak Ada Data
- Gunakan device valid: `FISH-00`
- Cek log backend: cek console IoT Node.js untuk daftar device aktif
