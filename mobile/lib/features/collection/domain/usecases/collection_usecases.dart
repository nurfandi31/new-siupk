import '../../../../core/utils/thermal_printer_service.dart';
import '../entities/collection_entities.dart';
import '../repositories/collection_repository.dart';

class SearchCollectionLoansUseCase {
  final CollectionRepository repository;
  SearchCollectionLoansUseCase({required this.repository});

  Future<List<CollectionLoanItem>> execute({String? search, int? villageId}) {
    return repository.searchLoans(search: search, villageId: villageId);
  }
}

class GetLoanDetailUseCase {
  final CollectionRepository repository;
  GetLoanDetailUseCase({required this.repository});

  Future<LoanCollectionDetail> execute(int loanId) {
    return repository.getLoanDetail(loanId);
  }
}

class PayInstallmentUseCase {
  final CollectionRepository repository;
  PayInstallmentUseCase({required this.repository});

  Future<ReceiptData> execute({
    required int loanId,
    required int memberId,
    required double principalAmount,
    required double interestAmount,
    double penaltyAmount = 0,
    int? cashAccountId,
    String? description,
  }) {
    return repository.payInstallment(
      loanId: loanId,
      memberId: memberId,
      principalAmount: principalAmount,
      interestAmount: interestAmount,
      penaltyAmount: penaltyAmount,
      cashAccountId: cashAccountId,
      description: description,
    );
  }
}
