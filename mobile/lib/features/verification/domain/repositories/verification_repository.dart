import '../entities/verification_entities.dart';

abstract class VerificationRepository {
  Future<List<ProposalItem>> getProposals({String? search, String? status, int? villageId});
  Future<ProposalDetail> getProposalDetail(int loanId);
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
  });
}
