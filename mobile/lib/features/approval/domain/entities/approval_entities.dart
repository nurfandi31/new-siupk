import 'package:equatable/equatable.dart';

class ExecutiveSummary extends Equatable {
  final String asOfDate;
  final double cashBalance;
  final double bankBalance;
  final double totalLiquidity;
  final int activeLoansCount;
  final double outstandingPrincipal;
  final int pendingVerificationCount;
  final int pendingApprovalCount;
  final double todayCollectionsAmount;
  final int todayCollectionsCount;
  final double thisMonthDisbursedAmount;

  const ExecutiveSummary({
    required this.asOfDate,
    required this.cashBalance,
    required this.bankBalance,
    required this.totalLiquidity,
    required this.activeLoansCount,
    required this.outstandingPrincipal,
    required this.pendingVerificationCount,
    required this.pendingApprovalCount,
    required this.todayCollectionsAmount,
    required this.todayCollectionsCount,
    required this.thisMonthDisbursedAmount,
  });

  @override
  List<Object?> get props => [
        asOfDate,
        cashBalance,
        bankBalance,
        totalLiquidity,
        activeLoansCount,
        outstandingPrincipal,
        pendingVerificationCount,
        pendingApprovalCount,
        todayCollectionsAmount,
        todayCollectionsCount,
        thisMonthDisbursedAmount,
      ];
}

class ApprovalItem extends Equatable {
  final int id;
  final int rowId;
  final String loanNumber;
  final String status;
  final String productName;
  final String borrowerName;
  final String borrowerType;
  final String villageName;
  final String? proposedAt;
  final String? verifiedAt;
  final double proposedAmount;
  final double verifiedAmount;
  final int termMonths;
  final int beneficiaryCount;
  final String? verificationNotes;

  const ApprovalItem({
    required this.id,
    required this.rowId,
    required this.loanNumber,
    required this.status,
    required this.productName,
    required this.borrowerName,
    required this.borrowerType,
    required this.villageName,
    this.proposedAt,
    this.verifiedAt,
    required this.proposedAmount,
    required this.verifiedAmount,
    required this.termMonths,
    required this.beneficiaryCount,
    this.verificationNotes,
  });

  @override
  List<Object?> get props => [
        id,
        rowId,
        loanNumber,
        status,
        productName,
        borrowerName,
        borrowerType,
        villageName,
        proposedAt,
        verifiedAt,
        proposedAmount,
        verifiedAmount,
        termMonths,
        beneficiaryCount,
        verificationNotes,
      ];
}

class ApprovalBeneficiary extends Equatable {
  final int memberRowId;
  final int memberId;
  final String fullName;
  final String nik;
  final double proposedAmount;
  final double verifiedAmount;
  final double allocatedAmount;

  const ApprovalBeneficiary({
    required this.memberRowId,
    required this.memberId,
    required this.fullName,
    required this.nik,
    required this.proposedAmount,
    required this.verifiedAmount,
    required this.allocatedAmount,
  });

  @override
  List<Object?> get props => [
        memberRowId,
        memberId,
        fullName,
        nik,
        proposedAmount,
        verifiedAmount,
        allocatedAmount,
      ];
}

class ApprovalDetail extends Equatable {
  final int id;
  final int rowId;
  final String loanNumber;
  final String status;
  final String productName;
  final String borrowerType;
  final String borrowerName;
  final String villageName;
  final String? proposedAt;
  final String? verifiedAt;
  final String suggestedDisbursementDate;
  final double proposedAmount;
  final double verifiedAmount;
  final int termMonths;
  final String? verificationNotes;
  final List<ApprovalBeneficiary> beneficiaries;

  const ApprovalDetail({
    required this.id,
    required this.rowId,
    required this.loanNumber,
    required this.status,
    required this.productName,
    required this.borrowerType,
    required this.borrowerName,
    required this.villageName,
    this.proposedAt,
    this.verifiedAt,
    required this.suggestedDisbursementDate,
    required this.proposedAmount,
    required this.verifiedAmount,
    required this.termMonths,
    this.verificationNotes,
    required this.beneficiaries,
  });

  @override
  List<Object?> get props => [
        id,
        rowId,
        loanNumber,
        status,
        productName,
        borrowerType,
        borrowerName,
        villageName,
        proposedAt,
        verifiedAt,
        suggestedDisbursementDate,
        proposedAmount,
        verifiedAmount,
        termMonths,
        verificationNotes,
        beneficiaries,
      ];
}
