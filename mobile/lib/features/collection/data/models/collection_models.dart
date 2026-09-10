import '../../domain/entities/collection_entities.dart';

class CollectionLoanItemModel extends CollectionLoanItem {
  const CollectionLoanItemModel({
    required super.id,
    required super.loanNumber,
    required super.borrowerName,
    required super.borrowerType,
    required super.villageName,
    required super.productName,
    required super.principalAmount,
    required super.remainingPrincipal,
    required super.remainingInterest,
    required super.monthlyDue,
    super.nextDueDate,
    required super.status,
  });

  factory CollectionLoanItemModel.fromJson(Map<String, dynamic> json) {
    return CollectionLoanItemModel(
      id: json['id'] as int? ?? 0,
      loanNumber: json['loan_number'] as String? ?? '-',
      borrowerName: json['borrower_name'] as String? ?? '-',
      borrowerType: json['borrower_type'] as String? ?? 'Kelompok',
      villageName: json['village_name'] as String? ?? '-',
      productName: json['product_name'] as String? ?? 'SPP',
      principalAmount: (json['principal_amount'] as num?)?.toDouble() ?? 0.0,
      remainingPrincipal: (json['remaining_principal'] as num?)?.toDouble() ?? 0.0,
      remainingInterest: (json['remaining_interest'] as num?)?.toDouble() ?? 0.0,
      monthlyDue: (json['monthly_due'] as num?)?.toDouble() ?? 0.0,
      nextDueDate: json['next_due_date'] as String?,
      status: json['status'] as String? ?? 'active',
    );
  }
}

class LoanCollectionDetailModel extends LoanCollectionDetail {
  const LoanCollectionDetailModel({
    required super.id,
    required super.loanNumber,
    required super.borrowerName,
    required super.borrowerType,
    required super.villageName,
    required super.productName,
    required super.principalAmount,
    required super.remainingPrincipal,
    required super.remainingInterest,
    required super.suggestedPrincipal,
    required super.suggestedInterest,
    required super.nextInstallmentNumber,
    super.nextDueDate,
    required super.beneficiaries,
    required super.cashAccounts,
  });

  factory LoanCollectionDetailModel.fromJson(Map<String, dynamic> json) {
    final benList = (json['beneficiaries'] as List<dynamic>?)
            ?.map((e) => LoanBeneficiaryItem(
                  id: e['id'] as int? ?? 0,
                  name: e['name'] as String? ?? '-',
                  nik: e['nik'] as String?,
                  phone: e['phone'] as String?,
                  allocatedAmount: (e['allocated_amount'] as num?)?.toDouble() ?? 0.0,
                ))
            .toList() ??
        [];

    final cashList = (json['cash_accounts'] as List<dynamic>?)
            ?.map((e) => CashAccountOption(
                  id: e['id'] as int? ?? 0,
                  code: e['code'] as String? ?? '',
                  name: e['name'] as String? ?? '',
                ))
            .toList() ??
        [];

    return LoanCollectionDetailModel(
      id: json['id'] as int? ?? 0,
      loanNumber: json['loan_number'] as String? ?? '-',
      borrowerName: json['borrower_name'] as String? ?? '-',
      borrowerType: json['borrower_type'] as String? ?? 'Kelompok',
      villageName: json['village_name'] as String? ?? '-',
      productName: json['product_name'] as String? ?? 'SPP',
      principalAmount: (json['principal_amount'] as num?)?.toDouble() ?? 0.0,
      remainingPrincipal: (json['remaining_principal'] as num?)?.toDouble() ?? 0.0,
      remainingInterest: (json['remaining_interest'] as num?)?.toDouble() ?? 0.0,
      suggestedPrincipal: (json['suggested_principal'] as num?)?.toDouble() ?? 0.0,
      suggestedInterest: (json['suggested_interest'] as num?)?.toDouble() ?? 0.0,
      nextInstallmentNumber: json['next_installment_number'] as int? ?? 1,
      nextDueDate: json['next_due_date'] as String?,
      beneficiaries: benList,
      cashAccounts: cashList,
    );
  }
}
