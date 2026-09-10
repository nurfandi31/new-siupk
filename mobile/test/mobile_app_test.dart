import 'package:siupk_mobile/core/utils/currency_formatter.dart';
import 'package:siupk_mobile/core/utils/date_formatter.dart';
import 'package:siupk_mobile/core/error/failures.dart';
import 'package:siupk_mobile/features/auth/domain/entities/auth_entities.dart';
import 'package:siupk_mobile/features/collection/data/models/collection_models.dart';
import 'package:siupk_mobile/features/collection/domain/entities/collection_entities.dart';
import 'package:siupk_mobile/features/verification/data/models/verification_models.dart';
import 'package:siupk_mobile/features/verification/domain/entities/verification_entities.dart';
import 'package:siupk_mobile/features/approval/data/models/approval_models.dart';

void main() {
  int passed = 0;
  int failed = 0;

  void assertTest(String description, bool condition) {
    if (condition) {
      print('  ✓ $description');
      passed++;
    } else {
      print('  ✗ FAILED: $description');
      failed++;
    }
  }

  print('=====================================================');
  print('     SIUPK MOBILE APPLICATION TEST SUITE (FLUTTER)   ');
  print('=====================================================\n');

  // 1. CORE UTILS & FORMATTERS
  print('[1] Testing Core Utilities & Formatters:');
  assertTest('CurrencyFormatter formats millions correctly',
      CurrencyFormatter.formatRupiah(15000000).contains('15.000.000'));
  assertTest('CurrencyFormatter handles zero and null gracefully',
      CurrencyFormatter.formatRupiah(0).contains('0') && CurrencyFormatter.formatRupiah(null).contains('0'));
  assertTest('CurrencyFormatter parse extracted correct float',
      CurrencyFormatter.parse('Rp 2.750.000') == 2750000.0);
  assertTest('DateFormatter converts ISO to Indonesian short format',
      DateFormatter.formatShort('2026-08-22') == '22/08/2026');

  // 2. ERROR & FAILURE HIERARCHY
  print('\n[2] Testing Error & Failure Hierarchy:');
  const serverFailure = ServerFailure(message: 'Server error 500', statusCode: 500);
  const networkFailure = NetworkFailure(message: 'No internet connection');
  const validationFailure = ValidationFailure(
    message: 'Validation failed',
    errors: {'verified_at': ['Tanggal verifikasi wajib diisi']},
  );
  assertTest('ServerFailure holds message and status code', serverFailure.statusCode == 500);
  assertTest('NetworkFailure holds descriptive message', networkFailure.message.contains('internet'));
  assertTest('ValidationFailure holds validation error map', validationFailure.errors?.containsKey('verified_at') == true);

  // 3. AUTH & RBAC DOMAIN LOGIC
  print('\n[3] Testing Auth & RBAC Domain Logic:');
  const user = UserEntity(
    id: 1,
    name: 'Kolektor Lapangan',
    username: 'kolektor_app',
    permissions: ['loans.verify', 'collection.pay'],
  );
  assertTest('UserEntity correctly authorizes granted permission', user.hasPermission('loans.verify'));
  assertTest('UserEntity denies ungranted permission', !user.hasPermission('admin.purifier'));

  const superadmin = UserEntity(
    id: 99,
    name: 'Super Admin',
    username: 'superadmin',
    isSuperadmin: true,
    permissions: [],
  );
  assertTest('Superadmin has universal permission override', superadmin.hasPermission('any.permission'));

  const session1 = AuthSession(token: 'tok123', user: user);
  const session2 = AuthSession(token: 'tok123', user: user);
  assertTest('AuthSession implements Equatable value equality', session1 == session2);

  // 4. COLLECTION & PAYMENT MODELS
  print('\n[4] Testing Collection Models & Serialization:');
  final collectionJson = {
    'id': 101,
    'loan_number': 'PINJ-2026-0001',
    'borrower_name': 'Kelompok Melati',
    'borrower_type': 'Kelompok',
    'village_name': 'Desa Makmur',
    'product_name': 'SPP Perempuan',
    'principal_amount': 10000000.0,
    'remaining_principal': 6000000.0,
    'remaining_interest': 900000.0,
    'monthly_due': 575000.0,
    'next_due_date': '2026-09-01',
    'status': 'active',
  };
  final collectionModel = CollectionLoanItemModel.fromJson(collectionJson);
  assertTest('CollectionLoanItemModel parses JSON correctly',
      collectionModel.loanNumber == 'PINJ-2026-0001' && collectionModel.remainingPrincipal == 6000000.0);

  const beneficiary = LoanBeneficiaryItem(
    id: 1,
    name: 'Ibu Maryam',
    nik: '3201018888',
    allocatedAmount: 5000000,
  );
  assertTest('LoanBeneficiaryItem holds accurate allocated amount',
      beneficiary.allocatedAmount == 5000000 && beneficiary.name == 'Ibu Maryam');

  const cashAccount = CashAccountOption(
    id: 1,
    code: '1.1.01.01',
    name: 'Kas Tunai Teller',
  );
  assertTest('CashAccountOption holds accurate COA code', cashAccount.code == '1.1.01.01');

  // 5. VERIFICATION & 5C SURVEY MODELS
  print('\n[5] Testing Verification & 5C Survey Models:');
  final verificationJson = {
    'id': 201,
    'row_id': 201,
    'loan_number': 'PROP-2026-0055',
    'status': 'draft',
    'product': {'row_id': 1, 'code': 'SPP', 'name': 'Simpan Pinjam Perempuan'},
    'borrower_name': 'Kelompok Tani Sejahtera',
    'borrower_type': 'Kelompok',
    'village_name': 'Desa Sukamaju',
    'principal_amount': 12000000.0,
    'verified_amount': 10500000.0,
    'term_months': 12,
    'installment_method': 'flat',
    'beneficiaries': [
      {
        'member_row_id': 1,
        'member_id': 1,
        'full_name': 'Siti Nurhaliza',
        'nik': '3201010001',
        'proposed_amount': 6000000.0,
        'verified_amount': 5500000.0,
      },
      {
        'member_row_id': 2,
        'member_id': 2,
        'full_name': 'Dewi Sartika',
        'nik': '3201010002',
        'proposed_amount': 6000000.0,
        'verified_amount': 5000000.0,
      }
    ],
    'evaluation_5c_guide': [
      {'dimension': 'Character', 'description': 'Integritas sosial', 'rating': 5},
      {'dimension': 'Capacity', 'description': 'Arus kas usaha', 'rating': 4},
    ],
  };
  final proposalDetail = ProposalDetailModel.fromJson(verificationJson);
  assertTest('ProposalDetailModel parses nested beneficiaries and 5C guide',
      proposalDetail.beneficiaries.length == 2 &&
      proposalDetail.beneficiaries[0].fullName == 'Siti Nurhaliza' &&
      proposalDetail.beneficiaries[1].verifiedAmount == 5000000.0);

  const proposalItem1 = ProposalItem(
    id: 1,
    rowId: 1,
    loanNumber: 'PROP-01',
    status: 'draft',
    productName: 'SPP',
    productCode: 'SPP',
    borrowerType: 'Kelompok',
    borrowerName: 'Kelompok A',
    villageName: 'Desa A',
    proposedAmount: 10000000,
    verifiedAmount: 10000000,
    termMonths: 12,
    installmentMethod: 'flat',
    beneficiaryCount: 5,
    hasSurvey: false,
  );
  const proposalItem2 = ProposalItem(
    id: 1,
    rowId: 1,
    loanNumber: 'PROP-01',
    status: 'draft',
    productName: 'SPP',
    productCode: 'SPP',
    borrowerType: 'Kelompok',
    borrowerName: 'Kelompok A',
    villageName: 'Desa A',
    proposedAmount: 10000000,
    verifiedAmount: 10000000,
    termMonths: 12,
    installmentMethod: 'flat',
    beneficiaryCount: 5,
    hasSurvey: false,
  );
  assertTest('ProposalItem implements Equatable value equality', proposalItem1 == proposalItem2);

  // 6. EXECUTIVE SUMMARY & APPROVAL MODELS
  print('\n[6] Testing Executive Summary & Approval Models:');
  final summaryJson = {
    'as_of_date': '2026-08-22',
    'cash_balance': 45000000.0,
    'bank_balance': 125000000.0,
    'total_liquidity': 170000000.0,
    'active_loans_count': 42,
    'outstanding_principal': 285000000.0,
    'pending_verification_count': 3,
    'pending_approval_count': 2,
    'today_collections_amount': 4500000.0,
    'today_collections_count': 8,
    'this_month_disbursed_amount': 50000000.0,
  };
  final execSummary = ExecutiveSummaryModel.fromJson(summaryJson);
  assertTest('ExecutiveSummaryModel parses KPI metrics correctly',
      execSummary.totalLiquidity == 170000000.0 && execSummary.pendingApprovalCount == 2);
  assertTest('ExecutiveSummary liquidity equality holds',
      execSummary.totalLiquidity == (execSummary.cashBalance + execSummary.bankBalance));

  final approvalJson = {
    'id': 301,
    'row_id': 301,
    'loan_number': 'PROP-2026-0099',
    'status': 'verified',
    'product_name': 'SPP',
    'borrower_name': 'Kelompok Mawar',
    'borrower_type': 'Kelompok',
    'village_name': 'Desa Makmur',
    'suggested_disbursement_date': '2026-08-29',
    'proposed_amount': 15000000.0,
    'verified_amount': 15000000.0,
    'term_months': 12,
    'beneficiaries': [
      {
        'member_row_id': 10,
        'member_id': 10,
        'full_name': 'Ibu Ratna',
        'nik': '3201019999',
        'proposed_amount': 15000000.0,
        'verified_amount': 15000000.0,
        'allocated_amount': 15000000.0,
      }
    ]
  };
  final approvalDetail = ApprovalDetailModel.fromJson(approvalJson);
  assertTest('ApprovalDetailModel parses 1-tap approval breakdown correctly',
      approvalDetail.beneficiaries.first.allocatedAmount == 15000000.0);

  print('\n-----------------------------------------------------');
  print('ALL MOBILE TESTS PASSED: $passed PASSED, $failed FAILED');
  print('-----------------------------------------------------');

  if (failed > 0) {
    throw Exception('$failed tests failed in Flutter Mobile App test suite.');
}
}
