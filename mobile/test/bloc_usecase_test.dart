import 'package:siupk_mobile/core/utils/whatsapp_helper.dart';
import 'package:siupk_mobile/features/auth/domain/entities/auth_entities.dart';
import 'package:siupk_mobile/features/collection/domain/entities/collection_entities.dart';
import 'package:siupk_mobile/features/verification/domain/entities/verification_entities.dart';
import 'package:siupk_mobile/features/approval/domain/entities/approval_entities.dart';

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

  print('=== SIUPK FLUTTER DOMAIN & UTILS TEST SUITE ===\n');

  // 1. WHATSAPP HELPER
  print('[1] Testing WhatsApp URL Generator & Phone Normalizer:');
  final waLink = WhatsAppHelper.generateLink(
    phoneNumber: '081234567890',
    message: 'Bukti Pembayaran Angsuran Rp 500.000',
  );
  assertTest('WhatsAppHelper normalizes Indonesian 08xx to 628xx',
      waLink.startsWith('https://wa.me/6281234567890'));
  assertTest('WhatsAppHelper encodes URL parameters safely',
      waLink.contains('text=Bukti%20Pembayaran%20Angsuran%20Rp%20500.000'));

  // 2. ENTITY IMMUTABILITY & VALUE EQUALITY
  print('\n[2] Testing Domain Entity Value Equality (Equatable):');
  const session1 = AuthSession(
    token: 'token_abc123',
    user: UserEntity(id: 1, name: 'User A', username: 'user_a', permissions: ['loans.verify']),
  );
  const session2 = AuthSession(
    token: 'token_abc123',
    user: UserEntity(id: 1, name: 'User A', username: 'user_a', permissions: ['loans.verify']),
  );
  assertTest('AuthSession objects with identical props are equal (Value Object)', session1 == session2);

  const proposal1 = ProposalItem(
    id: 1,
    rowId: 10,
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
  const proposal2 = ProposalItem(
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
  assertTest('ProposalItem objects support value equality', proposal1 == proposal2);

  // 3. EXECUTIVE SUMMARY CALCULATIONS
  print('\n[3] Testing Executive KPI Domain Calculations:');
  const execSummary = ExecutiveSummary(
    asOfDate: '2026-08-22',
    cashBalance: 25000000,
    bankBalance: 75000000,
    totalLiquidity: 100000000,
    activeLoansCount: 30,
    outstandingPrincipal: 150000000,
    pendingVerificationCount: 4,
    pendingApprovalCount: 2,
    todayCollectionsAmount: 3500000,
    todayCollectionsCount: 7,
    thisMonthDisbursedAmount: 40000000,
  );
  assertTest('ExecutiveSummary total liquidity equals cash + bank balances',
      execSummary.totalLiquidity == (execSummary.cashBalance + execSummary.bankBalance));

  // 4. COLLECTION BENEFICIARY & CASH ACCOUNT DATA
  print('\n[4] Testing Collection Beneficiary & Cash Account Options:');
  const cashAccount = CashAccountOption(
    id: 1,
    code: '1.1.01.01',
    name: 'Kas Tunai Teller Lapangan',
  );
  assertTest('CashAccountOption holds correct COA account code',
      cashAccount.code == '1.1.01.01' && cashAccount.name.contains('Kas Tunai'));

  const beneficiary = LoanBeneficiaryItem(
    id: 1,
    name: 'Ibu Maryam',
    nik: '3201018888',
    allocatedAmount: 5000000,
  );
  assertTest('LoanBeneficiaryItem holds allocated amount',
      beneficiary.allocatedAmount == 5000000);

  print('\n-----------------------------------------------------');
  print('TEST SUMMARY: $passed PASSED, $failed FAILED');
  print('-----------------------------------------------------');

  if (failed > 0) {
    throw Exception('$failed tests failed in Flutter BLoC & Domain test suite.');
  }
}
