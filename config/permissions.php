<?php

declare(strict_types=1);
use App\Http\Requests\Access\StoreTenantUserRequest;
use App\Http\Requests\Access\TenantRoleRequest;
use App\Http\Requests\Access\UpdateTenantUserRequest;
use App\Http\Requests\Accounting\AggregateJournalRequest;
use App\Http\Requests\Accounting\JournalEntryRequest;
use App\Http\Requests\Accounting\LoanInstallmentJournalRequest;
use App\Http\Requests\Accounting\ManualOpeningBalanceRequest;
use App\Http\Requests\Assets\AssetRequest;
use App\Http\Requests\Budgeting\SaveBudgetMonthRequest;
use App\Http\Requests\Lending\LoanApproveRequest;
use App\Http\Requests\Lending\LoanBeneficiaryWriteOffRequest;
use App\Http\Requests\Lending\LoanDisburseRequest;
use App\Http\Requests\Lending\LoanRequest;
use App\Http\Requests\Lending\LoanRescheduleCancelRequest;
use App\Http\Requests\Lending\LoanRescheduleRequest;
use App\Http\Requests\Lending\LoanUpdateRequest;
use App\Http\Requests\Lending\LoanVerifyRequest;
use App\Http\Requests\Lending\LoanWriteOffRequest;
use App\Http\Requests\MasterData\GroupRequest;
use App\Http\Requests\MasterData\MemberRequest;
use App\Http\Requests\MasterData\OtherInstitutionRequest;
use App\Http\Requests\MasterData\QuickMemberRequest;
use App\Http\Requests\MasterData\VillageRequest;
use App\Http\Requests\Settings\IdentityRequest;
use App\Http\Requests\Settings\LendingSystemRequest;
use App\Http\Requests\Settings\LogoUploadRequest;
use App\Http\Requests\Settings\OfflineAccessRequest;
use App\Http\Requests\Settings\OrchestratorRequest;
use App\Http\Requests\Settings\SignatureImageUploadRequest;
use App\Http\Requests\Settings\SignaturesRequest;
use App\Http\Requests\Settings\WhatsappInstanceRequest;
use App\Http\Requests\Settings\WhatsappRequest;
use App\Http\Requests\Website\SitePageRequest;
use App\Http\Requests\Website\SitePostRequest;
use App\Http\Requests\Website\SiteSettingRequest;

/**
 * Permission catalog + default role packs.
 *
 * roles table only stores assignment (code). Permission matrix lives here —
 * no role_permissions table until multi-tenant custom roles are needed.
 *
 * Users with zero assigned roles keep full access (legacy). Once a user has
 * any role, only the union of that role's permissions applies.
 *
 * Full link/button map: docs/RBAC_MATRIX.md
 */
return [
    'permissions' => [
        // Master
        'members.view',
        'members.manage',
        'groups.view',
        'groups.manage',
        'villages.view',
        'villages.manage',
        'institutions.view',
        'institutions.manage',
        // Public website content (blog & static pages)
        'website.view',
        'website.manage',
        // Lending
        'loans.view',
        'loans.propose',
        'loans.verify',
        'loans.approve',
        'loans.disburse',
        'loans.manage',
        // Accounting
        'journals.view',
        'journals.create',
        'installments.record',
        'assets.view',
        'assets.manage',
        'period_close.view',
        'period_close.manage',
        'reports.view',
        'reports.manage',
        'tax.view',
        // Budget / ops
        'budgeting.view',
        'budgeting.manage',
        'messages.send',
        // Tenant SaaS billing (pay platform invoices)
        'billing.view',
        'billing.pay',
        // Regency supervisory permissions
        'regency.view_reports',
        // Province supervisory permissions
        'province.view_reports',
        // Village operator permissions
        'village_user.access',
        // Settings / assistant
        'settings.manage',
        'assistant.use',
        // User & Role Access Management
        'portal.self',
        'users.view',
        'users.manage',
        'roles.view',
        'roles.manage',
    ],

    'roles' => [
        'admin' => [
            'name' => 'Administrator',
            'is_system' => true,
            'permissions' => ['*'],
        ],
        'kasir' => [
            'name' => 'Kasir / Teller',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'groups.view',
                'villages.view',
                'institutions.view',
                'loans.view',
                'journals.view',
                'journals.create',
                'installments.record',
                'assets.view',
                'reports.view',
                'tax.view',
                'budgeting.view',
                'messages.send',
                'billing.view',
                'billing.pay',
                'assistant.use',
            ],
        ],
        'verifikator' => [
            'name' => 'Verifikator',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'groups.view',
                'villages.view',
                'institutions.view',
                'loans.view',
                'loans.verify',
                'journals.view',
                'assets.view',
                'reports.view',
                'assistant.use',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'groups.view',
                'villages.view',
                'institutions.view',
                'loans.view',
                'journals.view',
                'assets.view',
                'period_close.view',
                'reports.view',
                'tax.view',
                'budgeting.view',
                'billing.view',
                'assistant.use',
            ],
        ],
        'regency_supervisor' => [
            'name' => 'Supervisor Kabupaten',
            'is_system' => true,
            'permissions' => [
                'regency.view_reports',
            ],
        ],
        'province_supervisor' => [
            'name' => 'Supervisor Provinsi',
            'is_system' => true,
            'permissions' => [
                'province.view_reports',
            ],
        ],
        'village_operator' => [
            'name' => 'Operator Desa',
            'is_system' => true,
            'permissions' => [
                'members.view',
                'members.manage',
                'groups.view',
                'groups.manage',
                'loans.view',
                'loans.propose',
                'reports.view',
                'village_user.access',
            ],
        ],
        'anggota' => [
            'name' => 'Anggota',
            'is_system' => true,
            'permissions' => [
                'portal.self',
            ],
        ],
    ],

    /**
     * Nav path prefix → permission required to show the link.
     * Longest prefix wins when matching. Missing = always show (auth only).
     */
    'nav_map' => [
        '/portal' => 'portal.self',
        '/master-data/villages' => 'villages.view',
        '/master-data/members/create' => 'members.manage',
        '/master-data/members' => 'members.view',
        '/master-data/groups/create' => 'groups.manage',
        '/master-data/groups' => 'groups.view',
        '/master-data/institutions/create' => 'institutions.manage',
        '/master-data/institutions' => 'institutions.view',
        '/lending/loans/create' => 'loans.propose',
        '/lending/loans' => 'loans.view',
        '/lending/reports' => 'loans.view',
        '/lending/simulation' => 'loans.view',
        '/accounting/journals' => 'journals.view',
        '/accounting/assets' => 'assets.view',
        '/accounting/journal-entries/installment' => 'installments.record',
        '/accounting/journal-entries' => 'journals.create',
        '/accounting/chart-of-accounts' => 'journals.view',
        '/accounting/period-close' => 'period_close.view',
        '/accounting/tax-estimate' => 'tax.view',
        '/accounting/reports' => 'reports.view',
        '/budgeting' => 'budgeting.view',
        '/notifications/billing' => 'messages.send',
        '/billing/invoices' => 'billing.view',
        '/access/users' => 'users.view',
        '/access/roles' => 'roles.view',
        '/website' => 'website.view',
        '/settings' => 'settings.manage',
        '/regency' => 'regency.view_reports',
        '/province' => 'province.view_reports',
    ],

    /**
     * Map HTTP FormRequest / assistant tool → required permission.
     * Missing key = no extra check beyond auth.
     */
    'request_map' => [
        JournalEntryRequest::class => 'journals.create',
        ManualOpeningBalanceRequest::class => 'journals.create',
        AggregateJournalRequest::class => 'journals.create',
        LoanInstallmentJournalRequest::class => 'installments.record',
        LoanRequest::class => 'loans.propose',
        LoanVerifyRequest::class => 'loans.verify',
        LoanApproveRequest::class => 'loans.approve',
        LoanDisburseRequest::class => 'loans.disburse',
        LoanUpdateRequest::class => 'loans.manage',
        LoanWriteOffRequest::class => 'loans.manage',
        LoanBeneficiaryWriteOffRequest::class => 'loans.manage',
        LoanRescheduleRequest::class => 'loans.manage',
        LoanRescheduleCancelRequest::class => 'loans.manage',
        MemberRequest::class => 'members.manage',
        QuickMemberRequest::class => 'members.manage',
        GroupRequest::class => 'groups.manage',
        SaveBudgetMonthRequest::class => 'budgeting.manage',
        IdentityRequest::class => 'settings.manage',
        LendingSystemRequest::class => 'settings.manage',
        LogoUploadRequest::class => 'settings.manage',
        WhatsappRequest::class => 'settings.manage',
        OfflineAccessRequest::class => 'settings.manage',
        WhatsappInstanceRequest::class => 'settings.manage',
        OrchestratorRequest::class => 'settings.manage',
        SignaturesRequest::class => 'settings.manage',
        SignatureImageUploadRequest::class => 'settings.manage',
        AssetRequest::class => 'assets.manage',
        VillageRequest::class => 'villages.manage',
        OtherInstitutionRequest::class => 'institutions.manage',
        SitePageRequest::class => 'website.manage',
        SitePostRequest::class => 'website.manage',
        SiteSettingRequest::class => 'website.manage',
        StoreTenantUserRequest::class => 'users.manage',
        UpdateTenantUserRequest::class => 'users.manage',
        TenantRoleRequest::class => 'roles.manage',
    ],

    'tool_map' => [
        'search_members' => 'members.view',
        'search_groups' => 'groups.view',
        'groups_with_loans' => 'groups.view',
        'search_loans' => 'loans.view',
        'get_loan' => 'loans.view',
        'list_accounts' => 'journals.view',
        'search_journals' => 'journals.view',
        'search_assets' => 'assets.view',
        'get_asset' => 'assets.view',
        'list_due_billing' => 'messages.send',
        'create_journal_entry' => 'journals.create',
        'reverse_journal' => 'journals.create',
        'record_installment' => 'installments.record',
        'send_billing_notices' => 'messages.send',
        'download_report' => 'reports.view',
        'simulate_loan' => 'loans.view',
    ],
];
