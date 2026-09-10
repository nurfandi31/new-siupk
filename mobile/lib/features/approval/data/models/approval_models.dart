import '../../domain/entities/approval_entities.dart';

class ExecutiveSummaryModel extends ExecutiveSummary {
  const ExecutiveSummaryModel({
    required super.asOfDate,
    required super.cashBalance,
    required super.bankBalance,
    required super.totalLiquidity,
    required super.activeLoansCount,
    required super.outstandingPrincipal,
    required super.pendingVerificationCount,
    required super.pendingApprovalCount,
    required super.todayCollectionsAmount,
    required super.todayCollectionsCount,
    required super.thisMonthDisbursedAmount,
  });

  factory ExecutiveSummaryModel.fromJson(Map<String, dynamic> json) {
    return ExecutiveSummaryModel(
      asOfDate: json['as_of_date'] as String? ?? '',
      cashBalance: (json['cash_balance'] as num?)?.toDouble() ?? 0.0,
      bankBalance: (json['bank_balance'] as num?)?.toDouble() ?? 0.0,
      totalLiquidity: (json['total_liquidity'] as num?)?.toDouble() ?? 0.0,
      activeLoansCount: json['active_loans_count'] as int? ?? 0,
      outstandingPrincipal: (json['outstanding_principal'] as num?)?.toDouble() ?? 0.0,
      pendingVerificationCount: json['pending_verification_count'] as int? ?? 0,
      pendingApprovalCount: json['pending_approval_count'] as int? ?? 0,
      todayCollectionsAmount: (json['today_collections_amount'] as num?)?.toDouble() ?? 0.0,
      todayCollectionsCount: json['today_collections_count'] as int? ?? 0,
      thisMonthDisbursedAmount: (json['this_month_disbursed_amount'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

class ApprovalItemModel extends ApprovalItem {
  const ApprovalItemModel({
    required super.id,
    required super.rowId,
    required super.loanNumber,
    required super.status,
    required super.productName,
    required super.borrowerName,
    required super.borrowerType,
    required super.villageName,
    super.proposedAt,
    super.verifiedAt,
    required super.proposedAmount,
    required super.verifiedAmount,
    required super.termMonths,
    required super.beneficiaryCount,
    super.verificationNotes,
  });

  factory ApprovalItemModel.fromJson(Map<String, dynamic> json) {
    return ApprovalItemModel(
      id: json['id'] as int? ?? 0,
      rowId: json['row_id'] as int? ?? 0,
      loanNumber: json['loan_number'] as String? ?? '-',
      status: json['status'] as String? ?? 'verified',
      productName: json['product_name'] as String? ?? 'Pinjaman',
      borrowerName: json['borrower_name'] as String? ?? 'Nasabah',
      borrowerType: json['borrower_type'] as String? ?? 'Kelompok',
      villageName: json['village_name'] as String? ?? '-',
      proposedAt: json['proposed_at'] as String?,
      verifiedAt: json['verified_at'] as String?,
      proposedAmount: (json['proposed_amount'] as num?)?.toDouble() ?? 0.0,
      verifiedAmount: (json['verified_amount'] as num?)?.toDouble() ?? 0.0,
      termMonths: json['term_months'] as int? ?? 0,
      beneficiaryCount: json['beneficiary_count'] as int? ?? 0,
      verificationNotes: json['verification_notes'] as String?,
    );
  }
}

class ApprovalBeneficiaryModel extends ApprovalBeneficiary {
  const ApprovalBeneficiaryModel({
    required super.memberRowId,
    required super.memberId,
    required super.fullName,
    required super.nik,
    required super.proposedAmount,
    required super.verifiedAmount,
    required super.allocatedAmount,
  });

  factory ApprovalBeneficiaryModel.fromJson(Map<String, dynamic> json) {
    return ApprovalBeneficiaryModel(
      memberRowId: json['member_row_id'] as int? ?? 0,
      memberId: json['member_id'] as int? ?? 0,
      fullName: json['full_name'] as String? ?? '-',
      nik: json['nik'] as String? ?? '-',
      proposedAmount: (json['proposed_amount'] as num?)?.toDouble() ?? 0.0,
      verifiedAmount: (json['verified_amount'] as num?)?.toDouble() ?? 0.0,
      allocatedAmount: (json['allocated_amount'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

class ApprovalDetailModel extends ApprovalDetail {
  const ApprovalDetailModel({
    required super.id,
    required super.rowId,
    required super.loanNumber,
    required super.status,
    required super.productName,
    required super.borrowerType,
    required super.borrowerName,
    required super.villageName,
    super.proposedAt,
    super.verifiedAt,
    required super.suggestedDisbursementDate,
    required super.proposedAmount,
    required super.verifiedAmount,
    required super.termMonths,
    super.verificationNotes,
    required super.beneficiaries,
  });

  factory ApprovalDetailModel.fromJson(Map<String, dynamic> json) {
    final list = (json['beneficiaries'] as List<dynamic>?) ?? [];
    return ApprovalDetailModel(
      id: json['id'] as int? ?? 0,
      rowId: json['row_id'] as int? ?? 0,
      loanNumber: json['loan_number'] as String? ?? '-',
      status: json['status'] as String? ?? 'verified',
      productName: json['product_name'] as String? ?? 'Pinjaman',
      borrowerType: json['borrower_type'] as String? ?? 'Kelompok',
      borrowerName: json['borrower_name'] as String? ?? 'Nasabah',
      villageName: json['village_name'] as String? ?? '-',
      proposedAt: json['proposed_at'] as String?,
      verifiedAt: json['verified_at'] as String?,
      suggestedDisbursementDate: json['suggested_disbursement_date'] as String? ?? '',
      proposedAmount: (json['proposed_amount'] as num?)?.toDouble() ?? 0.0,
      verifiedAmount: (json['verified_amount'] as num?)?.toDouble() ?? 0.0,
      termMonths: json['term_months'] as int? ?? 0,
      verificationNotes: json['verification_notes'] as String?,
      beneficiaries: list
          .map((e) => ApprovalBeneficiaryModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
