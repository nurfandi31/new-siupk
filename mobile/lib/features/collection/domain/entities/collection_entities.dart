import 'package:equatable/equatable.dart';

class CollectionLoanItem extends Equatable {
  final int id;
  final String loanNumber;
  final String borrowerName;
  final String borrowerType;
  final String villageName;
  final String productName;
  final double principalAmount;
  final double remainingPrincipal;
  final double remainingInterest;
  final double monthlyDue;
  final String? nextDueDate;
  final String status;

  const CollectionLoanItem({
    required this.id,
    required this.loanNumber,
    required this.borrowerName,
    required this.borrowerType,
    required this.villageName,
    required this.productName,
    required this.principalAmount,
    required this.remainingPrincipal,
    required this.remainingInterest,
    required this.monthlyDue,
    this.nextDueDate,
    required this.status,
  });

  @override
  List<Object?> get props => [
        id,
        loanNumber,
        borrowerName,
        borrowerType,
        villageName,
        productName,
        principalAmount,
        remainingPrincipal,
        remainingInterest,
        monthlyDue,
        nextDueDate,
        status,
      ];
}

class LoanBeneficiaryItem extends Equatable {
  final int id;
  final String name;
  final String? nik;
  final String? phone;
  final double allocatedAmount;

  const LoanBeneficiaryItem({
    required this.id,
    required this.name,
    this.nik,
    this.phone,
    required this.allocatedAmount,
  });

  @override
  List<Object?> get props => [id, name, nik, phone, allocatedAmount];
}

class CashAccountOption extends Equatable {
  final int id;
  final String code;
  final String name;

  const CashAccountOption({
    required this.id,
    required this.code,
    required this.name,
  });

  @override
  List<Object?> get props => [id, code, name];
}

class LoanCollectionDetail extends Equatable {
  final int id;
  final String loanNumber;
  final String borrowerName;
  final String borrowerType;
  final String villageName;
  final String productName;
  final double principalAmount;
  final double remainingPrincipal;
  final double remainingInterest;
  final double suggestedPrincipal;
  final double suggestedInterest;
  final int nextInstallmentNumber;
  final String? nextDueDate;
  final List<LoanBeneficiaryItem> beneficiaries;
  final List<CashAccountOption> cashAccounts;

  const LoanCollectionDetail({
    required this.id,
    required this.loanNumber,
    required this.borrowerName,
    required this.borrowerType,
    required this.villageName,
    required this.productName,
    required this.principalAmount,
    required this.remainingPrincipal,
    required this.remainingInterest,
    required this.suggestedPrincipal,
    required this.suggestedInterest,
    required this.nextInstallmentNumber,
    this.nextDueDate,
    required this.beneficiaries,
    required this.cashAccounts,
  });

  @override
  List<Object?> get props => [
        id,
        loanNumber,
        borrowerName,
        borrowerType,
        villageName,
        productName,
        principalAmount,
        remainingPrincipal,
        remainingInterest,
        suggestedPrincipal,
        suggestedInterest,
        nextInstallmentNumber,
        nextDueDate,
        beneficiaries,
        cashAccounts,
      ];
}
