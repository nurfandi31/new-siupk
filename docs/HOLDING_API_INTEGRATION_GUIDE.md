# Panduan Integrasi API Laporan Keuangan Holding (Holding Financial Reports API)

Dokumentasi ini adalah panduan teknis bagi pengembang aplikasi **Holding / BUMDesma Induk** untuk mengintegrasikan, mengonsumsi, dan mengonsolidasikan laporan keuangan dari seluruh unit usaha / anak perusahaan (tenant BUMDesma) yang berjalan di ekosistem **SIUPK Next**.

---

## 1. Gambaran Umum Arsitektur

SIUPK Next menyediakan RESTful API berstandar JSON yang dirancang khusus untuk komunikasi antar-server (*Server-to-Server / Machine-to-Machine*). Melalui API ini, sistem holding dapat:
1. **Menemukan Unit Usaha (Tenant Discovery)**: Mengambil daftar seluruh unit anak perusahaan beserta kode wilayah dan status aktifnya.
2. **Menarik Laporan Keuangan Individual**: Mengambil data Neraca, Laba Rugi, Arus Kas, CALK, dan Perubahan Ekuitas per anak usaha pada periode bulanan maupun tahunan.
3. **Mengambil Laporan Konsolidasi**: Mengambil kompilasi laporan keuangan gabungan dari seluruh anak usaha atau filter unit tertentu dalam satu request.
4. **Paket Laporan Lengkap (5-in-1 Pack)**: Mengambil kelima laporan akuntansi sekaligus dalam 1 kali roundtrip HTTP.

### Base URL & Prefix Rute
Semua endpoint terdaftar di bawah prefix API:
- **Rute Utama (Versioned)**: `https://domain-siupk.com/api/v1/holding/`
- **Alias Sederhana**: `https://domain-siupk.com/api/holding/`

---

## 2. Autentikasi & Keamanan

API Holding diamankan melalui middleware `VerifyHoldingApiToken` (`holding.auth`).

### 2.1 Konfigurasi Token pada SIUPK Next
Tambahkan token rahasia pada berkas `.env` aplikasi SIUPK Next:
```dotenv
HOLDING_API_KEY="kunci-rahasia-holding-anda-disini"
HOLDING_API_ENABLED=true
```

### 2.2 Mengirim Token dalam Request
Klien holding dapat mengirimkan token melalui salah satu metode berikut:

| Metode | Format | Rekomendasi |
|---|---|---|
| **Bearer Token (Header)** | `Authorization: Bearer <HOLDING_API_KEY>` | **Sangat Direkomendasikan** |
| **Custom Header** | `X-Holding-Key: <HOLDING_API_KEY>` atau `X-API-Key: <HOLDING_API_KEY>` | Alternatif |
| **Query Parameter** | `?api_key=<HOLDING_API_KEY>` | Untuk pengujian cepat |

> **Catatan Keamanan:** Pengguna yang sedang login sebagai *Superadmin*, *Supervisor Provinsi*, atau *Supervisor Kabupaten* dapat mengakses API tanpa token manual saat menggunakan browser/session yang terautentikasi.

---

## 3. Parameter Request & Resolusi Tenant

### 3.1 Identifikasi Anak Perusahaan (Tenant)
Untuk menarik laporan per anak usaha, identitas tenant dapat dikirim melalui:
1. **Route URL (Path Param)**: `/api/v1/holding/tenants/{tenant_code}/reports/balance-sheet`
2. **Query Parameter**: `?tenant={tenant_code}` atau `?tenant_id={tenant_row_id}`
3. **Header HTTP**: `X-Tenant-Code: {tenant_code}`

### 3.2 Parameter Periode Waktu
Setiap endpoint laporan menerima parameter periode:
- `year` *(integer, opsional, default: tahun berjalan)*: Contoh `2026` (rentang 2000 ? 2100).
- `month` *(integer|string, opsional)*:
  - `1` s/d `12`: Laporan bulanan (misal `month=8` untuk Agustus).
  - `all` / `0` / kosong: Laporan tahunan kumulatif (Januari ? Desember).

---

## 4. Spesifikasi Lengkap Endpoint API

### 4.1 Direktori Anak Perusahaan (Tenant Directory)

#### `GET /api/v1/holding/tenants`
Mengambil daftar seluruh anak perusahaan / unit usaha aktif.

**Query Parameters (Opsional):**
- `province_code`: Filter kode provinsi (contoh `32`).
- `regency_code`: Filter kode kabupaten/kota (contoh `3201`).
- `district_code`: Filter kode kecamatan.
- `search`: Pencarian nama atau kode tenant.

**Contoh Response:**
```json
{
  "status": "success",
  "meta": {
    "total": 2,
    "generated_at": "2026-08-20T12:09:30+07:00"
  },
  "data": [
    {
      "id": 1,
      "code": "bumdesma-mandiri",
      "name": "BUMDesma Mandiri Sejahtera",
      "status": "active",
      "district_code": "320101",
      "regency_code": "3201",
      "regency_name": "Kabupaten Bogor",
      "province_code": "32",
      "shard": "local",
      "created_at": "2026-08-14T21:49:55+07:00"
    }
  ]
}
```

#### `GET /api/v1/holding/tenants/{tenant}`
Mengambil detail informasi profil satu anak perusahaan berdasarkan kode atau ID.

---

### 4.2 Laporan Neraca (Balance Sheet)

#### `GET /api/v1/holding/reports/balance-sheet`
#### `GET /api/v1/holding/tenants/{tenant}/reports/balance-sheet`

Menyajikan laporan posisi keuangan yang terstruktur dalam pohon hierarki Chart of Accounts (Level 1 Header ? Level 2 Kelompok ? Level 3 Akun Rincian beserta saldo).

**Struktur Data `data.sections`:**
- **Level 1**: Header Akun Utama (`Aset`, `Utang`, `Modal`) beserta total saldo grup.
- **Level 2**: Sub-kelompok Akun (`Aset Lancar`, `Aset Tidak Lancar`, `Utang Jangka Pendek`, `Modal Disetor`, `Laba Rugi`).
- **Level 3**: Rincian akun *postable* dan saldo bersihnya per akhir periode (`as_of`).

**Contoh Response:**
```json
{
  "status": "success",
  "meta": {
    "report": "balance_sheet",
    "report_title": "Laporan Neraca",
    "scope": "single_tenant",
    "tenant": {
      "id": 1,
      "code": "bumdesma-mandiri",
      "name": "BUMDesma Mandiri Sejahtera",
      "district_code": "320101",
      "regency_code": "3201",
      "regency_name": "Kabupaten Bogor",
      "province_code": "32"
    },
    "period": {
      "year": 2026,
      "month": 8,
      "as_of": "2026-08-31",
      "from": "2026-08-01",
      "until_exclusive": "2026-09-01",
      "period_label": "Agustus 2026",
      "is_monthly": true
    },
    "generated_at": "2026-08-20T12:09:38+07:00"
  },
  "data": {
    "sections": [
      {
        "code": "1.0.00.00",
        "name": "Aset",
        "level": 1,
        "account_type": "asset",
        "balance": 2147839317.34,
        "children": [
          {
            "code": "1.1.00.00",
            "name": "Aset Lancar",
            "level": 2,
            "children": [
              {
                "code": "1.1.01.00",
                "name": "Kas",
                "level": 3,
                "balance": 119904935.20
              },
              {
                "code": "1.1.03.00",
                "name": "Piutang",
                "level": 3,
                "balance": 2025343000.00
              },
              {
                "code": "1.1.04.00",
                "name": "Cadangan Kerugian Piutang",
                "level": 3,
                "balance": -25345985.00
              }
            ]
          },
          {
            "code": "1.2.00.00",
            "name": "Aset Tidak Lancar",
            "level": 2,
            "children": [
              {
                "code": "1.2.01.00",
                "name": "Aktiva Tetap dan Inventaris",
                "level": 3,
                "balance": 90148400.00
              },
              {
                "code": "1.2.02.00",
                "name": "Akumulasi Penyusutan Aktiva Tetap",
                "level": 3,
                "balance": -61144290.86
              }
            ]
          }
        ]
      },
      {
        "code": "2.0.00.00",
        "name": "Utang",
        "level": 1,
        "account_type": "liability",
        "balance": 33178660.00,
        "children": [...]
      },
      {
        "code": "3.0.00.00",
        "name": "Modal",
        "level": 1,
        "account_type": "equity",
        "balance": 2114660657.34,
        "children": [...]
      }
    ],
    "totals": {
      "assets": 2147839317.34,
      "liabilities_equity": 2147839317.34,
      "net_income": 21253597.31
    },
    "balanced": true
  }
}
```

---

### 4.3 Laporan Laba Rugi (Income Statement / Profit & Loss)

#### `GET /api/v1/holding/reports/income-statement`
#### `GET /api/v1/holding/tenants/{tenant}/reports/income-statement`

Menyajikan pendapatan operasional, pendapatan non-operasional, beban operasional, beban non-operasional, dan taksiran pajak lengkap dengan perbandingan 3 kolom waktu:
- `prior`: Nilai pada periode sebelumnya (Bulan Lalu / Tahun Lalu).
- `current`: Nilai transaksi mutasi pada periode berjalan (Bulan Ini).
- `ytd`: Akumulasi *Year-to-Date* dari awal tahun s/d akhir periode yang dipilih.

**Contoh Response:**
```json
{
  "status": "success",
  "meta": {
    "report": "income_statement",
    "report_title": "Laporan Laba Rugi",
    "scope": "single_tenant",
    "period": { ... }
  },
  "data": {
    "header_lalu": "Bulan Lalu",
    "header_sekarang": "Bulan Ini",
    "groups": [
      {
        "code": "4.1.00.00",
        "name": "Pendapatan Usaha",
        "account_type": "revenue",
        "bucket": "revenue_ops",
        "prior": 157420000.00,
        "current": 110000.00,
        "ytd": 157530000.00,
        "children": [
          {
            "row_id": 130,
            "code": "4.1.01.01",
            "name": "Pendapatan Jasa Piutang SPP",
            "prior": 133150000.00,
            "current": 0.00,
            "ytd": 133150000.00
          },
          {
            "row_id": 133,
            "code": "4.1.01.04",
            "name": "Pendapatan Denda Piutang SPP",
            "prior": 15870000.00,
            "current": 110000.00,
            "ytd": 15980000.00
          }
        ]
      }
    ],
    "summary": {
      "operating": { "prior": 21552797.31, "current": 110000.00, "ytd": 21662797.31 },
      "non_operating": { "prior": -409200.00, "current": 0.00, "ytd": -409200.00 },
      "before_tax": { "prior": 21143597.31, "current": 110000.00, "ytd": 21253597.31 },
      "tax": { "prior": 0.00, "current": 0.00, "ytd": 0.00 },
      "after_tax": { "prior": 21143597.31, "current": 110000.00, "ytd": 21253597.31 }
    }
  }
}
```

---

### 4.4 Laporan Arus Kas (Cash Flow)

#### `GET /api/v1/holding/reports/cash-flow`
#### `GET /api/v1/holding/tenants/{tenant}/reports/cash-flow`

Menyajikan arus kas masuk dan keluar metode langsung (*direct method*) berdasarkan klasifikasi jurnal posted yang menyentuh akun kas/bank (`1.1.01.*`).

**Struktur Data:**
- `cash_accounts`: Daftar seluruh akun kas & bank yang dipantau.
- `opening_cash`: Saldo kas pada awal periode.
- `closing_cash`: Saldo kas riil pada akhir periode.
- `net_change`: Total perubahan kas bersih selama periode.
- `sections`: Pengelompokan baris transaksi menjadi 3 pilar:
  1. `operating`: Arus kas dari aktivitas operasi (angsuran, pendapatan jasa, denda, beban operasional).
  2. `investing`: Arus kas dari aktivitas investasi (pembelian inventaris/aset tetap).
  3. `financing`: Arus kas dari aktivitas pendanaan (setoran modal, penarikan, pinjaman modal).
- `reconciled`: Status rekonsiliasi matematis (`opening_cash + net_change == closing_cash`).

---

### 4.5 Catatan Atas Laporan Keuangan (CALK)

#### `GET /api/v1/holding/reports/calk`
#### `GET /api/v1/holding/tenants/{tenant}/reports/calk`

Menyajikan ringkasan eksekutif indikator keuangan, kebijakan akuntansi pokok (basis akrual SAK ETAP), serta catatan narasi tambahan dari manajemen.

**Struktur Data `data.highlights`:**
- Laba (rugi) bersih YTD
- Total aset
- Total liabilitas dan ekuitas
- Saldo kas akhir
- Perubahan kas bersih periode

---

### 4.6 Laporan Perubahan Ekuitas (Changes in Equity)

#### `GET /api/v1/holding/reports/equity-changes`
#### `GET /api/v1/holding/tenants/{tenant}/reports/equity-changes`

Menyajikan pergerakan modal pemilik/masyarakat, laba ditahan, dan laba tahun berjalan dari saldo awal menjadi saldo akhir.

**Struktur Data:**
- `rows`: Rincian per akun modal dengan kolom `opening`, `movement`, dan `closing`.
- `summary`:
  - `opening_total`: Total ekuitas awal periode.
  - `period_net_income`: Laba/rugi bersih yang dihasilkan periode ini.
  - `other_equity_movement`: Mutasi setoran modal / penyesuaian lain.
  - `closing_total`: Total ekuitas akhir periode.
- `bridge`: Baris jembatan rekonsiliasi (*equity bridge*).

---

### 4.7 Paket Lengkap Laporan Keuangan (5-in-1 Pack)

#### `GET /api/v1/holding/reports/pack`
#### `GET /api/v1/holding/tenants/{tenant}/reports/pack`

Mengembalikan seluruh 5 laporan akuntansi secara lengkap dalam satu kali panggilan API:
```json
{
  "status": "success",
  "meta": {
    "report": "financial_report_pack",
    "report_title": "Paket Laporan Keuangan Lengkap",
    "scope": "single_tenant",
    "tenant": { ... },
    "period": { ... }
  },
  "data": {
    "balance_sheet": { ... },
    "income_statement": { ... },
    "cash_flow": { ... },
    "equity_changes": { ... },
    "calk": { ... }
  }
}
```

---

### 4.8 Laporan Konsolidasi Holding (Multi-Tenant)

Endpoint konsolidasi menggabungkan data keuangan seluruh anak perusahaan di bawah holding:
- `GET /api/v1/holding/reports/consolidated/balance-sheet`
- `GET /api/v1/holding/reports/consolidated/income-statement`
- `GET /api/v1/holding/reports/consolidated/cash-flow`
- `GET /api/v1/holding/reports/consolidated/equity-changes`
- `GET /api/v1/holding/reports/consolidated/calk`
- `GET /api/v1/holding/reports/consolidated/pack`

**Parameter Filter Konsolidasi:**
- `tenant_ids`: Daftar ID anak perusahaan yang ingin dikonsolidasikan (contoh: `tenant_ids=1,2,3`). Jika tidak diisi, akan mengonsolidasikan seluruh anak perusahaan aktif.
- `province_code`: Filter konsolidasi per wilayah provinsi.
- `regency_code`: Filter konsolidasi per wilayah kabupaten.

---

## 5. Contoh Implementasi Klien (Code Snippets)

### 5.1 Contoh cURL
```bash
curl -X GET "https://app-siupk.com/api/v1/holding/reports/balance-sheet?tenant=bumdesma-mandiri&year=2026&month=8" \
     -H "Authorization: Bearer YOUR_HOLDING_API_KEY" \
     -H "Accept: application/json"
```

### 5.2 Contoh PHP (Laravel Http Client)
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken(config('services.siupk.holding_key'))
    ->get('https://app-siupk.com/api/v1/holding/reports/pack', [
        'tenant' => 'bumdesma-mandiri',
        'year' => 2026,
        'month' => 8,
    ]);

if ($response->successful()) {
    $reportPack = $response->json('data');
    $balanceSheet = $reportPack['balance_sheet'];
    $netIncome = $balanceSheet['totals']['net_income'];
}
```

### 5.3 Contoh TypeScript / JavaScript (Axios / Fetch)
```typescript
import axios from 'axios';

const siupkApi = axios.create({
  baseURL: 'https://app-siupk.com/api/v1/holding',
  headers: {
    'Authorization': `Bearer ${process.env.SIUPK_HOLDING_API_KEY}`,
    'Accept': 'application/json',
  },
});

export async function fetchHoldingBalanceSheet(tenantCode: string, year: number, month?: number) {
  const { data } = await siupkApi.get('/reports/balance-sheet', {
    params: { tenant: tenantCode, year, month },
  });
  return data;
}
```

### 5.4 Contoh Python (Requests)
```python
import os
import requests

url = "https://app-siupk.com/api/v1/holding/reports/balance-sheet"
headers = {
    "Authorization": f"Bearer {os.getenv('SIUPK_HOLDING_API_KEY')}",
    "Accept": "application/json",
}
params = {
    "tenant": "bumdesma-mandiri",
    "year": 2026,
    "month": 8,
}

response = requests.get(url, headers=headers, params=params)
data = response.json()
print("Total Aset:", data['data']['totals']['assets'])
```

---

## 6. Tabel Status Code HTTP

| Status Code | Kondisi | Keterangan Respon |
|---|---|---|
| **200 OK** | Sukses | Mengembalikan data laporan berformat JSON. |
| **401 Unauthorized** | Token Salah / Tidak Ada | `{"status": "error", "message": "Unauthorized. Invalid or missing Holding API token."}` |
| **403 Forbidden** | API Dinonaktifkan | `{"status": "error", "message": "Holding API service is currently disabled."}` |
| **404 Not Found** | Tenant Tidak Ditemukan | `{"status": "error", "message": "Holding subsidiary / tenant not found. Please provide a valid tenant code or ID."}` |
| **500 Error** | Kesalahan Server Shard | Kesalahan koneksi shard database atau kalkulasi akuntansi. |
