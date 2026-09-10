<?php

declare(strict_types=1);

namespace App\Tenancy\Services;

use App\Domain\Accounting\Models\Account;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Idempotent default COA seed. Requires TenantContext. */
final class DefaultChartOfAccountsProvisioner
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /** @var array<int, array{code: string, name: string, normal: string, level: int, parent_code: ?string}> */
    private array $dataset = [
        // Level 1
        ['code' => '1.0.00.00', 'name' => 'Aset',                  'normal' => 'D', 'level' => 1, 'parent_code' => null],
        ['code' => '2.0.00.00', 'name' => 'Utang',                 'normal' => 'C', 'level' => 1, 'parent_code' => null],
        ['code' => '3.0.00.00', 'name' => 'Modal',                 'normal' => 'C', 'level' => 1, 'parent_code' => null],
        ['code' => '4.0.00.00', 'name' => 'Pendapatan',            'normal' => 'C', 'level' => 1, 'parent_code' => null],
        ['code' => '5.0.00.00', 'name' => 'Beban',                 'normal' => 'D', 'level' => 1, 'parent_code' => null],
        // Level 2
        ['code' => '1.1.00.00', 'name' => 'Aset Lancar',           'normal' => 'D', 'level' => 2, 'parent_code' => '1.0.00.00'],
        ['code' => '1.2.00.00', 'name' => 'Aset Tidak Lancar',     'normal' => 'D', 'level' => 2, 'parent_code' => '1.0.00.00'],
        ['code' => '1.3.00.00', 'name' => 'Aset Lain-lain',        'normal' => 'D', 'level' => 2, 'parent_code' => '1.0.00.00'],
        ['code' => '2.1.00.00', 'name' => 'Utang Jangka Pendek',   'normal' => 'C', 'level' => 2, 'parent_code' => '2.0.00.00'],
        ['code' => '2.2.00.00', 'name' => 'Utang Jangka Panjang',  'normal' => 'C', 'level' => 2, 'parent_code' => '2.0.00.00'],
        ['code' => '3.1.00.00', 'name' => 'Modal Disetor',         'normal' => 'C', 'level' => 2, 'parent_code' => '3.0.00.00'],
        ['code' => '3.2.00.00', 'name' => 'Laba Rugi',             'normal' => 'C', 'level' => 2, 'parent_code' => '3.0.00.00'],
        ['code' => '4.1.00.00', 'name' => 'Pendapatan Usaha',      'normal' => 'C', 'level' => 2, 'parent_code' => '4.0.00.00'],
        ['code' => '4.2.00.00', 'name' => 'Pendapatan Non Usaha',  'normal' => 'C', 'level' => 2, 'parent_code' => '4.0.00.00'],
        ['code' => '4.3.00.00', 'name' => 'Pendapatan Luar biasa', 'normal' => 'C', 'level' => 2, 'parent_code' => '4.0.00.00'],
        ['code' => '5.1.00.00', 'name' => 'Beban Usaha',           'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
        ['code' => '5.2.00.00', 'name' => 'Beban Pemasaran',       'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
        ['code' => '5.3.00.00', 'name' => 'Beban Non Usaha',       'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
        ['code' => '5.4.00.00', 'name' => 'Beban Pajak',           'normal' => 'D', 'level' => 2, 'parent_code' => '5.0.00.00'],
        // Level 3
        ['code' => '1.1.01.00', 'name' => 'Kas',                                                  'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.1.02.00', 'name' => 'Kas Setara Kas',                                       'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.1.03.00', 'name' => 'Piutang',                                              'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang',                            'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.1.05.00', 'name' => 'Rekening antar Kantor ',                               'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.1.06.00', 'name' => 'Investasi',                                            'normal' => 'D', 'level' => 3, 'parent_code' => '1.1.00.00'],
        ['code' => '1.2.01.00', 'name' => 'Aktiva Tetap dan Inventaris',                          'normal' => 'D', 'level' => 3, 'parent_code' => '1.2.00.00'],
        ['code' => '1.2.02.00', 'name' => 'Akumulasi Penyusutan Aktiva Tetap dan Inventaris',     'normal' => 'D', 'level' => 3, 'parent_code' => '1.2.00.00'],
        ['code' => '1.2.03.00', 'name' => 'Aset Tak Berwujud',                                    'normal' => 'D', 'level' => 3, 'parent_code' => '1.2.00.00'],
        ['code' => '1.2.04.00', 'name' => 'Akumulasi Amortisasi Aset Tak Berwujud',              'normal' => 'D', 'level' => 3, 'parent_code' => '1.2.00.00'],
        ['code' => '1.2.05.00', 'name' => 'Konstruksi Dalam Pengerjaan',                         'normal' => 'D', 'level' => 3, 'parent_code' => '1.2.00.00'],
        ['code' => '1.3.01.00', 'name' => 'Aset Lain-lain',                                      'normal' => 'D', 'level' => 3, 'parent_code' => '1.3.00.00'],
        ['code' => '2.1.01.00', 'name' => 'Utang Bank',                                          'normal' => 'C', 'level' => 3, 'parent_code' => '2.1.00.00'],
        ['code' => '2.1.02.00', 'name' => 'Utang Biaya Operasional',                             'normal' => 'C', 'level' => 3, 'parent_code' => '2.1.00.00'],
        ['code' => '2.1.03.00', 'name' => 'Utang Pajak',                                         'normal' => 'C', 'level' => 3, 'parent_code' => '2.1.00.00'],
        ['code' => '2.1.04.00', 'name' => 'Utang Pembagian Laba',                                'normal' => 'C', 'level' => 3, 'parent_code' => '2.1.00.00'],
        ['code' => '2.1.05.00', 'name' => 'Utang Jangka Pendek Lainnya',                         'normal' => 'C', 'level' => 3, 'parent_code' => '2.1.00.00'],
        ['code' => '2.2.01.00', 'name' => 'Utang Bank',                                          'normal' => 'C', 'level' => 3, 'parent_code' => '2.2.00.00'],
        ['code' => '2.2.02.00', 'name' => 'Utang Jangka Panjang Lainnya',                        'normal' => 'C', 'level' => 3, 'parent_code' => '2.2.00.00'],
        ['code' => '3.1.01.00', 'name' => 'Modal Masyarakat dan Desa',                           'normal' => 'C', 'level' => 3, 'parent_code' => '3.1.00.00'],
        ['code' => '3.1.02.00', 'name' => 'Modal Lain-lain',                                     'normal' => 'C', 'level' => 3, 'parent_code' => '3.1.00.00'],
        ['code' => '3.2.01.00', 'name' => 'Laba Ditahan',                                        'normal' => 'C', 'level' => 3, 'parent_code' => '3.2.00.00'],
        ['code' => '3.2.02.00', 'name' => 'Laba Rugi Berjalan',                                  'normal' => 'C', 'level' => 3, 'parent_code' => '3.2.00.00'],
        ['code' => '4.1.01.00', 'name' => 'Pendapatan Usaha Utama',                              'normal' => 'C', 'level' => 3, 'parent_code' => '4.1.00.00'],
        ['code' => '4.1.02.00', 'name' => 'Pendapatan Usaha Lain',                               'normal' => 'C', 'level' => 3, 'parent_code' => '4.1.00.00'],
        ['code' => '4.2.01.00', 'name' => 'Pendapatan Non Usaha',                                'normal' => 'C', 'level' => 3, 'parent_code' => '4.2.00.00'],
        ['code' => '4.3.01.00', 'name' => 'Pendapatan Luar biasa',                               'normal' => 'C', 'level' => 3, 'parent_code' => '4.3.00.00'],
        ['code' => '5.1.01.00', 'name' => 'Beban Gaji dan Honor',                                'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.02.00', 'name' => 'Beban Tunjangan dan Bonus',                           'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.03.00', 'name' => 'Beban ATK dan Umum',                                  'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.04.00', 'name' => 'Beban Administarsi dan Umum Lainnya',                 'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.05.00', 'name' => 'Beban Rapat, Peningkatan Kapasitas',                   'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.06.00', 'name' => 'Transportasi dan Perjalanan Dinas',                    'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.07.00', 'name' => 'Beban Penyisihan, Penyusutan dan Amortisasi',         'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.08.00', 'name' => 'Beban Bunga Utang',                                   'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.1.09.00', 'name' => 'Beban Usaha Lainnya',                                'normal' => 'C', 'level' => 3, 'parent_code' => '5.1.00.00'],
        ['code' => '5.2.01.00', 'name' => 'Beban Pemasaran',                                     'normal' => 'C', 'level' => 3, 'parent_code' => '5.2.00.00'],
        ['code' => '5.3.01.00', 'name' => 'Beban Pajak bunga dan Administrasi Bank',              'normal' => 'C', 'level' => 3, 'parent_code' => '5.3.00.00'],
        ['code' => '5.3.02.00', 'name' => 'Beban Penghapusan Aset Tetap',                        'normal' => 'C', 'level' => 3, 'parent_code' => '5.3.00.00'],
        ['code' => '5.3.03.00', 'name' => 'Beban Kegiatan Sosial dan Masyarakat',                 'normal' => 'C', 'level' => 3, 'parent_code' => '5.3.00.00'],
        ['code' => '5.3.04.00', 'name' => 'Beban Non Usaha Lainnya',                            'normal' => 'C', 'level' => 3, 'parent_code' => '5.3.00.00'],
        ['code' => '5.4.01.00', 'name' => 'Beban Pajak',                                         'normal' => 'C', 'level' => 3, 'parent_code' => '5.4.00.00'],
        // Level 4 (from rekening_1, parent_code derived as X.Y.ZZ.00 from X.Y.ZZ.NN)
        ['code' => '1.1.01.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.01.00', 'name' => 'Kas Tunai'],
        ['code' => '1.1.01.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.01.00', 'name' => 'Kas Kecil'],
        ['code' => '1.1.01.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.01.00', 'name' => 'Kas di Bank Ops'],
        ['code' => '1.1.01.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.01.00', 'name' => 'Kas di Bank SPP'],
        ['code' => '1.1.01.05', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.01.00', 'name' => 'Kas di Bank Bumkalma'],
        ['code' => '1.1.01.06', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.01.00', 'name' => 'Kas di Bank Lainnya'],
        ['code' => '1.1.02.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.02.00', 'name' => 'Deposito'],
        ['code' => '1.1.02.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.02.00', 'name' => 'Saham'],
        ['code' => '1.1.02.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.02.00', 'name' => 'Obligasi'],
        ['code' => '1.1.03.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang Masyarakat SPP (Pokok)'],
        ['code' => '1.1.03.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang Masyarakat UEP (Pokok)'],
        ['code' => '1.1.03.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang Lembaga Lain (Pokok) '],
        ['code' => '1.1.03.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang Jasa SPP'],
        ['code' => '1.1.03.05', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang Jasa UEP'],
        ['code' => '1.1.03.06', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang Jasa Lembaga Lain'],
        ['code' => '1.1.03.07', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang Dividen'],
        ['code' => '1.1.03.08', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.03.00', 'name' => 'Piutang lain'],
        ['code' => '1.1.04.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang Pokok SPP'],
        ['code' => '1.1.04.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang Pokok UEP'],
        ['code' => '1.1.04.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang Pokok Lembaga Lain'],
        ['code' => '1.1.04.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang Jasa SPP'],
        ['code' => '1.1.04.05', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang Jasa UEP'],
        ['code' => '1.1.04.06', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang Jasa Lembaga Lain'],
        ['code' => '1.1.04.07', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.04.00', 'name' => 'Cadangan Kerugian Piutang Lain'],
        ['code' => '1.1.05.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.05.00', 'name' => 'Rekening antar Kantor (RK unit Usaha 1)'],
        ['code' => '1.1.05.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.05.00', 'name' => 'Rekening antar Kantor (RK unit Usaha 2)'],
        ['code' => '1.1.05.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.05.00', 'name' => 'Rekening antar Kantor (RK unit Usaha 3)'],
        ['code' => '1.1.06.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.06.00', 'name' => 'Investasi unit Usaha 1'],
        ['code' => '1.1.06.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.06.00', 'name' => 'Investasi unit Usaha 2'],
        ['code' => '1.1.06.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.1.06.00', 'name' => 'Investasi unit Usaha 3'],
        ['code' => '1.2.01.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.01.00', 'name' => 'Tanah'],
        ['code' => '1.2.01.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.01.00', 'name' => 'Gedung & Bangunan'],
        ['code' => '1.2.01.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.01.00', 'name' => 'Kendaraan dan Mesin'],
        ['code' => '1.2.01.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.01.00', 'name' => 'Inventaris/Peralatan'],
        ['code' => '1.2.02.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.02.00', 'name' => 'Akumulasi penyusutan Gedung dan Bangunan'],
        ['code' => '1.2.02.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.02.00', 'name' => 'Akumulasi penyusutan Kendaraan dan Mesin'],
        ['code' => '1.2.02.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.02.00', 'name' => 'Akumulasi penyusutan Inventaris/Peralatan'],
        ['code' => '1.2.03.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.03.00', 'name' => 'Biaya Pendirian Organisasi'],
        ['code' => '1.2.03.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.03.00', 'name' => 'Lisensi'],
        ['code' => '1.2.03.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.03.00', 'name' => 'Sewa dibayar dimuka'],
        ['code' => '1.2.03.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.03.00', 'name' => 'Asuransi dibayar dimuka'],
        ['code' => '1.2.04.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.04.00', 'name' => 'Akumulasi Amortisasi Biaya Pendirian Organisasi'],
        ['code' => '1.2.04.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.04.00', 'name' => 'Akumulasi Amortisasi Lisensi'],
        ['code' => '1.2.04.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.04.00', 'name' => 'Akumulasi Amortisasi Sewa dibayar dimuka'],
        ['code' => '1.2.04.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.04.00', 'name' => 'Akumulasi Amortisasi Asuransi dibayar dimuka'],
        ['code' => '1.2.05.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.2.05.00', 'name' => 'Konstruksi Dalam Pengerjaan dan Uang Muka'],
        ['code' => '1.3.01.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '1.3.01.00', 'name' => 'Aset Lain-lain'],
        ['code' => '2.1.01.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.01.00', 'name' => 'Utang Bank 1'],
        ['code' => '2.1.01.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.01.00', 'name' => 'Utang Bank 2'],
        ['code' => '2.1.02.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.02.00', 'name' => 'Utang Gaji'],
        ['code' => '2.1.02.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.02.00', 'name' => 'Utang Honor'],
        ['code' => '2.1.02.03', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.02.00', 'name' => 'Utang Tunjangan'],
        ['code' => '2.1.02.04', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.02.00', 'name' => 'Utang Bonus Prestasi Kerja'],
        ['code' => '2.1.02.05', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.02.00', 'name' => 'Utang Biaya Operasional lainnya'],
        ['code' => '2.1.03.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.03.00', 'name' => 'Utang Pajak'],
        ['code' => '2.1.04.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.04.00', 'name' => 'Utang Laba Bagian Masyarakat'],
        ['code' => '2.1.04.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.04.00', 'name' => 'Utang Laba Bagian Desa'],
        ['code' => '2.1.04.03', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.04.00', 'name' => 'Utang Laba Bagian Penyerta Modal'],
        ['code' => '2.1.05.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.1.05.00', 'name' => 'Utang Jangka Pendek Lainnya'],
        ['code' => '2.2.01.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.2.01.00', 'name' => 'Utang Bank 1'],
        ['code' => '2.2.01.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.2.01.00', 'name' => 'Dana Pensiuan'],
        ['code' => '2.2.02.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '2.2.02.00', 'name' => 'Utang Jangka Panjang Lainnya'],
        ['code' => '3.1.01.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.1.01.00', 'name' => 'Modal Masyarakat Desa (Eks. PNPM)'],
        ['code' => '3.1.01.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.1.01.00', 'name' => 'Modal Desa Pendiri'],
        ['code' => '3.1.01.03', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.1.01.00', 'name' => 'Modal Masyarakat'],
        ['code' => '3.1.02.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.1.02.00', 'name' => 'Modal Lain-lain'],
        ['code' => '3.2.01.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.2.01.00', 'name' => 'Laba Ditahan s/d Tahun lalu'],
        ['code' => '3.2.02.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '3.2.02.00', 'name' => 'Laba/Rugi Tahun Berjalan'],
        ['code' => '4.1.01.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00', 'name' => 'Pendapatan Jasa Piutang SPP'],
        ['code' => '4.1.01.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00', 'name' => 'Pendapatan Jasa Piutang UEP'],
        ['code' => '4.1.01.03', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00', 'name' => 'Pendapatan Jasa Piutang Lembaga Lain'],
        ['code' => '4.1.01.04', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00', 'name' => 'Pendapatan Denda Piutang SPP'],
        ['code' => '4.1.01.05', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00', 'name' => 'Pendapatan Denda Piutang UEP'],
        ['code' => '4.1.01.06', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.01.00', 'name' => 'Pendapatan Denda Piutang Lembaga Lain'],
        ['code' => '4.1.02.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.02.00', 'name' => 'Pendapatan Dividen Unit Usaha 1'],
        ['code' => '4.1.02.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.02.00', 'name' => 'Pendapatan Dividen Unit Usaha 2'],
        ['code' => '4.1.02.03', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.02.00', 'name' => 'Pendapatan Dividen Unit Usaha 3'],
        ['code' => '4.1.02.99', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.1.02.00', 'name' => 'Pendapatan Usaha Lainnya'],
        ['code' => '4.2.01.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00', 'name' => 'Pendapatan Bunga Bank'],
        ['code' => '4.2.01.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00', 'name' => 'Pendapatan Bunga Deposito'],
        ['code' => '4.2.01.03', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00', 'name' => 'Pendapatan Surat Berharga'],
        ['code' => '4.2.01.04', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00', 'name' => 'Pertambahan Nilai Penjualan Aset'],
        ['code' => '4.2.01.05', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00', 'name' => 'Pendapatan Hadiah'],
        ['code' => '4.2.01.06', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00', 'name' => 'Pendapatan Hibah'],
        ['code' => '4.2.01.07', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.2.01.00', 'name' => 'Pendapatan Non Usaha Lainnya'],
        ['code' => '4.3.01.01', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.3.01.00', 'name' => 'Pendapatan revaluasi Aset'],
        ['code' => '4.3.01.02', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.3.01.00', 'name' => 'Pendapatan revaluasi Saham'],
        ['code' => '4.3.01.03', 'normal' => 'C', 'level' => 4, 'parent_code' => '4.3.01.00', 'name' => 'Pendapatan lain-lain Lainnya'],
        ['code' => '5.1.01.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Gaji PO '],
        ['code' => '5.1.01.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Gaji Pegawai '],
        ['code' => '5.1.01.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Honor Verifikator'],
        ['code' => '5.1.01.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Honor Pengawas'],
        ['code' => '5.1.01.05', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Honor Penasihat'],
        ['code' => '5.1.01.06', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Honor Tim Penanganan Masalah'],
        ['code' => '5.1.01.07', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Honor Tim Pendanaan'],
        ['code' => '5.1.01.08', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.01.00', 'name' => 'Beban Honor Petugas Keamanan dan Kebersihan'],
        ['code' => '5.1.02.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00', 'name' => 'Beban Tunjangan Jabatan'],
        ['code' => '5.1.02.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00', 'name' => 'Beban Tunjangan Komunikasi'],
        ['code' => '5.1.02.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00', 'name' => 'Beban Tunjangan Hari Raya'],
        ['code' => '5.1.02.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00', 'name' => 'Beban Tunjangan Asuransi/BPJS'],
        ['code' => '5.1.02.05', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00', 'name' => 'Bonus Prestasi Kerja'],
        ['code' => '5.1.02.06', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.02.00', 'name' => 'Tunjangan Pensiun'],
        ['code' => '5.1.03.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.03.00', 'name' => 'Beban Administrasi dan Umum'],
        ['code' => '5.1.03.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.03.00', 'name' => 'Beban Listrik'],
        ['code' => '5.1.03.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.03.00', 'name' => 'Beban Internet'],
        ['code' => '5.1.03.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.03.00', 'name' => 'Beban Pemeliharaan & Perbaikan Aset'],
        ['code' => '5.1.04.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.04.00', 'name' => 'Konsumsi Kantor dan Tamu'],
        ['code' => '5.1.04.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.04.00', 'name' => 'Beban Iuran Organisasi'],
        ['code' => '5.1.04.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.04.00', 'name' => 'Beban Biaya Audit'],
        ['code' => '5.1.05.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.05.00', 'name' => 'Beban Rapat / MAD'],
        ['code' => '5.1.05.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.05.00', 'name' => 'Beban Peningkatan Kapasitas'],
        ['code' => '5.1.05.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.05.00', 'name' => 'Beban Pembinaan Kelompok Bermasalah'],
        ['code' => '5.1.06.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.06.00', 'name' => 'Beban Perjalanan Dinas'],
        ['code' => '5.1.06.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.06.00', 'name' => 'Beban Transportasi'],
        ['code' => '5.1.07.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyisihan Kerugian Piutang SPP'],
        ['code' => '5.1.07.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyisihan Kerugian Piutang UEP'],
        ['code' => '5.1.07.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyisihan Kerugian Piutang Lembaga Lain'],
        ['code' => '5.1.07.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyisihan Kerugian Piutang Jasa SPP'],
        ['code' => '5.1.07.05', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyisihan Kerugian Piutang Jasa UEP'],
        ['code' => '5.1.07.06', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyisihan Kerugian Piutang Jasa Lembaga Lain'],
        ['code' => '5.1.07.07', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyisihan Kerugian Piutang Lain'],
        ['code' => '5.1.07.08', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyusutan Gedung dan Bangunan'],
        ['code' => '5.1.07.09', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyusutan Kendaraan & Mesin'],
        ['code' => '5.1.07.10', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Penyusutan Inventaris'],
        ['code' => '5.1.07.11', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Amortisasi Biaya Pendirian Organisasi'],
        ['code' => '5.1.07.12', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Amortisasi Lisensi'],
        ['code' => '5.1.07.13', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Amortisasi Sewa dibayar dimuka'],
        ['code' => '5.1.07.14', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.07.00', 'name' => 'Beban Amortisasi Asuransi dibayar dimuka'],
        ['code' => '5.1.08.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.08.00', 'name' => 'Beban Bunga Utang Bank'],
        ['code' => '5.1.09.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.1.09.00', 'name' => 'Beban Usaha Lainnya'],
        ['code' => '5.2.01.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.2.01.00', 'name' => 'Beban IPTW'],
        ['code' => '5.2.01.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.2.01.00', 'name' => 'Beban Seragam PO dan Pegawai'],
        ['code' => '5.2.01.03', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.2.01.00', 'name' => 'Beban Spanduk/Papan Nama'],
        ['code' => '5.2.01.04', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.2.01.00', 'name' => 'Beban Pemasaran lainnya'],
        ['code' => '5.3.01.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.3.01.00', 'name' => 'Beban Pajak Bank'],
        ['code' => '5.3.01.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.3.01.00', 'name' => 'Beban Administrasi Bank'],
        ['code' => '5.3.02.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.3.02.00', 'name' => 'Beban Penghapusan Aset Tetap'],
        ['code' => '5.3.03.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.3.03.00', 'name' => 'Beban Sumbangan Kegiatan Kemasyarakatan'],
        ['code' => '5.3.03.02', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.3.03.00', 'name' => 'Beban Kegiatan Sosial'],
        ['code' => '5.3.04.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.3.04.00', 'name' => 'Beban Non Usaha Lainnya'],
        ['code' => '5.4.01.01', 'normal' => 'D', 'level' => 4, 'parent_code' => '5.4.01.00', 'name' => 'Taksiran PPh'],
    ];

    /**
     * @return array{inserted: int, skipped: int, settings_seeded: int}
     */
    public function ensureDefaults(bool $seedSettings = true): array
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context required to provision chart of accounts.');
        }

        $tenantId = $this->context->id();
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $byCode = [];
        $inserted = 0;
        $skipped = 0;

        DB::connection($connectionName)->transaction(function () use ($tenantId, &$byCode, &$inserted, &$skipped): void {
            foreach ($this->dataset as $row) {
                $existing = $this->existingAccount($tenantId, $row['code']);
                if ($existing !== null) {
                    $byCode[$row['code']] = (int) $existing['row_id'];
                    $skipped++;

                    continue;
                }

                $parentRowId = null;
                if ($row['parent_code'] !== null) {
                    if (! isset($byCode[$row['parent_code']])) {
                        $parent = $this->existingAccount($tenantId, $row['parent_code']);
                        if ($parent === null) {
                            throw new RuntimeException("Parent [{$row['parent_code']}] not seeded yet for [{$row['code']}].");
                        }
                        $byCode[$row['parent_code']] = (int) $parent['row_id'];
                    }
                    $parentRowId = $byCode[$row['parent_code']];
                }

                try {
                    $account = Account::query()->create([
                        'code' => $row['code'],
                        'name' => trim($row['name']),
                        'account_type' => $this->mapAccountType($row['code']),
                        'normal_balance' => $row['normal'],
                        'level' => $row['level'],
                        'is_postable' => $row['level'] === 4,
                        'is_active' => true,
                        'parent_row_id' => $parentRowId,
                        'legacy_parent_code' => $row['parent_code'],
                    ]);
                } catch (\Throwable $e) {
                    throw new RuntimeException("Failed to insert [{$row['code']}]: {$e->getMessage()}", previous: $e);
                }

                $byCode[$row['code']] = (int) $account->row_id;
                $inserted++;
            }
        });

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'settings_seeded' => $seedSettings ? $this->seedDefaultSettings($tenantId) : 0,
        ];
    }

    /** @return array{would_insert: int, would_skip: int} */
    public function preview(): array
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context required.');
        }

        $tenantId = $this->context->id();
        $wouldInsert = 0;
        $wouldSkip = 0;
        foreach ($this->dataset as $row) {
            if ($this->existingAccount($tenantId, $row['code']) !== null) {
                $wouldSkip++;
            } else {
                $wouldInsert++;
            }
        }

        return ['would_insert' => $wouldInsert, 'would_skip' => $wouldSkip];
    }

    public function reset(): int
    {
        if (! $this->context->isInitialized()) {
            throw new RuntimeException('Tenant context required.');
        }

        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');

        return DB::connection($connectionName)
            ->table('accounts')
            ->where('tenant_id', $this->context->id())
            ->delete();
    }

    private function existingAccount(int $tenantId, string $code): ?array
    {
        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $row = DB::connection($connectionName)->table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first(['row_id']);

        return $row === null ? null : (array) $row;
    }

    private function mapAccountType(string $code): string
    {
        return match (true) {
            str_starts_with($code, '1.') => 'asset',
            str_starts_with($code, '2.') => 'liability',
            str_starts_with($code, '3.') => 'equity',
            str_starts_with($code, '4.') => 'revenue',
            str_starts_with($code, '5.') => 'expense',
            default => 'unknown',
        };
    }

    private function seedDefaultSettings(int $tenantId): int
    {
        $defaults = [
            'account.pencairan_spp' => '1.1.03.01',
            'account.pencairan_uep' => '1.1.03.02',
            'account.pencairan_pl' => '1.1.03.03',
        ];

        $connectionName = (string) config('tenancy.tenant_connection', 'tenant');
        $now = now();
        $seeded = 0;

        foreach ($defaults as $key => $value) {
            $existing = DB::connection($connectionName)->table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('key', $key)
                ->exists();

            if ($existing) {
                continue;
            }

            DB::connection($connectionName)->table('tenant_settings')->insert([
                'tenant_id' => $tenantId,
                'key' => $key,
                'value' => $value,
                'value_type' => 'string',
                'is_encrypted' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $seeded++;
        }

        return $seeded;
    }
}
