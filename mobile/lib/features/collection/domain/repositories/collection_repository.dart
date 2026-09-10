import '../../../../core/utils/thermal_printer_service.dart';
import '../entities/collection_entities.dart';

abstract class CollectionRepository {
  Future<List<CollectionLoanItem>> searchLoans({String? search, int? villageId});
  Future<LoanCollectionDetail> getLoanDetail(int loanId);
  Future<ReceiptData> payInstallment({
    required int loanId,
    required int memberId,
    required double principalAmount,
    required double interestAmount,
    double penaltyAmount = 0,
    int? cashAccountId,
    String? description,
  });
}
