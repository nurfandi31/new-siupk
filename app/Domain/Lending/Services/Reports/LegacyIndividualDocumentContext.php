<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Lending\Models\InstallmentSystem;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Models\LoanPayment;
use App\Domain\Lending\Services\MemberLoanScheduleCalculator;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\Tenant\VillageNaming;
use App\Support\Code128Barcode;
use App\Utils\Keuangan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Membangun object graph "legacy" (`$pinkel`, `$kec`, `$kab`, `$dir`, `$real`, `$ra`, ...)
 * dari model `Loan` modern, supaya template dokumen SIUPK asli dapat dirender 1:1.
 *
 * Template sumber: `siupk/resources/views/perguliran_i/dokumen/*`.
 */
final class LegacyIndividualDocumentContext
{
    public function __construct(
        private readonly MemberLoanScheduleCalculator $scheduleCalculator,
    ) {}

    /** @var array<int, array{name: string, description: string, interval: int}> */
    private const SYSTEM_FALLBACK = [
        1 => ['name' => 'Bulanan', 'description' => 'satu kali dalam sebulan', 'interval' => 1],
        2 => ['name' => '3 Bulan', 'description' => 'satu kali dalam tiga bulan', 'interval' => 3],
        3 => ['name' => '4 Bulan', 'description' => 'satu kali dalam empat bulan', 'interval' => 4],
        4 => ['name' => '6 Bulan', 'description' => 'satu kali dalam enam bulan', 'interval' => 6],
        5 => ['name' => 'Musiman', 'description' => 'sesuai pola pembayaran', 'interval' => 1],
        6 => ['name' => '5 Bulan', 'description' => 'Satu kali dalam lima bulan', 'interval' => 5],
        7 => ['name' => '7 Bulan', 'description' => 'Satu kali dalam tujuh bulan', 'interval' => 7],
        8 => ['name' => '8 Bulan', 'description' => 'Satu kali dalam delapan bulan', 'interval' => 8],
        9 => ['name' => '2 Bulan', 'description' => 'Satu kali dalam dua bulan', 'interval' => 2],
        10 => ['name' => '12 Bulan', 'description' => 'Satu kali dalam satu Tahun', 'interval' => 12],
        11 => ['name' => 'M24', 'description' => 'Satu kali dalam satu bulan setelah 2 tahun', 'interval' => 1],
        12 => ['name' => 'Mingguan', 'description' => 'satu minggu sekali', 'interval' => 1],
        13 => ['name' => '9 Bulan', 'description' => 'Satu kali dalam sembilan bulan', 'interval' => 9],
        14 => ['name' => 'M3', 'description' => 'Pokok ditunda 3 bulan setelah cair', 'interval' => 1],
        15 => ['name' => 'M2', 'description' => 'Pokok ditunda 2 bulan setelah cair', 'interval' => 1],
        16 => ['name' => '24 Bulan', 'description' => 'Pokok 24 bulan sekali', 'interval' => 24],
        20 => ['name' => 'M12', 'description' => 'Pokok ditunda 12 bulan setelah cair', 'interval' => 1],
        21 => ['name' => '10 Bulan', 'description' => 'Satu kali dalam 10 bulan', 'interval' => 10],
        22 => ['name' => '24 Bulan', 'description' => 'Satu kali dalam Dua Tahun', 'interval' => 24],
        23 => ['name' => '36 Bulan', 'description' => 'Satu kali dalam Tiga Tahun', 'interval' => 36],
        24 => ['name' => '11 Bulan', 'description' => 'Satu kali dalam 11 bulan', 'interval' => 11],
        25 => ['name' => 'M1', 'description' => 'Angsuran ditunda 1 bulan setelah cair', 'interval' => 1],
        26 => ['name' => 'M6', 'description' => 'Pokok ditunda 6 bulan setelah cair', 'interval' => 1],
    ];

    /** Nama method legacy untuk footer/header base layout. */
    private const LEGACY_REPORT_NAMES = [
        'cover_proposal_individu' => 'coverProposal',
        'pengajuan_kredit_individu' => 'suratPengajuanPinjaman',
        'rekomendasi_kredit_individu' => 'suratRekomendasi',
        'pernyataan_peminjam_individu' => 'pernyataanPeminjam',
        'form_verifikasi_individu' => 'formVerifikasi',
        'form_verifikasi_individu_pencairan' => 'formVerifikasi',
        'rencana_angsuran_individu' => 'rencanaAngsuran',
        'rencana_angsuran_individu_proposal' => 'rencanaAngsuran',
        'surat_persetujuan_kuasa_individu' => 'SuratPersetujuanKuasa',
        'tanda_terima_jaminan' => 'tandaTerimaJaminan',
        'cover_pencairan_individu' => 'coverPencairan',
        'spk_individu' => 'spk',
        'ba_pencairan_individu' => 'BaPencairan',
        'kuitansi_pencairan_individu' => 'kuitansi',
        'kartu_angsuran_individu' => 'kartuAngsuran',
        'analisis_keputusan_kredit' => 'analisiskeputusankredit',
        'surat_pemberitahuan' => 'suratpemberitahuan',
        'pengikat_diri_penjamin' => 'pengikatdirisebagaipenjamin',
        'surat_pernyataan_suami' => 'suratpernyataansuami',
        'tagihan_individu' => 'suratTagihan',
        'surat_kelayakan_individu' => 'suratKelayakan',
        'daftar_hadir_pencairan_individu' => 'daftarHadirPencairan',
        'pemberitahuan_desa_individu' => 'pemberitahuanDesa',
        'surat_ahli_waris_individu' => 'suratAhliWaris',
        'bukti_pengembalian_jaminan' => 'Pengembalian',
        'rekomendasi_verifikator_individu' => 'RekomendasiVerifikator',
    ];

    /**
     * @param  array<string, string>  $tokens
     * @return array<string, mixed>
     */
    public function build(Loan $loan, string $documentKey, string $signatureHtml = '', array $tokens = []): array
    {
        $loan->loadMissing([
            'product',
            'borrower.member.person',
            'borrower.member.address',
            'borrower.member.business',
            'borrower.member.village.villageNaming',
            'borrower.member.village.parent',
            'borrower.member.guarantor.person',
            'borrower.member.groupMemberships.group.activeOfficers.member.person',
            'installments',
            'payments.allocations',
            'statusHistories',
        ]);

        $profile = OrganizationProfile::query()->first();
        $rounding = (string) ($profile?->installment_rounding ?? '5000');
        $keuangan = new Keuangan($rounding === '' ? '5000' : $rounding);

        $member = $loan->borrower?->member;
        $person = $member?->person;
        $village = $member?->village;
        $naming = $village?->villageNaming;
        $address = $member?->address;
        $guarantor = $member?->guarantor?->person;
        $business = $member?->business;

        // Kelompok aktif anggota (dipakai surat kuasa, daftar hadir, dsb).
        $groupMembership = $member?->groupMemberships
            ?->filter(static fn ($membership): bool => (string) $membership->status === 'active' && $membership->left_at === null)
            ->sortByDesc(static fn ($membership): string => $membership->joined_at?->toDateString() ?? '')
            ->first();
        $group = $groupMembership?->group;
        $groupOfficers = $group?->activeOfficers?->keyBy('position') ?? collect();
        $chairName = (string) ($groupOfficers->get('chair')?->member?->person?->full_name ?? '');
        $secretaryName = (string) ($groupOfficers->get('secretary')?->member?->person?->full_name ?? '');
        $treasurerName = (string) ($groupOfficers->get('treasurer')?->member?->person?->full_name ?? '');

        $histories = $loan->statusHistories;
        $draftHistory = $histories->firstWhere('to_status', 'draft');
        $verifiedHistory = $histories->firstWhere('to_status', 'verified');
        $waitingHistory = $histories->firstWhere('to_status', 'waiting');
        $activeHistory = $histories->firstWhere('to_status', 'active');

        $proposalAmount = (float) ($draftHistory?->principal_amount ?: $loan->principal_amount);
        $verifiedAmount = (float) ($verifiedHistory?->principal_amount ?: $proposalAmount);
        $allocatedAmount = (float) ($waitingHistory?->principal_amount ?: ($activeHistory?->principal_amount ?: $proposalAmount));

        $proposedAt = $loan->proposed_at?->toDateString() ?? now()->toDateString();
        $verifiedAt = $loan->verified_at?->toDateString() ?? $proposedAt;
        // Legacy mengisi tgl_dana/tgl_tunggu saat approval, dan tgl_cair dengan
        // rencana pencairan. `funded_at` Next menyimpan planned_disbursed_at.
        $approvedAt = $loan->approved_at?->toDateString() ?? $proposedAt;
        $plannedAt = $loan->funded_at?->toDateString() ?? $proposedAt;
        $disbursedAt = $loan->disbursed_at?->toDateString() ?? $plannedAt;
        $completedAt = $loan->completed_at?->toDateString() ?? $proposedAt;

        $statusLegacy = $this->legacyStatus((string) $loan->status);
        $effective = $this->effectiveScheduleValues(
            loan: $loan,
            status: $statusLegacy,
            verifiedHistory: $verifiedHistory,
            waitingHistory: $waitingHistory,
            activeHistory: $activeHistory,
            proposalAmount: $proposalAmount,
            verifiedAmount: $verifiedAmount,
            allocatedAmount: $allocatedAmount,
        );
        $systemPokok = $effective['system_pokok'];
        $systemJasa = $effective['system_jasa'];

        $rencana = $this->scheduleRows($loan, $effective);
        $realRows = $this->paymentRows($loan, $rencana);
        $latestPayment = $realRows->last();
        $lastReal = $latestPayment ?? $this->emptyRealRow($loan, $rencana);
        $dueRow = $this->dueRow($rencana);

        $memberObject = $this->memberObject(
            member: $member,
            person: $person,
            village: $village,
            naming: $naming,
            address: $address,
            guarantor: $guarantor,
            business: $business,
            guarantorRelation: (string) ($member?->guarantor?->relationship_type ?? ''),
            group: $group,
            chairName: $chairName,
            secretaryName: $secretaryName,
            treasurerName: $treasurerName,
        );

        $collateralJson = $this->collateralJson($loan->collateral, (string) ($person?->full_name ?? ''));
        $amount = (float) ($allocatedAmount > 0 ? $allocatedAmount : $loan->principal_amount);

        $pinkel = (object) [
            'id' => (int) $loan->id,
            'nia' => (int) ($member?->id ?? 0),
            'nib' => (string) ($member?->member_number ?? ''),
            'jenis_pinjaman' => 'I',
            'jenis_jasa' => $this->legacyInterestKind((string) ($loan->installment_method ?? 'flat')),
            'proposal' => $proposalAmount,
            'verifikasi' => $verifiedAmount,
            'alokasi' => $amount,
            'tgl_proposal' => $proposedAt,
            'tgl_verifikasi' => $verifiedAt,
            'tgl_tunggu' => $approvedAt,
            'tgl_dana' => $approvedAt,
            'tgl_cair' => $disbursedAt,
            'tgl_lunas' => $completedAt,
            'jangka' => $effective['term'],
            'pros_jasa' => $effective['service_rate'],
            'sistem_angsuran' => $systemPokok->id,
            'sa_jasa' => $systemJasa->id,
            'sis_pokok' => $systemPokok,
            'sis_jasa' => $systemJasa,
            'jpp' => (object) [
                'kode' => (string) ($loan->product?->code ?? ''),
                'nama_jpp' => (string) ($loan->product?->name ?? 'Pinjaman'),
                'deskripsi_jpp' => (string) ($loan->product?->description ?? ''),
            ],
            'jasa' => (object) [
                'nama_jj' => $this->interestKindLabel((string) ($loan->installment_method ?? 'flat')),
            ],
            'spk_no' => (string) ($loan->spk_no ?? ''),
            'wt_cair' => $this->disbursementSlot($loan),
            'jaminan' => $collateralJson,
            'nama_barang' => (string) ($loan->product?->name ?? ''),
            'kelompok' => $this->groupObject($village, $naming, $group, $chairName),
            'anggota' => $memberObject,
            'rencana' => $rencana,
            'rencana_count' => $rencana->count(),
            'target' => $rencana->take(1),
            'real_i' => $realRows,
            'real_i_count' => $realRows->count(),
            'real' => $lastReal,
            'real_count' => $realRows->count(),
            'pinjaman_anggota' => collect([$this->beneficiaryObject($memberObject, $amount, $proposalAmount, $verifiedAmount)]),
            'pinjaman_anggota_count' => 1,
            'iteration' => 0,
        ];

        $dir = $this->officerObject((string) ($profile?->manager_name ?? ''), (string) ($profile?->manager_nik ?? ''), (string) ($profile?->manager_address ?? $profile?->address ?? ''), (string) ($profile?->manager_title ?? 'Direktur'));
        $bend = $this->officerObject((string) ($profile?->treasurer_name ?? ''), (string) ($profile?->treasurer_nik ?? ''), (string) ($profile?->address ?? ''), (string) ($profile?->treasurer_title ?? 'Bendahara'));
        $bp = $this->officerObject((string) ($profile?->secretary_name ?? ''), (string) ($profile?->secretary_nik ?? ''), (string) ($profile?->address ?? ''), (string) ($profile?->secretary_title ?? 'Sekretaris'));

        $verifier = $this->officerObject(
            (string) ($profile?->verifier_name ?: $profile?->manager_name ?: ''),
            '',
            '',
            (string) ($profile?->verifier_title ?: $profile?->manager_title ?: 'Verifikator'),
        );

        $kabupatenName = (string) ($profile?->regency_name ?? '');
        $kabupatenTitle = Keuangan::startWith($kabupatenName, 'KOTA') || Keuangan::startWith($kabupatenName, 'KAB')
            ? ucwords(strtolower($kabupatenName))
            : 'Kabupaten '.ucwords(strtolower($kabupatenName));
        $namaKecamatan = trim('Kecamatan '.trim((string) ($profile?->district_name ?? '')).' '.$kabupatenTitle);
        $namaLembaga = (string) ($profile?->short_name ?: $profile?->legal_name ?: config('app.name'));
        $nomorUsaha = trim((string) ($profile?->registration_number ?? '')) !== ''
            ? 'SK Kemenkumham RI No.'.$profile->registration_number
            : '';
        $infoLembaga = (string) ($profile?->address ?? '').', Telp.'.(string) ($profile?->phone ?? '');
        $kab = (object) [
            'id' => 0,
            'nama_kab' => $kabupatenName,
            'nama_kabupaten' => $kabupatenName,
        ];

        $kec = (object) [
            'id' => 0,
            'logo' => basename((string) ($profile?->logo_path ?? '')),
            'logo_src' => $this->fileDataUri((string) ($profile?->logo_path ?? ''), 'public'),
            'nama_lembaga_sort' => $namaLembaga,
            'nama_lembaga' => $namaLembaga,
            'nama_kecamatan' => $namaKecamatan,
            'nama_kabupaten' => $kabupatenTitle,
            'nama_kec' => (string) ($profile?->district_name ?? ''),
            'sebutan_kec' => 'Kecamatan',
            'alamat_kec' => (string) ($profile?->address ?? ''),
            'telpon_kec' => (string) ($profile?->phone ?? ''),
            'email_kec' => (string) ($profile?->email ?? ''),
            'nomor_bh' => (string) ($profile?->registration_number ?? ''),
            'redaksi_spk' => $this->replaceTokens((string) ($profile?->spk_template_individual ?: $profile?->spk_template ?: ''), $tokens),
            'batas_angsuran' => (int) ($profile?->disbursement_cutoff_day ?? 0),
            'pembulatan' => (string) ($profile?->installment_rounding ?? '5000'),
            'def_jangka' => (int) ($loan->product?->default_term_months ?? 12),
            'def_jasa' => (float) ($loan->product?->default_interest_rate ?? 0),
            'sebutan_level_1' => (string) ($profile?->manager_title ?: $profile?->institution_level_1 ?: 'Direktur'),
            'sebutan_level_2' => (string) ($profile?->secretary_title ?: $profile?->institution_level_2 ?: 'Sekretaris'),
            'sebutan_level_3' => (string) ($profile?->treasurer_title ?: $profile?->institution_level_3 ?: 'Bendahara'),
            'sebutan_level_4' => (string) ($profile?->verifier_title ?: 'Verifikator'),
            'kabupaten' => $kab,
            'iptw' => 0,
            'besar_premi' => 0,
            'nama_asuransi_p' => '',
        ];

        $stage = $this->stageFor($documentKey, $statusLegacy);
        $memberName = (string) ($memberObject->namadepan ?? '-');
        $judul = $this->labelFor($documentKey).' ('.$memberName.' - Loan ID. '.$loan->id.')';

        $common = [
            'pinkel' => $pinkel,
            'nia' => $pinkel,
            'kec' => $kec,
            'kab' => $kab,
            'dir' => $dir,
            'bend' => $bend,
            'bp' => $bp,
            'keuangan' => $keuangan,
            'ttd' => $signatureHtml,
            'user' => collect([$verifier]),
            'verifikator' => collect([$verifier]),
            'rencana' => $rencana,
            // Legacy: `$real` bernilai null/0 kalau belum ada realisasi pembayaran.
            'real' => $latestPayment,
            'ra' => $dueRow,
            'transaksi' => collect(),
            'type' => 'pdf',
            'report' => self::LEGACY_REPORT_NAMES[$documentKey] ?? $documentKey,
            'judul' => $judul,
            'kategori_dokumen' => $stage,
            'status_legacy' => $statusLegacy,
            'logo' => basename((string) ($profile?->logo_path ?? '')),
            'logo_src' => $this->fileDataUri((string) ($profile?->logo_path ?? ''), 'public'),
            'logo_kab_src' => '',
            'nama_kab' => ucwords(strtolower($kabupatenName)),
            'nama_kabupaten' => $kabupatenTitle,
            'nama_kecamatan' => $namaKecamatan,
            'nama_lembaga' => $namaLembaga,
            'nomor_usaha' => $nomorUsaha,
            'info' => $infoLembaga,
            'email' => (string) ($profile?->email ?? ''),
            'spk_no' => (string) ($loan->spk_no ?? ''),
            'tgl_kondisi' => now()->toDateString(),
            'barcode' => Code128Barcode::pngDataUri((string) $loan->id),
            'laporan' => 'Kartu Angsuran '.$memberName.' Loan ID. '.$loan->id,
            'idtp' => null,
            'alokasi' => $amount,
            'alokasi_pinjaman' => $amount,
            'tanggal' => $statusLegacy === 'P' ? 'Tanggal Proposal' : ($statusLegacy === 'V' ? 'Tanggal Verifikasi' : ($statusLegacy === 'W' ? 'Tanggal Tunggu' : 'Tanggal Cair')),
            'tgl' => $statusLegacy === 'P' ? $proposedAt : ($statusLegacy === 'V' ? $verifiedAt : ($statusLegacy === 'W' ? $plannedAt : $disbursedAt)),
            'jangka' => $effective['term'],
            'sa_pokok' => $systemPokok->id,
            'sa_jasa' => $systemJasa->id,
            'sum_pokok' => (float) $lastReal->sum_pokok,
            'sum_jasa' => (float) $lastReal->sum_jasa,
            'saldo_pokok' => (float) $lastReal->saldo_pokok,
            'saldo_jasa' => (float) $lastReal->saldo_jasa,
            'real_pokok' => (float) $lastReal->realisasi_pokok,
            'real_jasa' => (float) $lastReal->realisasi_jasa,
            'target_pokok' => (float) $dueRow->target_pokok,
            'target_jasa' => (float) $dueRow->target_jasa,
            'tunggakan_pokok' => (float) $lastReal->tunggakan_pokok,
            'tunggakan_jasa' => (float) $lastReal->tunggakan_jasa,
            'wajib_angsur' => (float) ($rencana->first()->wajib_pokok ?? 0) + (float) ($rencana->first()->wajib_jasa ?? 0),
            'jumlah_angsuran' => (float) $this->maxInstallmentAmount($rencana),
        ];

        return array_merge($common, $this->documentSpecific($documentKey, $memberObject, $village, $naming, $loan));
    }

    /**
     * @param  array<string, string>  $tokens
     */
    private function replaceTokens(string $template, array $tokens): string
    {
        if ($template === '' || $tokens === []) {
            return $template;
        }

        return str_replace(array_keys($tokens), array_values($tokens), $template);
    }

    /**
     * @return array<string, mixed>
     */
    private function documentSpecific(string $key, object $member, ?OrganizationUnit $village, ?VillageNaming $naming, Loan $loan): array
    {
        return [];
    }

    private function memberObject(
        ?Member $member,
        ?Person $person,
        ?OrganizationUnit $village,
        ?VillageNaming $naming,
        mixed $address,
        ?Person $guarantor,
        mixed $business,
        string $guarantorRelation = '',
        ?Group $group = null,
        string $chairName = '',
        string $secretaryName = '',
        string $treasurerName = '',
    ): object {
        $fullName = (string) ($person?->full_name ?? '-');
        $villageSchedule = trim((string) ($village?->installment_schedule ?? ''));
        $villageDay = ctype_digit($villageSchedule) ? (int) $villageSchedule : 0;
        $villageObject = (object) [
            'id' => (int) ($village?->id ?? 0),
            'kode' => (string) ($village?->code ?? ''),
            'kd_desa' => (string) ($village?->code ?? ''),
            'nama_desa' => (string) ($village?->name ?? ''),
            'desa' => (string) ($village?->name ?? ''),
            'alamat_desa' => (string) ($village?->address ?? ''),
            'kades' => (string) ($village?->village_head_name ?? ''),
            'nip' => (string) ($village?->village_head_nip ?? ''),
            'jadwal_angsuran_desa' => $villageDay,
            'sebutan_desa' => (object) [
                'sebutan_desa' => (string) ($naming?->village_name ?: 'Desa'),
                'sebutan_kades' => (string) ($naming?->village_head_name ?: 'Kepala Desa'),
            ],
        ];

        $businessName = (string) ($business?->name ?? '');
        $addressText = (string) ($address?->address ?? '');
        $family = $guarantor !== null
            ? (object) ['kekeluargaan' => $guarantorRelation]
            : null;

        return (object) [
            'id' => (int) ($member?->id ?? 0),
            'row_id' => (int) ($member?->row_id ?? 0),
            'nia' => (string) ($member?->member_number ?? ''),
            'namadepan' => $fullName,
            'namabelakang' => '',
            'damadepan' => $fullName,
            'nik' => (string) ($person?->national_identity_number ?? ''),
            'kk' => (string) ($person?->family_card_number ?? ''),
            'jk' => $this->legacyGender((string) ($person?->gender ?? '')),
            'tempat_lahir' => (string) ($person?->birth_place ?? ''),
            'tgl_lahir' => $person?->birth_date?->toDateString() ?? '',
            // Legacy `anggota` tidak punya kolom tgl_cair. Template SPK memanggil
            // Carbon::parse(null), sehingga yang tampil adalah tanggal cetak.
            'tgl_cair' => null,
            'alamat' => $addressText,
            'alamat_anggota' => $addressText,
            'alamat_Anggota' => $addressText,
            'alamat_kelompok' => (string) ($group?->address ?: $addressText),
            'hp' => (string) ($person?->phone ?? ''),
            'usaha' => $businessName,
            'u' => (object) ['nama_usaha' => $businessName],
            'd' => $villageObject,
            'desa' => $villageObject,
            'keluarga' => $family,
            'nama_kelompok' => (string) ($group?->name ?? ''),
            'kd_kelompok' => (string) ($group?->code ?? ''),
            'ketua' => $chairName,
            'sekretaris' => $secretaryName,
            'bendahara' => $treasurerName,
            'penjamin' => (string) ($guarantor?->full_name ?? ''),
            'nik_penjamin' => (string) ($guarantor?->national_identity_number ?? ''),
            'pinjaman' => null,
        ];
    }

    private function groupObject(?OrganizationUnit $village, ?VillageNaming $naming, ?Group $group = null, string $chairName = ''): object
    {
        return (object) [
            'id' => (int) ($group?->id ?? 0),
            'kd_kelompok' => (string) ($group?->code ?? ''),
            'nama_kelompok' => (string) ($group?->name ?? ''),
            'ketua' => $chairName,
            'd' => (object) [
                'kd_desa' => (string) ($village?->code ?? ''),
                'nama_desa' => (string) ($village?->name ?? ''),
                'sebutan_desa' => (object) [
                    'sebutan_desa' => (string) ($naming?->village_name ?: 'Desa'),
                ],
            ],
        ];
    }

    private function beneficiaryObject(object $member, float $allocated, float $proposal, float $verified): object
    {
        return (object) [
            'id' => 0,
            'anggota' => $member,
            'alokasi' => $allocated,
            'proposal' => $proposal,
            'verifikasi' => $verified,
        ];
    }

    private function legacyGender(string $gender): string
    {
        return match (strtoupper(trim($gender))) {
            'M', 'MALE', 'LAKI-LAKI', 'LAKI LAKI' => 'L',
            'F', 'FEMALE', 'PEREMPUAN' => 'P',
            default => strtoupper(trim($gender)),
        };
    }

    private function officerObject(string $name, string $nik, string $address, string $position): object
    {
        $name = trim($name);
        $parts = $name === '' ? [''] : preg_split('/\s+/', $name, 2);
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? '';

        return (object) [
            'namadepan' => $first,
            'namabelakang' => $last,
            'nik' => $nik,
            'alamat' => $address,
            'jabatan' => $position,
            'j' => (object) ['nama_jabatan' => $position],
        ];
    }

    private function systemFor(string $frequency, int $grace): object
    {
        $legacyId = $this->legacySystemId($frequency, $grace);

        $system = InstallmentSystem::query()->where('legacy_id', $legacyId)->first();
        if ($system !== null) {
            return (object) [
                'id' => (string) $legacyId,
                'sistem' => (int) $system->interval_months,
                'nama_sistem' => (string) $system->name,
                'deskripsi_sistem' => (string) $system->description,
            ];
        }

        $fallback = self::SYSTEM_FALLBACK[$legacyId] ?? self::SYSTEM_FALLBACK[1];

        return (object) [
            'id' => (string) $legacyId,
            'sistem' => (int) $fallback['interval'],
            'nama_sistem' => (string) $fallback['name'],
            'deskripsi_sistem' => (string) $fallback['description'],
        ];
    }

    private function legacySystemId(string $frequency, int $grace): int
    {
        if (preg_match('/^\d+$/', $frequency) === 1) {
            return (int) $frequency;
        }

        return match ($frequency) {
            'weekly' => 12,
            'biweekly' => 12,
            'bimonthly' => 9,
            'quarterly' => 2,
            'every_4_months' => 3,
            'every_5_months' => 6,
            'every_6_months' => 4,
            'every_7_months' => 7,
            'every_8_months' => 8,
            'every_9_months' => 13,
            'every_10_months' => 21,
            'every_11_months' => 24,
            'every_12_months' => 10,
            'every_24_months' => 22,
            'every_36_months' => 23,
            'at_maturity' => 22,
            'monthly' => match (true) {
                $grace >= 24 => 11,
                $grace >= 12 => 20,
                $grace >= 6 => 26,
                $grace >= 3 => 14,
                $grace >= 2 => 15,
                $grace >= 1 => 25,
                default => 1,
            },
            default => 1,
        };
    }

    private function legacyInterestKind(string $method): string
    {
        return match ($method) {
            'effective' => '2',
            'annuity' => '3',
            default => '1',
        };
    }

    private function interestKindLabel(string $method): string
    {
        return match ($method) {
            'effective' => 'Efektif',
            'annuity' => 'Anuitas',
            default => 'Flat',
        };
    }

    private function legacyStatus(string $status): string
    {
        return match ($status) {
            'draft', 'edit_proposal' => 'P',
            'verified' => 'V',
            'waiting', 'approved' => 'W',
            'active', 'disbursed' => 'A',
            'completed' => 'L',
            'rescheduled' => 'R',
            'written_off' => 'H',
            'rejected' => 'T',
            default => 'P',
        };
    }

    private function stageFor(string $documentKey, string $status): string
    {
        if (in_array($documentKey, [
            'cover_proposal_individu', 'pengajuan_kredit_individu', 'rekomendasi_kredit_individu',
            'rekomendasi_verifikator_individu', 'pernyataan_peminjam_individu', 'form_verifikasi_individu',
            'rencana_angsuran_individu_proposal',
            'surat_persetujuan_kuasa_individu', 'tanda_terima_jaminan',
        ], true)) {
            return 'proposal';
        }

        return 'pencairan';
    }

    private function labelFor(string $key): string
    {
        return match ($key) {
            'cover_proposal_individu' => 'Cover Proposal',
            'pengajuan_kredit_individu' => 'Surat Pengajuan Kredit',
            'rekomendasi_kredit_individu' => 'Surat Rekomendasi Kredit',
            'rekomendasi_verifikator_individu' => 'Surat Rekomendasi Verifikator',
            'pernyataan_peminjam_individu' => 'Surat Pernyataan Peminjam',
            'form_verifikasi_individu' => 'Form Verifikasi',
            'form_verifikasi_individu_pencairan' => 'Form Verifikasi',
            'rencana_angsuran_individu' => 'Rencana Angsuran',
            'rencana_angsuran_individu_proposal' => 'Rencana Angsuran',
            'surat_persetujuan_kuasa_individu' => 'Surat Kuasa Khusus',
            'tanda_terima_jaminan' => 'Penyerahan Jaminan',
            'cover_pencairan_individu' => 'Cover Pencairan',
            'spk_individu' => 'Surat Perjanjian Kredit',
            'ba_pencairan_individu' => 'Berita Acara Pencairan',
            'kuitansi_pencairan_individu' => 'Kuitansi',
            'kartu_angsuran_individu' => 'Kartu Angsuran',
            'analisis_keputusan_kredit' => 'Analisis Keputusan Kredit',
            'surat_pemberitahuan' => 'Surat Pemberitahuan',
            'pengikat_diri_penjamin' => 'Pengikat Diri Sebagai Penjamin',
            'surat_pernyataan_suami' => 'Surat Pernyataan Suami',
            'tagihan_individu' => 'Surat Tagihan',
            'surat_kelayakan_individu' => 'Surat Kelayakan',
            'daftar_hadir_pencairan_individu' => 'Daftar Hadir Pencairan',
            'pemberitahuan_desa_individu' => 'Pemberitahuan Ke Desa',
            'surat_ahli_waris_individu' => 'Surat Ahli Waris',
            'bukti_pengembalian_jaminan' => 'Bukti Pengembalian Jaminan',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    private function disbursementSlot(Loan $loan): string
    {
        $slot = trim((string) ($loan->disbursement_slot ?? ''));
        if ($slot === '') {
            return '09:00_Kantor UPK';
        }

        // Format legacy: "HH:MM_Tempat" (underscore). Jika sudah demikian, pakai apa adanya.
        if (str_contains($slot, '_')) {
            return $slot;
        }

        // Format Next: "10:00 WIB · Kantor BUMDes" / "10:00 - Kantor BUMDes".
        if (preg_match('/(\d{1,2}:\d{2})/', $slot, $matches) === 1) {
            $time = $matches[1];
            $place = trim(str_replace($time, '', $slot));
            $place = (string) preg_replace('/\b(WIB|WITA|WIT)\b/i', '', $place);
            $place = str_replace(['Â·', '·', '•', 'â€¢'], ' ', $place);
            $place = (string) preg_replace('/^[\s\p{P}\p{S}]+/u', '', $place);
            $place = (string) preg_replace('/\s+/u', ' ', trim($place));

            return $time.'_'.($place !== '' ? $place : 'Kantor UPK');
        }

        // Fallback: seluruh slot dianggap tempat, waktu default.
        return '09:00_'.$slot;
    }

    private function collateralJson(mixed $collateral, string $ownerName = ''): ?string
    {
        if (! is_array($collateral) || $collateral === []) {
            return null;
        }

        $items = [];
        if (array_is_list($collateral)) {
            foreach ($collateral as $item) {
                if (is_array($item)) {
                    $items = array_merge($items, $this->collateralMap($item, $ownerName));
                }
            }
        } else {
            $items = $this->collateralMap($collateral, $ownerName);
        }

        if ($items === []) {
            return null;
        }

        return json_encode($items, JSON_UNESCAPED_UNICODE) ?: null;
    }

    /**
     * Hasil akhir harus menyerupai JSON `pinjaman_individu.jaminan` SIUPK:
     * field rincian lebih dulu, lalu `jenis_jaminan` di paling akhir.
     *
     * @param  array<string, mixed>  $collateral
     * @return array<string, string>
     */
    private function collateralMap(array $collateral, string $ownerName = ''): array
    {
        // Data lama/import yang sudah berbentuk legacy -> biarkan apa adanya.
        if (! isset($collateral['type']) && isset($collateral['jenis_jaminan'])) {
            return array_map(
                static fn (mixed $value): string => is_scalar($value) ? (string) $value : '',
                $collateral,
            );
        }

        // Sebagian data lama hanya berisi detail key tanpa `type` maupun
        // `jenis_jaminan`. Jangan remap menjadi "Lain Lain"; pertahankan key
        // asli (nomor, nama_pemilik, jenis_kendaraan, dst.) dan urutannya.
        if (! isset($collateral['type']) && ! isset($collateral['jenis_jaminan'])) {
            $legacyKeys = [
                'nomor_sertifikat', 'nomor', 'nama_pemilik', 'nama_pegawai', 'nama_kuitansi_penerbit',
                'alamat', 'luas', 'nilai_jual_tanah', 'jenis_kendaraan', 'nopol',
                'nilai_jual_kendaraan', 'nama_jaminan', 'keterangan', 'nilai_jaminan',
            ];

            if (array_intersect_key($collateral, array_flip($legacyKeys)) !== []) {
                $preserved = [];
                foreach ($collateral as $key => $value) {
                    if (in_array($key, ['type', 'details'], true)) {
                        continue;
                    }
                    if (is_scalar($value) || $value === null) {
                        $preserved[(string) $key] = $this->stringifyCollateralValue((string) $key, $value);
                    }
                }

                return $preserved;
            }
        }

        $type = (string) ($collateral['type'] ?? '');
        $typeCode = match ($type) {
            'sertifikat_tanah' => '1',
            'kendaraan', 'bpkb', 'cash', 'simpanan' => '2',
            'sk_pegawai' => '3',
            default => '4',
        };

        $keys = match ($typeCode) {
            '1' => ['nomor_sertifikat', 'nama_pemilik', 'alamat', 'luas', 'nilai_jual_tanah'],
            '2' => ['nomor', 'nama_pemilik', 'jenis_kendaraan', 'nopol', 'nilai_jual_kendaraan'],
            '3' => ['nomor', 'nama_pegawai', 'nama_kuitansi_penerbit'],
            default => ['nama_jaminan', 'keterangan', 'nilai_jaminan'],
        };

        // Dukung data lama yang menaruh field legacy langsung di root JSON.
        $details = array_intersect_key($collateral, array_flip($keys));
        if (isset($collateral['details']) && is_array($collateral['details'])) {
            $details = array_merge($details, $collateral['details']);
        }

        $map = [];

        foreach ($keys as $key) {
            $value = $details[$key] ?? null;
            if ($value === null || $value === '') {
                $value = $this->legacyCollateralFallback($key, $collateral, $ownerName);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $map[$key] = $this->stringifyCollateralValue($key, $value);
        }

        if ($map === [] && $details === []) {
            return [];
        }

        // Legacy menuliskan `jenis_jaminan` setelah field rincian.
        $map['jenis_jaminan'] = $typeCode;

        return $map;
    }

    /**
     * @param  array<string, mixed>  $collateral
     */
    private function legacyCollateralFallback(string $key, array $collateral, string $ownerName): ?string
    {
        $reference = trim((string) ($collateral['reference'] ?? $collateral['nomor'] ?? $collateral['nomor_sertifikat'] ?? ''));
        $description = trim((string) ($collateral['description'] ?? $collateral['jenis_kendaraan'] ?? $collateral['nama_jaminan'] ?? $collateral['keterangan'] ?? ''));
        $value = $collateral['value']
            ?? $collateral['nilai_jaminan']
            ?? $collateral['nilai_jual_tanah']
            ?? $collateral['nilai_jual_kendaraan']
            ?? null;
        $owner = trim((string) ($collateral['nama_pemilik'] ?? $collateral['atas_nama'] ?? $collateral['nama_pegawai'] ?? ''));
        if ($owner === '') {
            $owner = $ownerName;
        }

        return match ($key) {
            'nomor_sertifikat', 'nomor' => $reference !== '' ? $reference : null,
            'nama_pemilik', 'nama_pegawai' => $owner !== '' ? $owner : null,
            'alamat' => $description !== '' ? $description : null,
            'nilai_jual_tanah', 'nilai_jual_kendaraan', 'nilai_jaminan' => $value !== null && $value !== ''
                ? (string) round((float) $value)
                : null,
            'jenis_kendaraan' => $description !== '' ? $description : null,
            'nama_kuitansi_penerbit' => $description !== '' ? $description : null,
            'nama_jaminan' => $description !== '' ? $description : ($reference !== '' ? $reference : null),
            'keterangan' => $description !== '' ? $description : null,
            default => null,
        };
    }

    private function stringifyCollateralValue(string $key, mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = trim((string) $value);
        if (str_starts_with($key, 'nilai')) {
            return (string) preg_replace('/[^\d-]/', '', $value);
        }

        return $value;
    }

    /**
     * Nilai efektif tahap dokumen. Legacy menyimpan hasil verifikasi dan
     * alokasi di kolom pinjaman, sementara Next menyimpannya di history.
     *
     * @return array{term: int, service_rate: float, principal: float, date: ?CarbonImmutable, principal_frequency: string, interest_frequency: string, principal_grace: int, interest_grace: int, system_pokok: object, system_jasa: object}
     */
    private function effectiveScheduleValues(
        Loan $loan,
        string $status,
        mixed $verifiedHistory,
        mixed $waitingHistory,
        mixed $activeHistory,
        float $proposalAmount,
        float $verifiedAmount,
        float $allocatedAmount,
    ): array {
        $source = match ($status) {
            'V' => $verifiedHistory,
            'W' => $waitingHistory,
            default => null,
        };

        $term = (int) ($source?->term_months ?? $loan->term_months);
        $term = max(1, $term);
        $serviceRate = (float) ($source?->service_rate_total ?? $loan->service_rate_total);
        $principalFrequency = (string) ($source?->principal_frequency ?? $loan->principal_frequency ?? 'monthly');
        $interestFrequency = (string) ($source?->interest_frequency ?? $loan->interest_frequency ?? 'monthly');
        $principalGrace = (int) ($source?->principal_grace_months ?? $loan->principal_grace_months ?? 0);
        $interestGrace = (int) ($source?->interest_grace_months ?? $loan->interest_grace_months ?? 0);

        $principal = match ($status) {
            'P' => $proposalAmount,
            'V' => $verifiedAmount,
            'W' => $allocatedAmount,
            default => (float) ($activeHistory?->principal_amount ?: $loan->principal_amount),
        };

        $dateValue = match ($status) {
            'P' => $loan->proposed_at?->toDateString(),
            'V' => $loan->verified_at?->toDateString() ?? $loan->proposed_at?->toDateString(),
            'W' => $loan->funded_at?->toDateString() ?? $loan->proposed_at?->toDateString(),
            default => $loan->disbursed_at?->toDateString() ?? $loan->funded_at?->toDateString() ?? $loan->proposed_at?->toDateString(),
        };

        return [
            'term' => $term,
            'service_rate' => $serviceRate,
            'principal' => $principal,
            'date' => $dateValue !== null ? CarbonImmutable::parse($dateValue) : null,
            'principal_frequency' => $principalFrequency,
            'interest_frequency' => $interestFrequency,
            'principal_grace' => $principalGrace,
            'interest_grace' => $interestGrace,
            'system_pokok' => $this->systemFor($principalFrequency, $principalGrace),
            'system_jasa' => $this->systemFor($interestFrequency, $interestGrace),
        ];
    }

    /**
     * @param  array{term: int, service_rate: float, principal: float, date: ?CarbonImmutable, principal_frequency: string, interest_frequency: string, principal_grace: int, interest_grace: int}  $effective
     * @return Collection<int, object>
     */
    private function scheduleRows(Loan $loan, array $effective): Collection
    {
        // Pacuan hanya memakai baris tersimpan saat status A. Status P/V/W/L/R/H
        // memanggil generate() live (non-save) sehingga tanggal di-clamp.
        if ($this->legacyStatus((string) $loan->status) === 'A') {
            return $this->savedScheduleRows($loan);
        }

        $preview = [];
        if ($effective['date'] !== null) {
            $preview = $this->scheduleCalculator->preview(
                loan: $loan,
                term: $effective['term'],
                principal: $effective['principal'],
                principalSystem: $effective['principal_frequency'],
                interestSystem: $effective['interest_frequency'],
                serviceRateTotal: $effective['service_rate'],
                interestMethod: (string) ($loan->installment_method ?? 'flat'),
                disbursementDate: $effective['date'],
                principalGraceMonths: $effective['principal_grace'],
                interestGraceMonths: $effective['interest_grace'],
            );
        }

        if ($preview === []) {
            return $this->savedScheduleRows($loan);
        }

        return collect($preview)->map(static fn (array $row): object => (object) [
            'id' => 0,
            'angsuran_ke' => (int) $row['angsuran_ke'],
            'jatuh_tempo' => (string) $row['jatuh_tempo'],
            'wajib_pokok' => round((float) $row['wajib_pokok'], 2),
            'wajib_jasa' => round((float) $row['wajib_jasa'], 2),
            'pokok' => round((float) $row['wajib_pokok'], 2),
            'jasa' => round((float) $row['wajib_jasa'], 2),
            'target_pokok' => round((float) $row['target_pokok'], 2),
            'target_jasa' => round((float) $row['target_jasa'], 2),
            'status' => 'pending',
        ])->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function savedScheduleRows(Loan $loan): Collection
    {
        $grouped = $loan->installments
            ->filter(static fn (LoanInstallment $inst): bool => (int) $inst->installment_number > 0)
            ->sortBy(static fn (LoanInstallment $inst): int => (int) $inst->installment_number)
            ->groupBy(static fn (LoanInstallment $inst): int => (int) $inst->installment_number);

        $rows = collect();
        $cumulativePrincipal = 0.0;
        $cumulativeInterest = 0.0;

        foreach ($grouped as $number => $items) {
            /** @var Collection<int, LoanInstallment> $items */
            $principal = (float) $items->sum(static fn (LoanInstallment $i): float => (float) $i->principal_due);
            $interest = (float) $items->sum(static fn (LoanInstallment $i): float => (float) $i->interest_due);
            $cumulativePrincipal += $principal;
            $cumulativeInterest += $interest;
            $first = $items->first();

            $rows->push((object) [
                'id' => (int) ($first?->row_id ?? 0),
                'angsuran_ke' => (int) $number,
                'jatuh_tempo' => $first?->due_date?->toDateString() ?? '',
                'wajib_pokok' => round($principal, 2),
                'wajib_jasa' => round($interest, 2),
                'pokok' => round($principal, 2),
                'jasa' => round($interest, 2),
                'target_pokok' => round($cumulativePrincipal, 2),
                'target_jasa' => round($cumulativeInterest, 2),
                'status' => (string) ($first?->status ?? 'pending'),
            ]);
        }

        return $rows->values();
    }

    /**
     * @param  Collection<int, object>  $rencana
     * @return Collection<int, object>
     */
    private function paymentRows(Loan $loan, Collection $rencana): Collection
    {
        $installmentNumberByRowId = $loan->installments
            ->mapWithKeys(static fn (LoanInstallment $inst): array => [(int) $inst->row_id => (int) $inst->installment_number])
            ->all();

        $totalPrincipal = (float) $loan->principal_amount;
        $totalInterest = (float) $rencana->sum(static fn (object $row): float => (float) $row->wajib_jasa);

        $rows = collect();
        $cumulativePrincipal = 0.0;
        $cumulativeInterest = 0.0;

        /** @var LoanPayment $payment */
        foreach ($loan->payments as $payment) {
            $principal = 0.0;
            $interest = 0.0;
            $lastNumber = 0;

            foreach ($payment->allocations as $allocation) {
                $component = (string) ($allocation->component ?? '');
                $amount = (float) $allocation->amount;
                if ($component === 'principal') {
                    $principal += $amount;
                } elseif ($component === 'interest') {
                    $interest += $amount;
                }

                $number = $installmentNumberByRowId[(int) ($allocation->installment_row_id ?? 0)] ?? 0;
                if ($number > $lastNumber) {
                    $lastNumber = $number;
                }
            }

            $cumulativePrincipal += $principal;
            $cumulativeInterest += $interest;

            // Legacy mengambil target dari rencana terakhir yang sudah jatuh
            // tempo pada tanggal transaksi, bukan dari angsuran yang dibayar.
            $paidDate = $payment->paid_at?->toDateString() ?? '';
            $target = $paidDate === ''
                ? null
                : $rencana->filter(static fn (object $row): bool => (string) $row->jatuh_tempo !== '' && (string) $row->jatuh_tempo <= $paidDate)->last();
            $targetPrincipal = (float) ($target->target_pokok ?? 0);
            $targetInterest = (float) ($target->target_jasa ?? 0);

            $rows->push((object) [
                'id' => (int) $payment->id,
                'tgl_transaksi' => $payment->paid_at?->toDateString() ?? '',
                'waktu_transaksi' => $payment->paid_at?->format('H:i') ?? '',
                'angsuran_ke' => $lastNumber,
                'realisasi_pokok' => round($principal, 2),
                'realisasi_jasa' => round($interest, 2),
                'sum_pokok' => round($cumulativePrincipal, 2),
                'sum_jasa' => round($cumulativeInterest, 2),
                'saldo_pokok' => round(max(0.0, $totalPrincipal - $cumulativePrincipal), 2),
                'saldo_jasa' => round(max(0.0, $totalInterest - $cumulativeInterest), 2),
                'tunggakan_pokok' => round(max(0.0, $targetPrincipal - $cumulativePrincipal), 2),
                'tunggakan_jasa' => round(max(0.0, $targetInterest - $cumulativeInterest), 2),
            ]);
        }

        return $rows->values();
    }

    private function emptyRealRow(Loan $loan, Collection $rencana): object
    {
        $totalInterest = (float) $rencana->sum(static fn (object $row): float => (float) $row->wajib_jasa);
        $last = $rencana->last();

        return (object) [
            'id' => 0,
            'tgl_transaksi' => $last?->jatuh_tempo ?? ($loan->proposed_at?->toDateString() ?? ''),
            'waktu_transaksi' => '',
            'angsuran_ke' => 0,
            'realisasi_pokok' => 0.0,
            'realisasi_jasa' => 0.0,
            'sum_pokok' => 0.0,
            'sum_jasa' => 0.0,
            'saldo_pokok' => (float) $loan->principal_amount,
            'saldo_jasa' => $totalInterest,
            'tunggakan_pokok' => 0.0,
            'tunggakan_jasa' => 0.0,
        ];
    }

    /**
     * @param  Collection<int, object>  $rencana
     */
    private function dueRow(Collection $rencana): object
    {
        $today = now()->toDateString();
        $due = $rencana->filter(static fn (object $row): bool => (string) $row->jatuh_tempo !== '' && (string) $row->jatuh_tempo <= $today)->last();

        // Pacuan: kalau belum ada angsuran yang jatuh tempo, `$ra` = null dan template
        // menganggap target/tunggakan = 0. Jangan fallback ke angsuran pertama.
        if ($due === null) {
            return (object) [
                'id' => 0,
                'angsuran_ke' => 0,
                'jatuh_tempo' => '',
                'wajib_pokok' => 0.0,
                'wajib_jasa' => 0.0,
                'target_pokok' => 0.0,
                'target_jasa' => 0.0,
            ];
        }

        return (object) [
            'id' => (int) ($due->id ?? 0),
            'angsuran_ke' => (int) ($due->angsuran_ke ?? 0),
            'jatuh_tempo' => (string) ($due->jatuh_tempo ?? ''),
            'wajib_pokok' => (float) ($due->wajib_pokok ?? 0),
            'wajib_jasa' => (float) ($due->wajib_jasa ?? 0),
            'target_pokok' => (float) ($due->target_pokok ?? 0),
            'target_jasa' => (float) ($due->target_jasa ?? 0),
        ];
    }

    private function maxInstallmentAmount(Collection $rencana): float
    {
        $max = 0.0;
        foreach ($rencana as $row) {
            $amount = (float) $row->wajib_pokok + (float) $row->wajib_jasa;
            if ($amount > $max) {
                $max = $amount;
            }
        }

        return $max;
    }

    private function fileDataUri(string $path, string $disk): string
    {
        if ($path === '') {
            return '';
        }

        try {
            $storage = Storage::disk($disk);
            if (! $storage->exists($path)) {
                return '';
            }

            $contents = $storage->get($path);
            if ($contents === '') {
                return '';
            }

            $mime = $storage->mimeType($path);

            return 'data:'.($mime ?: 'image/png').';base64,'.base64_encode($contents);
        } catch (\Throwable) {
            return '';
        }
    }
}
