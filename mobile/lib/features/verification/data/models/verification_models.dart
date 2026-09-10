import '../../domain/entities/verification_entities.dart';

class ProposalItemModel extends ProposalItem {
  const ProposalItemModel({
    required super.id,
    required super.rowId,
    required super.loanNumber,
    required super.status,
    required super.productName,
    required super.productCode,
    required super.borrowerType,
    required super.borrowerName,
    required super.villageName,
    super.proposedAt,
    required super.proposedAmount,
    required super.verifiedAmount,
    required super.termMonths,
    required super.installmentMethod,
    required super.beneficiaryCount,
    required super.hasSurvey,
  });

  factory ProposalItemModel.fromJson(Map<String, dynamic> json) {
    return ProposalItemModel(
      id: json['id'] as int? ?? 0,
      rowId: json['row_id'] as int? ?? 0,
      loanNumber: json['loan_number'] as String? ?? '-',
      status: json['status'] as String? ?? 'draft',
      productName: json['product_name'] as String? ?? 'Pinjaman',
      productCode: json['product_code'] as String? ?? 'SPP',
      borrowerType: json['borrower_type'] as String? ?? 'Kelompok',
      borrowerName: json['borrower_name'] as String? ?? 'Nasabah',
      villageName: json['village_name'] as String? ?? '-',
      proposedAt: json['proposed_at'] as String?,
      proposedAmount: (json['proposed_amount'] as num?)?.toDouble() ?? 0.0,
      verifiedAmount: (json['verified_amount'] as num?)?.toDouble() ?? 0.0,
      termMonths: json['term_months'] as int? ?? 0,
      installmentMethod: json['installment_method'] as String? ?? 'flat',
      beneficiaryCount: json['beneficiary_count'] as int? ?? 0,
      hasSurvey: json['has_survey'] as bool? ?? false,
    );
  }
}

class ProposalBeneficiaryModel extends ProposalBeneficiary {
  const ProposalBeneficiaryModel({
    required super.memberRowId,
    required super.memberId,
    required super.fullName,
    required super.nik,
    required super.phone,
    required super.address,
    required super.proposedAmount,
    required super.verifiedAmount,
  });

  factory ProposalBeneficiaryModel.fromJson(Map<String, dynamic> json) {
    return ProposalBeneficiaryModel(
      memberRowId: json['member_row_id'] as int? ?? 0,
      memberId: json['member_id'] as int? ?? 0,
      fullName: json['full_name'] as String? ?? '-',
      nik: json['nik'] as String? ?? '-',
      phone: json['phone'] as String? ?? '-',
      address: json['address'] as String? ?? '-',
      proposedAmount: (json['proposed_amount'] as num?)?.toDouble() ?? 0.0,
      verifiedAmount: (json['verified_amount'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

class ProposalDetailModel extends ProposalDetail {
  const ProposalDetailModel({
    required super.id,
    required super.rowId,
    required super.loanNumber,
    required super.status,
    required super.productName,
    required super.productCode,
    required super.borrowerType,
    required super.borrowerName,
    super.groupName,
    required super.groupAddress,
    required super.villageName,
    super.proposedAt,
    super.verifiedAt,
    required super.principalAmount,
    required super.verifiedAmount,
    required super.termMonths,
    required super.installmentMethod,
    super.verificationNotes,
    super.guidanceNotes,
    required super.committee,
    required super.beneficiaries,
    required super.evaluationGuide,
  });

  factory ProposalDetailModel.fromJson(Map<String, dynamic> json) {
    final beneficiariesList = (json['beneficiaries'] as List<dynamic>?) ?? [];
    final guideList = (json['evaluation_5c_guide'] as List<dynamic>?) ?? [];

    return ProposalDetailModel(
      id: json['id'] as int? ?? 0,
      rowId: json['row_id'] as int? ?? 0,
      loanNumber: json['loan_number'] as String? ?? '-',
      status: json['status'] as String? ?? 'draft',
      productName: json['product']?['name'] as String? ?? 'Pinjaman',
      productCode: json['product']?['code'] as String? ?? 'SPP',
      borrowerType: json['borrower_type'] as String? ?? 'Kelompok',
      borrowerName: json['borrower_name'] as String? ?? 'Nasabah',
      groupName: json['group_name'] as String?,
      groupAddress: json['group_address'] as String? ?? '-',
      villageName: json['village_name'] as String? ?? '-',
      proposedAt: json['proposed_at'] as String?,
      verifiedAt: json['verified_at'] as String?,
      principalAmount: (json['principal_amount'] as num?)?.toDouble() ?? 0.0,
      verifiedAmount: (json['verified_amount'] as num?)?.toDouble() ?? 0.0,
      termMonths: json['term_months'] as int? ?? 0,
      installmentMethod: json['installment_method'] as String? ?? 'flat',
      verificationNotes: json['verification_notes'] as String?,
      guidanceNotes: json['guidance_notes'] as String?,
      committee: (json['committee'] as Map<String, dynamic>?) ?? {},
      beneficiaries: beneficiariesList
          .map((e) => ProposalBeneficiaryModel.fromJson(e as Map<String, dynamic>))
          .toList(),
      evaluationGuide: guideList.map((e) {
        final g = e as Map<String, dynamic>;
        return Evaluation5CItem(
          dimension: g['dimension'] as String? ?? '',
          description: g['description'] as String? ?? '',
          rating: g['rating'] as int? ?? 5,
        );
      }).toList(),
    );
  }
}
