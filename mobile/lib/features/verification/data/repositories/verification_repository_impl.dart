import '../../../../core/error/exceptions.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/verification_entities.dart';
import '../../domain/repositories/verification_repository.dart';
import '../datasources/verification_remote_datasource.dart';

class VerificationRepositoryImpl implements VerificationRepository {
  final VerificationRemoteDataSource remoteDataSource;

  VerificationRepositoryImpl({required this.remoteDataSource});

  @override
  Future<List<ProposalItem>> getProposals({String? search, String? status, int? villageId}) async {
    try {
      return await remoteDataSource.getProposals(search: search, status: status, villageId: villageId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<ProposalDetail> getProposalDetail(int loanId) async {
    try {
      return await remoteDataSource.getProposalDetail(loanId);
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }

  @override
  Future<bool> submitVerification({
    required int loanId,
    required String verifiedAt,
    required double verificationAmount,
    String? verificationNotes,
    Map<int, double>? verifiedAmounts,
    double? latitude,
    double? longitude,
    Map<String, int>? scoring5C,
    String? signatureBase64,
  }) async {
    try {
      return await remoteDataSource.submitVerification(
        loanId: loanId,
        verifiedAt: verifiedAt,
        verificationAmount: verificationAmount,
        verificationNotes: verificationNotes,
        verifiedAmounts: verifiedAmounts,
        latitude: latitude,
        longitude: longitude,
        scoring5C: scoring5C,
        signatureBase64: signatureBase64,
      );
    } on ServerException catch (e) {
      throw ServerFailure(message: e.message);
    } on NetworkException catch (e) {
      throw NetworkFailure(message: e.message);
    }
  }
}
