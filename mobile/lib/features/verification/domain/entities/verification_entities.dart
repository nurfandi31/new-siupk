import 'package:equatable/equatable.dart';

class ProposalItem extends Equatable {
  final int id;
  final int rowId;
  final String loanNumber;
  final String status;
  final String productName;
  final String productCode;
  final String borrowerType;
  final String borrowerName;
  final String villageName;
  final String? proposedAt;
  final double proposedAmount;
  final double verifiedAmount;
  final int termMonths;
  final String installmentMethod;
  final int beneficiaryCount;
  final bool hasSurvey;

  const ProposalItem({
    required this.id,
    required this.rowId,
    required this.loanNumber,
    required this.status,
    required this.productName,
    required this.productCode,
    required this.borrowerType,
    required this.borrowerName,
    required this.villageName,
    this.proposedAt,
    required this.proposedAmount,
    required this.verifiedAmount,
    required this.termMonths,
    required this.installmentMethod,
    required this.beneficiaryCount,
    required this.hasSurvey,
  });

  @override
  List<Object?> get props => [
        id,
        rowId,
        loanNumber,
        status,
        productName,
        productCode,
        borrowerType,
        borrowerName,
        villageName,
        proposedAt,
        proposedAmount,
        verifiedAmount,
        termMonths,
        installmentMethod,
        beneficiaryCount,
        hasSurvey,
      ];
}

class ProposalBeneficiary extends Equatable {
  final int memberRowId;
  final int memberId;
  final String fullName;
  final String nik;
  final String phone;
  final String address;
  final double proposedAmount;
  final double verifiedAmount;

  const ProposalBeneficiary({
    required this.memberRowId,
    required this.memberId,
    required this.fullName,
    required this.nik,
    required this.phone,
    required this.address,
    required this.proposedAmount,
    required this.verifiedAmount,
  });

  @override
  List<Object?> get props => [
        memberRowId,
        memberId,
        fullName,
        nik,
        phone,
        address,
        proposedAmount,
        verifiedAmount,
      ];
}

class Evaluation5CItem extends Equatable {
  final String dimension;
  final String description;
  final int rating;

  const Evaluation5CItem({
    required this.dimension,
    required this.description,
    required this.rating,
  });

  @override
  List<Object?> get props => [dimension, description, rating];
}

class ProposalDetail extends Equatable {
  final int id;
  final int rowId;
  final String loanNumber;
  final String status;
  final String productName;
  final String productCode;
  final String borrowerType;
  final String borrowerName;
  final String? groupName;
  final String groupAddress;
  final String villageName;
  final String? proposedAt;
  final String? verifiedAt;
  final double principalAmount;
  final double verifiedAmount;
  final int termMonths;
  final String installmentMethod;
  final String? verificationNotes;
  final String? guidanceNotes;
  final Map<String, dynamic> committee;
  final List<ProposalBeneficiary> beneficiaries;
  final List<Evaluation5CItem> evaluationGuide;

  const ProposalDetail({
    required this.id,
    required this.rowId,
    required this.loanNumber,
    required this.status,
    required this.productName,
    required this.productCode,
    required this.borrowerType,
    required this.borrowerName,
    this.groupName,
    required this.groupAddress,
    required this.villageName,
    this.proposedAt,
    this.verifiedAt,
    required this.principalAmount,
    required this.verifiedAmount,
    required this.termMonths,
    required this.installmentMethod,
    this.verificationNotes,
    this.guidanceNotes,
    required this.committee,
    required this.beneficiaries,
    required this.evaluationGuide,
  });

  @override
  List<Object?> get props => [
        id,
        rowId,
        loanNumber,
        status,
        productName,
        productCode,
        borrowerType,
        borrowerName,
        groupName,
        groupAddress,
        villageName,
        proposedAt,
        verifiedAt,
        principalAmount,
        verifiedAmount,
        termMonths,
        installmentMethod,
        verificationNotes,
        guidanceNotes,
        committee,
        beneficiaries,
        evaluationGuide,
      ];
}
