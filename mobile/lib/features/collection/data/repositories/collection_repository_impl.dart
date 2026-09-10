import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../../../core/storage/offline_queue_service.dart';
import '../../../../core/utils/thermal_printer_service.dart';
import '../../domain/entities/collection_entities.dart';
import '../../domain/repositories/collection_repository.dart';
import '../datasources/collection_remote_datasource.dart';

class CollectionRepositoryImpl implements CollectionRepository {
  final CollectionRemoteDataSource remoteDataSource;
  final OfflineQueueService offlineQueue;

  CollectionRepositoryImpl({
    required this.remoteDataSource,
    required this.offlineQueue,
  });

  @override
  Future<List<CollectionLoanItem>> searchLoans({String? search, int? villageId}) async {
    try {
      return await remoteDataSource.searchLoans(search: search, villageId: villageId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<LoanCollectionDetail> getLoanDetail(int loanId) async {
    try {
      return await remoteDataSource.getLoanDetail(loanId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<ReceiptData> payInstallment({
    required int loanId,
    required int memberId,
    required double principalAmount,
    required double interestAmount,
    double penaltyAmount = 0,
    int? cashAccountId,
    String? description,
  }) async {
    try {
      return await remoteDataSource.payInstallment(
        loanId: loanId,
        memberId: memberId,
        principalAmount: principalAmount,
        interestAmount: interestAmount,
        penaltyAmount: penaltyAmount,
        cashAccountId: cashAccountId,
        description: description,
      );
    } on NetworkException {
      await offlineQueue.enqueue(
        path: '/collection/loans/$loanId/pay',
        method: 'POST',
        payload: {
          'member_id': memberId,
          'principal_amount': principalAmount,
          'interest_amount': interestAmount,
          'penalty_amount': penaltyAmount,
          if (cashAccountId != null) 'cash_account_row_id': cashAccountId,
          if (description != null) 'description': description,
        },
      );

      throw OfflineQueuedFailure(
        message: 'Pembayaran disimpan di antrean offline dan akan dikirim otomatis.',
      );
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    }
  }
}