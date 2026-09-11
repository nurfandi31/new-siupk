<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Documents\Services\SignatureImageService;
use App\Domain\Documents\Services\SignatureTemplateService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\GroupOfficer;
use App\Domain\Membership\Models\OrganizationProfile;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Storage;

/**
 * Cetak dokumen pinjaman (PDF) — referensi alur `siupk/perguliran/dokumen`,
 * di-port ke arsitektur modern dengan signature template dinamis.
 *
 * Registry + token di file ini; view Blade di `resources/views/reports/pdf/loan_documents/`.
 */
final class LoanDocumentService
{
    /**
     * Registry dokumen yang tersedia.
     *
     * @var list<array{key: string, label: string, stage: string, view: string, signature: ?string, orientation: string, icon: string}>
     */
    private const DOCUMENTS = [
        ['key' => 'cover_proposal',               'label' => 'Cover Proposal',                  'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.cover_proposal',               'signature' => null,                'orientation' => 'portrait',  'icon' => 'auto_stories'],
        ['key' => 'pengajuan_kredit',             'label' => 'Surat Pengajuan Kredit',          'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.pengajuan_kredit',             'signature' => 'proposal',          'orientation' => 'portrait',  'icon' => 'mail'],
        ['key' => 'profil_kelompok',              'label' => 'Profil Kelompok',                 'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.profil_kelompok',              'signature' => null,                'orientation' => 'portrait',  'icon' => 'groups'],
        ['key' => 'susunan_pengurus',             'label' => 'Susunan Pengurus Kelompok',       'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.susunan_pengurus',             'signature' => null,                'orientation' => 'portrait',  'icon' => 'badge'],
        ['key' => 'daftar_pemanfaat',             'label' => 'Daftar Pemanfaat & Alokasi',      'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.daftar_pemanfaat',             'signature' => 'proposal',          'orientation' => 'landscape', 'icon' => 'list_alt'],
        ['key' => 'pernyataan_tanggung_renteng',  'label' => 'Pernyataan Tanggung Renteng',     'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.pernyataan_tanggung_renteng',  'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'handshake'],
        ['key' => 'rekomendasi_kredit',           'label' => 'Surat Rekomendasi Kredit',        'stage' => 'verification', 'view' => 'reports.pdf.loan_documents.rekomendasi_kredit',           'signature' => 'proposal',          'orientation' => 'portrait',  'icon' => 'verified'],
        ['key' => 'spk',                          'label' => 'Surat Perjanjian Kredit (SPK)',   'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.spk',                          'signature' => 'perjanjian_kredit', 'orientation' => 'portrait',  'icon' => 'gavel'],
        ['key' => 'berita_acara_pencairan',       'label' => 'Berita Acara Pencairan',          'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.berita_acara_pencairan',       'signature' => 'perjanjian_kredit', 'orientation' => 'portrait',  'icon' => 'fact_check'],
        ['key' => 'kuitansi_pencairan',           'label' => 'Kuitansi Pencairan',              'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.kuitansi_pencairan',           'signature' => 'kwitansi',          'orientation' => 'portrait',  'icon' => 'receipt_long'],

        // Iterasi 2 — Proposal Detail & Verifikasi
        ['key' => 'check',                        'label' => 'Checklist Proposal',             'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.check',                        'signature' => null,                'orientation' => 'portrait',  'icon' => 'checklist'],
        ['key' => 'ba_musyawarah',                'label' => 'Berita Acara Musyawarah',        'stage' => 'verification', 'view' => 'reports.pdf.loan_documents.ba_musyawarah',                'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'groups'],
        ['key' => 'surat_verifikasi',             'label' => 'Surat Undangan Verifikasi',      'stage' => 'verification', 'view' => 'reports.pdf.loan_documents.surat_verifikasi',             'signature' => 'proposal',          'orientation' => 'portrait',  'icon' => 'mark_email_read'],
        ['key' => 'surat_kelayakan',              'label' => 'Surat Kelayakan Piutang',        'stage' => 'verification', 'view' => 'reports.pdf.loan_documents.surat_kelayakan',              'signature' => 'proposal',          'orientation' => 'portrait',  'icon' => 'verified_user'],
        ['key' => 'form_verifikasi',              'label' => 'Form Verifikasi Kelompok',       'stage' => 'verification', 'view' => 'reports.pdf.loan_documents.form_verifikasi',              'signature' => null,                'orientation' => 'portrait',  'icon' => 'assignment'],
        ['key' => 'form_verifikasi_anggota',      'label' => 'Form Verifikasi Anggota',        'stage' => 'verification', 'view' => 'reports.pdf.loan_documents.form_verifikasi_anggota',      'signature' => null,                'orientation' => 'portrait',  'icon' => 'assignment_ind'],

        // Iterasi 3 — Pencairan & Penyaluran
        ['key' => 'cover_pencairan',              'label' => 'Cover Pencairan',                'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.cover_pencairan',              'signature' => null,                'orientation' => 'portrait',  'icon' => 'auto_stories'],
        ['key' => 'rencana_angsuran',             'label' => 'Rencana Angsuran',               'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.rencana_angsuran',             'signature' => null,                'orientation' => 'portrait',  'icon' => 'calendar_month'],
        ['key' => 'kartu_angsuran_anggota',       'label' => 'Kartu Angsuran per Anggota',     'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.kartu_angsuran_anggota',       'signature' => null,                'orientation' => 'portrait',  'icon' => 'credit_card'],
        ['key' => 'tanda_terima',                 'label' => 'Tanda Terima Dana',              'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.tanda_terima',                 'signature' => 'default',           'orientation' => 'landscape', 'icon' => 'task_alt'],
        ['key' => 'pemberitahuan_desa',           'label' => 'Surat Pemberitahuan ke Desa',    'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.pemberitahuan_desa',           'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'campaign'],
        ['key' => 'ba_pendanaan',                 'label' => 'BA Rapat Penetapan Pendanaan',   'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.ba_pendanaan',                 'signature' => 'perjanjian_kredit', 'orientation' => 'landscape', 'icon' => 'fact_check'],
        ['key' => 'peserta_asuransi',             'label' => 'Daftar Peserta Asuransi',        'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.peserta_asuransi',             'signature' => null,                'orientation' => 'landscape', 'icon' => 'health_and_safety'],
        ['key' => 'kuitansi_anggota',             'label' => 'Kuitansi per Anggota',           'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.kuitansi_anggota',             'signature' => 'kwitansi',          'orientation' => 'portrait',  'icon' => 'receipt'],
        ['key' => 'tagihan',                      'label' => 'Surat Tagihan',                  'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.tagihan',                      'signature' => 'kwitansi',          'orientation' => 'portrait',  'icon' => 'request_quote'],
        ['key' => 'surat_ahli_waris',             'label' => 'Surat Pernyataan Ahli Waris',    'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.surat_ahli_waris',             'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'family_restroom'],
        ['key' => 'surat_kuasa',                  'label' => 'Surat Kuasa Penandatanganan SPK', 'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.surat_kuasa',                 'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'assignment_late'],

        // Iterasi 4 — Penunjang
        ['key' => 'anggota',                      'label' => 'Daftar Anggota Kelompok',        'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.anggota',                      'signature' => null,                'orientation' => 'portrait',  'icon' => 'people'],
        ['key' => 'ktp',                          'label' => 'Cetak KTP Pemanfaat',           'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.ktp',                          'signature' => null,                'orientation' => 'portrait',  'icon' => 'badge'],
        ['key' => 'catatan_bimbingan',            'label' => 'Catatan Bimbingan Kelompok',    'stage' => 'proposal',     'view' => 'reports.pdf.loan_documents.catatan_bimbingan',            'signature' => null,                'orientation' => 'portrait',  'icon' => 'support_agent'],
        ['key' => 'daftar_hadir_verifikasi',     'label' => 'Daftar Hadir Verifikasi',       'stage' => 'verification', 'view' => 'reports.pdf.loan_documents.daftar_hadir_verifikasi',     'signature' => null,                'orientation' => 'portrait',  'icon' => 'event_available'],
        ['key' => 'tanggung_renteng_kematian',    'label' => 'Surat Pernyataan TR Kematian',   'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.tanggung_renteng_kematian',    'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'volunteer_activism'],
        ['key' => 'iptw',                         'label' => 'Daftar Penerima IPTW',          'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.iptw',                         'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'savings'],
        ['key' => 'rekening_koran',               'label' => 'Rekening Koran Pinjaman',       'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.rekening_koran',               'signature' => null,                'orientation' => 'portrait',  'icon' => 'account_balance'],
        ['key' => 'pernyataan_peminjam',          'label' => 'Surat Pengakuan Utang Peminjam', 'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.pernyataan_peminjam',          'signature' => 'default',           'orientation' => 'portrait',  'icon' => 'history_edu'],
        ['key' => 'daftar_hadir_pencairan',       'label' => 'Daftar Hadir Pencairan',        'stage' => 'disbursement', 'view' => 'reports.pdf.loan_documents.daftar_hadir_pencairan',       'signature' => null,                'orientation' => 'portrait',  'icon' => 'event_available'],
    ];

    /**
     * Status loan yang memenuhi syarat cetak untuk masing-masing stage.
     *
     * @var array<string, list<string>>
     */
    private const STAGE_ALLOWED_STATUS = [
        'proposal' => ['draft', 'verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'],
        'verification' => ['verified', 'waiting', 'approved', 'active', 'disbursed', 'completed'],
        'disbursement' => ['waiting', 'approved', 'active', 'disbursed', 'completed'],
    ];

    public function __construct(
        private readonly SignatureTemplateService $signatures,
        private readonly SignatureImageService $signatureImages,
    ) {}

    /**
     * @return list<array{key: string, label: string, stage: string, signature: ?string, orientation: string, icon: string}>
     */
    public function availableDocuments(Loan $loan): array
    {
        $status = (string) $loan->status;
        $out = [];
        foreach (self::DOCUMENTS as $doc) {
            if (in_array($status, self::STAGE_ALLOWED_STATUS[$doc['stage']] ?? [], true)) {
                $out[] = $doc;
            }
        }

        return $out;
    }

    /**
     * Resolve dokumen meta, throws DomainException bila key tidak dikenal.
     *
     * @return array{key: string, label: string, stage: string, view: string, signature: ?string, orientation: string, icon: string}
     */
    public function resolve(string $documentKey): array
    {
        foreach (self::DOCUMENTS as $doc) {
            if ($doc['key'] === $documentKey) {
                return $doc;
            }
        }

        throw new DomainException("Tipe dokumen pinjaman tidak dikenal: {$documentKey}");
    }

    /**
     * Bangun payload Blade untuk view.
     *
     * @return array<string, mixed>
     */
    public function payload(Loan $loan, string $documentKey): array
    {
        $loan->loadMissing([
            'product:row_id,code,name,default_interest_rate,default_term_months',
            'borrower.group:row_id,name,code,address,organization_unit_row_id',
            'borrower.group.village:row_id,name',
            'borrower.group.village.parent:row_id,name',
            'committee',
            'beneficiaries.member.person',
            'beneficiaries.member.guarantor.person',
            'installments',
        ]);

        $meta = $this->resolve($documentKey);
        $tokens = $this->tokenReplacer($loan);
        $signature = $this->renderSignatureForDocument($meta, $loan);

        return [
            'identity' => $this->identityBlock(),
            'loan' => $this->loanBlock($loan),
            'group' => $this->groupBlock($loan),
            'committee' => $this->committeeBlock($loan),
            'beneficiaries' => $this->beneficiariesBlock($loan),
            'installments' => $this->installmentsBlock($loan),
            'document' => $meta,
            'tokens' => $tokens,
            'signature' => $signature,
            'today' => CarbonImmutable::now()->toDateString(),
            'today_label' => $this->formatDateIndo(CarbonImmutable::now()->toDateString()),
        ];
    }

    /**
     * Map token placeholder → nilai (naming modern, konsisten dengan domain new_siupk).
     *
     * @return array<string, string>
     */
    public function tokenReplacer(Loan $loan): array
    {
        $profile = OrganizationProfile::query()->first();
        $group = $loan->borrower?->group;
        $village = $group?->village;
        $district = $village?->parent;
        $committeeByPos = $this->committeeByPosition($loan);
        $firstBeneficiary = $loan->beneficiaries->sortBy('row_id')->first();
        $firstPerson = $firstBeneficiary?->member?->person;
        $firstGuarantor = $firstBeneficiary?->member?->guarantor;

        return [
            // Lembaga
            '{nama_lembaga}' => (string) ($profile?->legal_name ?: config('app.name')),
            '{nama_singkat}' => (string) ($profile?->short_name ?? ''),
            '{alamat_lembaga}' => (string) ($profile?->address ?? ''),
            '{telepon_lembaga}' => (string) ($profile?->phone ?? ''),
            '{email_lembaga}' => (string) ($profile?->email ?? ''),

            // Kelompok
            '{nama_kelompok}' => (string) ($group?->name ?? ''),
            '{kd_kelompok}' => (string) ($group?->code ?? ''),
            '{alamat_kelompok}' => (string) ($group?->address ?? ''),
            '{desa}' => (string) ($village?->name ?? ''),
            '{kecamatan}' => (string) ($district?->name ?? ''),

            // Pengurus kelompok aktif
            '{nama_ketua}' => $this->activeOfficerName($group?->row_id, 'chair'),
            '{nama_sekretaris}' => $this->activeOfficerName($group?->row_id, 'secretary'),
            '{nama_bendahara}' => $this->activeOfficerName($group?->row_id, 'treasurer'),

            // Pinjaman
            '{produk}' => (string) ($loan->product?->name ?? ''),
            '{no_pinjaman}' => (string) ($loan->loan_number ?? ''),
            '{alokasi}' => $this->money((float) $loan->principal_amount),
            '{jasa_persen}' => number_format((float) ($loan->service_rate_total ?? $loan->interest_rate ?? 0), 2, ',', '.'),
            '{jangka}' => $loan->term_months ? $loan->term_months.' bulan' : '',
            '{tgl_proposal}' => $this->formatDateIndo($loan->proposed_at?->toDateString()),
            '{tgl_verifikasi}' => $this->formatDateIndo($loan->verified_at?->toDateString()),
            '{tgl_cair}' => $this->formatDateIndo($loan->disbursed_at?->toDateString()),
            '{tgl_kondisi}' => $this->formatDateIndo(CarbonImmutable::now()->toDateString()),
            '{no_spk}' => (string) ($loan->loan_number ?? ''),
            '{keterangan_verifikasi}' => (string) ($loan->verification_notes ?? ''),

            // Pengurus pinjaman (snapshot LoanCommittee, diprioritaskan)
            '{ketua_pengurus}' => (string) ($committeeByPos['chair'] ?? ''),
            '{sekretaris_pengurus}' => (string) ($committeeByPos['secretary'] ?? ''),
            '{bendahara_pengurus}' => (string) ($committeeByPos['treasurer'] ?? ''),

            // Pemanfaat (default: anggota pertama; loop di Blade via array)
            '{pemanfaat_nama}' => (string) ($firstPerson?->full_name ?? ''),
            '{pemanfaat_nik}' => (string) ($firstPerson?->national_identity_number ?? ''),
            '{pemanfaat_penjamin}' => (string) ($firstGuarantor?->person?->full_name ?? ''),
            '{pemanfaat_alokasi}' => $this->money((float) ($firstBeneficiary?->allocated_amount ?? 0)),
        ];
    }

    /**
     * Render tanda tangan untuk dokumen tertentu dengan token yang sudah di-replace.
     */
    public function renderSignature(string $reportKey, Loan $loan): string
    {
        $tokens = $this->tokenReplacer($loan);
        $html = $this->signatures->get($reportKey);
        if ($html === '') {
            return '';
        }

        return $this->injectSignatureImage((string) strtr($html, $tokens), $reportKey);
    }

    /**
     * @return array{identity: array<string, string>, loan: array<string, mixed>, group: array<string, mixed>, committee: array<int, array<string, string>>, beneficiaries: array<int, array<string, mixed>>, document: array<string, mixed>, tokens: array<string, string>, signature: string, today: string, today_label: string}
     */
    private function injectSignatureImage(string $html, string $reportKey): string
    {
        $uri = $this->signatureImages->dataUri($reportKey);
        if ($uri === null) {
            return $html;
        }

        $img = '<img src="'.$uri.'" style="height:50px;max-width:180px;object-fit:contain" alt="Tanda Tangan" />';

        if (str_contains($html, '{ttd_image}')) {
            return str_replace('{ttd_image}', $img, $html);
        }

        return preg_replace('/(<p>(<br\s*\/?>\s*)+<\/p>)/i', $img.'$1', $html, 1) ?? $html;
    }

    private function payloadShape(): array
    {
        return [
            'identity' => [],
            'loan' => [],
            'group' => [],
            'committee' => [],
            'beneficiaries' => [],
            'installments' => [],
            'document' => [],
            'tokens' => [],
            'signature' => '',
            'today' => '',
            'today_label' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function identityBlock(): array
    {
        $profile = OrganizationProfile::query()->first();

        return [
            'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
            'short_name' => (string) ($profile?->short_name ?? ''),
            'registration_number' => (string) ($profile?->registration_number ?? ''),
            'address' => (string) ($profile?->address ?? ''),
            'phone' => (string) ($profile?->phone ?? ''),
            'email' => (string) ($profile?->email ?? ''),
            'district_name' => (string) ($profile?->district_name ?? ''),
            'regency_name' => (string) ($profile?->regency_name ?? ''),
            'logo_url' => $profile?->logo_url ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loanBlock(Loan $loan): array
    {
        return [
            'row_id' => (int) $loan->row_id,
            'id' => (int) $loan->id,
            'loan_number' => $loan->loan_number,
            'status' => (string) $loan->status,
            'product_code' => $loan->product?->code,
            'product_name' => $loan->product?->name,
            'principal_amount' => (float) $loan->principal_amount,
            'interest_rate' => (float) ($loan->service_rate_total ?? $loan->interest_rate ?? 0),
            'term_months' => (int) $loan->term_months,
            'proposed_at' => $loan->proposed_at?->toDateString(),
            'verified_at' => $loan->verified_at?->toDateString(),
            'approved_at' => $loan->approved_at?->toDateString(),
            'disbursed_at' => $loan->disbursed_at?->toDateString(),
            'verification_notes' => $loan->verification_notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function groupBlock(Loan $loan): array
    {
        $group = $loan->borrower?->group;

        return [
            'row_id' => $group?->row_id,
            'name' => (string) ($group?->name ?? ''),
            'code' => (string) ($group?->code ?? ''),
            'address' => (string) ($group?->address ?? ''),
            'village' => $group?->village?->name,
            'district' => $group?->village?->parent?->name,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function committeeBlock(Loan $loan): array
    {
        $out = [];
        foreach ($loan->committee as $row) {
            $out[] = [
                'position' => (string) $row->position,
                'name' => (string) ($row->member_name_snapshot ?? '—'),
                'snapshot_at' => $row->snapshot_at?->toDateString() ?? '',
            ];
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function committeeByPosition(Loan $loan): array
    {
        $byPos = [];
        foreach ($loan->committee as $row) {
            $byPos[(string) $row->position] = (string) ($row->member_name_snapshot ?? '');
        }

        return $byPos;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function beneficiariesBlock(Loan $loan): array
    {
        $rows = [];
        foreach ($loan->beneficiaries as $i => $b) {
            $person = $b->member?->person;
            $guarantor = $b->member?->guarantor?->person;
            $rows[] = [
                'no' => $i + 1,
                'member_row_id' => $b->member_row_id,
                'name' => (string) ($person?->full_name ?? '—'),
                'nik' => (string) ($person?->national_identity_number ?? ''),
                'proposed_amount' => (float) ($b->proposed_amount ?? 0),
                'verified_amount' => (float) ($b->verified_amount ?? 0),
                'allocated_amount' => (float) ($b->allocated_amount ?? 0),
                'guarantor' => (string) ($guarantor?->full_name ?? ''),
                'identity_photo_data' => $this->identityPhotoData($person?->identity_photo_path),
            ];
        }

        return $rows;
    }

    private function identityPhotoData(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);
        if ($contents === false || $contents === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    /**
     * Build rencana angsuran (jadwal angsuran + saldo berjalan).
     *
     * @return list<array{number: int, due_date: ?string, due_date_label: string, principal_due: float, interest_due: float, principal_paid: float, interest_paid: float, principal_remaining: float, interest_remaining: float, total_due: float, total_paid: float, status: string}>
     */
    private function installmentsBlock(Loan $loan): array
    {
        $grouped = [];
        foreach ($loan->installments as $inst) {
            $n = (int) $inst->installment_number;
            if ($n <= 0) {
                continue;
            }
            if (! isset($grouped[$n])) {
                $grouped[$n] = [
                    'number' => $n,
                    'due_date' => null,
                    'principal_due' => 0.0,
                    'interest_due' => 0.0,
                    'principal_paid' => 0.0,
                    'interest_paid' => 0.0,
                ];
            }
            $grouped[$n]['principal_due'] = round($grouped[$n]['principal_due'] + (float) $inst->principal_due, 2);
            $grouped[$n]['interest_due'] = round($grouped[$n]['interest_due'] + (float) $inst->interest_due, 2);
            $grouped[$n]['principal_paid'] = round($grouped[$n]['principal_paid'] + (float) $inst->principal_paid, 2);
            $grouped[$n]['interest_paid'] = round($grouped[$n]['interest_paid'] + (float) $inst->interest_paid, 2);
            $due = $inst->due_date?->format('Y-m-d');
            if ($due && ($grouped[$n]['due_date'] === null || $due < $grouped[$n]['due_date'])) {
                $grouped[$n]['due_date'] = $due;
            }
        }
        ksort($grouped);

        $out = [];
        foreach ($grouped as $g) {
            $pRem = max(0.0, round($g['principal_due'] - $g['principal_paid'], 2));
            $iRem = max(0.0, round($g['interest_due'] - $g['interest_paid'], 2));
            $totalDue = round($g['principal_due'] + $g['interest_due'], 2);
            $totalPaid = round($g['principal_paid'] + $g['interest_paid'], 2);
            $status = 'pending';
            if ($pRem <= 0.009 && $iRem <= 0.009 && $totalDue > 0) {
                $status = 'paid';
            } elseif ($totalPaid > 0.009) {
                $status = 'partial';
            }
            $out[] = [
                'number' => $g['number'],
                'due_date' => $g['due_date'],
                'due_date_label' => $this->formatDateIndo($g['due_date']),
                'principal_due' => $g['principal_due'],
                'interest_due' => $g['interest_due'],
                'principal_paid' => $g['principal_paid'],
                'interest_paid' => $g['interest_paid'],
                'principal_remaining' => $pRem,
                'interest_remaining' => $iRem,
                'total_due' => $totalDue,
                'total_paid' => $totalPaid,
                'status' => $status,
            ];
        }

        return $out;
    }

    /**
     * @param  array{key: string, label: string, stage: string, view: string, signature: ?string, orientation: string, icon: string}  $meta
     */
    private function renderSignatureForDocument(array $meta, Loan $loan): string
    {
        if ($meta['signature'] === null) {
            return '';
        }

        return $this->renderSignature($meta['signature'], $loan);
    }

    private function activeOfficerName(?int $groupRowId, string $position): string
    {
        if ($groupRowId === null) {
            return '';
        }

        $officer = GroupOfficer::query()
            ->where('group_row_id', $groupRowId)
            ->where('position', $position)
            ->whereNull('ended_at')
            ->with('member.person:row_id,full_name')
            ->orderByDesc('started_at')
            ->first();

        return (string) ($officer?->member?->person?->full_name ?? '');
    }

    private function formatDateIndo(?string $ymd): string
    {
        if ($ymd === null || $ymd === '') {
            return '—';
        }

        try {
            return CarbonImmutable::parse($ymd)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable) {
            return $ymd;
        }
    }

    private function money(float $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }
}
