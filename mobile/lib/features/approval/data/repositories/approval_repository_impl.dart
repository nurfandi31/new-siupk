import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/approval_entities.dart';
import '../../domain/repositories/approval_repository.dart';
import '../datasources/approval_remote_datasource.dart';

class ApprovalRepositoryImpl implements ApprovalRepository {
  final ApprovalRemoteDataSource remoteDataSource;

  ApprovalRepositoryImpl({required this.remoteDataSource});

  @override
  Future<ExecutiveSummary> getExecutiveSummary() async {
    try {
      return await remoteDataSource.getExecutiveSummary();
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<List<ApprovalItem>> getApprovals({String? search, int? villageId}) async {
    try {
      return await remoteDataSource.getApprovals(search: search, villageId: villageId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<ApprovalDetail> getApprovalDetail(int loanId) async {
    try {
      return await remoteDataSource.getApprovalDetail(loanId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<bool> approveLoan({
    required int loanId,
    required String approvedAt,
    required String plannedDisbursedAt,
    required double allocatedPrincipal,
    String? allocationNotes,
    required List<Map<String, dynamic>> beneficiaries,
  }) async {
    try {
      return await remoteDataSource.approveLoan(
        loanId: loanId,
        approvedAt: approvedAt,
        plannedDisbursedAt: plannedDisbursedAt,
        allocatedPrincipal: allocatedPrincipal,
        allocationNotes: allocationNotes,
        beneficiaries: beneficiaries,
      );
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<bool> rejectLoan({required int loanId, required String reason}) async {
    try {
      return await remoteDataSource.rejectLoan(loanId: loanId, reason: reason);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }
}
