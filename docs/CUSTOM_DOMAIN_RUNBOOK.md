# Runbook domain kustom tenant — DNS & TLS untuk PublicSite

Cakupan: bagaimana 1 tenant punya domain sendiri (mis. `bumdes-sukamaju.test` / `danasukamaju.or.id`) yang menyajikan **PublicSite ber-branding tenant** (`PublicSite/TenantHome`, `/berita`, `/halaman/{slug}`, `/kontak`, `sitemap.xml`, `robots.txt`) — bukan halaman marketing vendor (`Home`).

Arsitektur terkait: `ResolvePublicSite` → `PublicSiteResolver` → `TenantContext` → `PublicSiteController`. Status di `docs/PROJECT_OVERVIEW.md` dan `docs/ASSISTANT_INTEGRATION.md` tidak berubah; dokumen ini hanya menambah prosedur operasional.

## 1) Bagaimana resolusi host bekerja

```
Request Host ──► ResolvePublicSite middleware
                   ├─ RESERVED_HOSTS: localhost, 127.0.0.1, ::1, host.docker.internal
                   ├─ app.url host + config('site.platform_hosts')  → platform (null)
                   └─ Tenant::matchesHost(metadata.domains) → tenant aktif/read_only
                        ├─ resolved  → connect(shard) + context->initialize(T,P,S) → next() → finally { clear(); disconnect(); }
                        └─ null      → context tetap uninitialized → controller render vendor Home
```

- Cache: `PublicSiteResolver` cache versioned (TTL 300s). `flush()` bump version — dipanggil saat `metadata.domains` diubah.
- Suspended tenant → `resolveTenantSite()` return null → vendor Home (sama seperti host tidak dikenal).
- Di test: `BuildsTenantTestDatabase` meninggalkan context ter-init di setUp; `activateTenantDomain()` = `forceFill(metadata.domains)` + `flush()` + `clearTenantTestContext()`.

## 2) Prasyarat

| Item | Cek |
|---|---|
| Tenant aktif (`status=active` atau `read_only`) | `tenants.code`, `tenant_registry.status` |
| `metadata.domains` berisi FQDN yang diminta | `SELECT metadata FROM tenant_registry WHERE code=:code` |
| DNS sudah mengarah ke platform | `dig` / `nslookup` (lihat §3) |
| TLS valid untuk FQDN | §4 |
| Shard migrasi `site_settings`/`site_messages` sudah jalan | `tenancy:migrate-shards` (Fase 3: `2026_09_03_000003`) |
| `site.platform_hosts` & `app.url` tidak tumpang tindih dengan domain tenant | `config/site.php`, `.env: APP_URL` |

## 3) DNS

### 3a. Tenant pakai subdomain platform (paling sederhana)

Contoh: platform `siupk.or.id` (A → VPS), tenant `bumdes-sukamaju.siupk.or.id`.

- Tambahkan wildcard `*.siupk.or.id` CNAME/A → platform, ATAU tambah per-tenant A/CNAME. Wildcard cukup untuk semua tenant yang tetap di bawah domain platform.
- Tidak perlu verifikasi kepemilikan domain di sisi tenant.

### 3b. Domain kustom penuh (mis. `bumdessukamaju.or.id`)

Tenant punya domain sendiri — vendor hanya mengarahkan.

1. Di admin platform: set `tenant_registry.metadata.domains = ["bumdessukamaju.or.id", "www.bumdessukamaju.or.id"]` (simpan + `PublicSiteResolver::flush()`).
2. Instruksikan tenant untuk buat record di DNS mereka:

| Tipe | Host | Target | TTL |
|---|---|---|---|
| **A** | `@` (apex) | IP publik VPS platform | 300 |
| **A** | `www` | IP yang sama | 300 |
| — ATAU — | | | |
| **CNAME** | `www` | `siupk.or.id` | 300 |

> Apex (`@`) tidak boleh CNAME per RFC — pakai **A** (atau ALIAS/ANAME jika provider mendukung). Subdomain (`www`, `bumdes`…) boleh CNAME.

3. Verifikasi propagasi:

```bash
dig +short A bumdessukamaju.or.id
dig +short CNAME www.bumdessukamaju.or.id
# Harus resolve ke IP/CNAME platform; tunggu TTL jika masih NXDOMAIN.
```

4. Verifikasi di aplikasi:

```bash
curl -I -H "Host: bumdessukamaju.or.id" https://siupk.or.id/          # via platform host + Host header (smoke tanpa DNS)
curl -I https://bumdessukamaju.or.id/                                  # setelah DNS jadi
curl -s https://bumdessukamaju.or.id/sitemap.xml | head
curl -s https://bumdessukamaju.or.id/robots.txt
```

- `/` tenant → `PublicSite/TenantHome` (bukan `Home` vendor).
- `/kontak` → `PublicSite/Contact`.
- `/sitemap.xml` memuat `/berita`, post & page published.
- Host platform (`localhost`/`siupk.or.id`) ke `/kontak` tetap vendor `Home` — ini expected (test `test_public_contact_page_falls_back_to_vendor_home_on_platform_host`).

### 3c. Lokal / Laragon (dev)

```text
C:\Windows\System32\drivers\etc\hosts
127.0.0.1  bumdes-sukamaju.test
```

- Laragon: **Menu → Apache → SSL → Enable** (opsional, mkcert).
- Atau tetap HTTP di dev; `ResolvePublicSite` tidak peduli skema, hanya `Host`.
- `RESERVED_HOSTS` tidak akan pernah di-resolve sebagai tenant — jangan pakai `localhost` sebagai domain tenant.

## 4) TLS

> Jangan pernah serve domain kustom tanpa TLS valid — browser akan block dan SEO hancur.

### Opsi A — Reverse proxy dengan TLS otomatis (disarankan untuk banyak domain kustom)

Pakai **Caddy** di depan Laravel (ganti/letakkan di depan nginx/Apache Laragon di prod).

```caddyfile
# Caddyfile
{
    on_demand_tls {
        ask http://127.0.0.1:8000/internal/tls-ask
    }
}
:443 {
    tls {
        on_demand
    }
    reverse_proxy 127.0.0.1:8000
}
```

- Caddy minta sertifikat ACME (Let's Encrypt) on-demand saat SNI pertama kali.
- Endpoint `GET /internal/tls-ask?domain=bumdessukamaju.or.id` harus return 200 hanya jika `PublicSiteResolver::resolve(domain)` menemukan tenant aktif (anti-abuse). Implementasi minimal: controller internal yang cek `Tenant::matchesHost()` tanpa init context; return 200/404.
- Renewal otomatis oleh Caddy.

### Opsi B — nginx + certbot per domain

```bash
# Sekali per domain kustom baru
certbot certonly --nginx -d bumdessukamaju.or.id -d www.bumdessukamaju.or.id --deploy-hook "nginx -s reload"
# Cron/ systemd timer sudah ada dari paket certbot — cek:
certbot renew --dry-run
```

- nginx `server_name bumdessukamaju.or.id www.bumdessukamaju.or.id;` → `proxy_pass` ke app (satu vhost per domain ATAU satu wildcard vhost dengan banyak server_name; yang pertama lebih eksplisit).
- HSTS (opsional, setelah yakin):
  ```nginx
  add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
  ```

### Checklist TLS

- [ ] `https://<domain>/` tanpa warning
- [ ] `http://<domain>/` 301 → `https://`
- [ ] `https://<domain>/sitemap.xml` 200 `Content-Type: application/xml`
- [ ] `https://<domain>/robots.txt` berisi `Disallow: /website` & `Sitemap:`
- [ ] Renewal job aktif (`caddy` otomatis / `certbot renew --dry-run` lolos)

## 5) Prosedur tambah domain kustom (operator)

1. Tenant ajukan domain (mis. via admin platform / support ticket).
2. Operator set `metadata.domains` + `PublicSiteResolver::flush()`.
3. Kirim instruksi DNS ke tenant (§3b).
4. Tunggu propagasi (`dig`).
5. Issue TLS (§4 — Caddy otomatis, atau `certbot` manual + reload).
6. Smoke curl/browser (§3b langkah 4).
7. Catat di `docs/TEST_AUDIT_LOG.md` / CHANGELOG jika perlu.

Durasi tipikal: DNS 5–30 menit (tergantung TTL), TLS <2 menit setelah DNS jadi, cache resolver ≤5 menit.

## 6) Rollback

| Skenario | Tindakan |
|---|---|
| DNS salah / belum propagasi | Tenant tetap dilayani di platform host / subdomain lama; tidak ada downtime app. Perbaiki record, tunggu TTL. |
| TLS gagal issue | Serve HTTP 301 belum aktif — domain tetap HTTP sampai cert jadi; jangan force HSTS sebelum cert valid. |
| Domain perlu ditarik | Hapus dari `metadata.domains`, `flush()`, cabut vhost/cert (`certbot delete --cert-name <domain>` atau hapus dari Caddy ask-allowlist). Request ke domain akan fallback ke vendor `Home` (aman, bukan 500). |

## 7) Troubleshooting

| Gejala | Penyebab umum | Periksa |
|---|---|---|
| Domain kustom masih tampil vendor `Home` | DNS belum propagasi / `metadata.domains` salah / cache 300s belum flush | `dig`, `SELECT metadata FROM tenant_registry`, `PublicSiteResolver::flush()` |
| `/kontak` 429 | Rate limit `throttle:10,1` di route kontak | Normal — tunggu 1 menit; bot honeypot `website` terisi juga 200 palsu (sengaja) |
| `sitemap.xml` kosong (hanya `/`) | Tenant suspended atau tidak ada post/page published | `tenants.status`, `site_posts.status='published'` |
| Mixed content / ikon tidak load | `APP_URL` masih `http` | Set `APP_URL=https://siupk.or.id`, `ASSET_URL` jika pakai CDN |
| Cert error `ERR_CERT_COMMON_NAME_INVALID` | nginx server_name tidak mencakup domain / Caddy ask endpoint return 404 | `nginx -T`, log Caddy, endpoint `/internal/tls-ask` |

## 8) Referensi kode

- `app/Tenancy/Services/PublicSiteResolver.php` — `RESERVED_HOSTS`, `resolve()`, `flush()`
- `app/Tenancy/Middleware/ResolvePublicSite.php` — `finally { clear(); disconnect(); }`
- `app/Http/Controllers/PublicSite/PublicSiteController.php` — `resolveTenantSite()`, `resolveSettings()`, `sitemap()`, `robots()`
- `app/Tenancy/TenantContext.php` — `isInitialized()`, `id()`, `tenant()`
- `tests/Feature/Website/WebsiteSettingsAndMessagesTest.php` — 20 test / 151 assertions (Fase 3)

> Cabang saat ini: `feat/public-tenant-site` — belum merge ke `main` (live). Merge hanya setelah checklist §4 lolos di staging.
