import '../entities/verification_entities.dart';
import '../repositories/verification_repository.dart';

class GetProposalsUseCase {
  final VerificationRepository repository;

  GetProposalsUseCase({required this.repository});

  Future<List<ProposalItem>> call({String? search, String? status, int? villageId}) {
    return repository.getProposals(search: search, status: status, villageId: villageId);
  }
}

class GetProposalDetailUseCase {
  final VerificationRepository repository;

  GetProposalDetailUseCase({required this.repository});

  Future<ProposalDetail> call(int loanId) {
    return repository.getProposalDetail(loanId);
  }
}

class SubmitVerificationUseCase {
  final VerificationRepository repository;

  SubmitVerificationUseCase({required this.repository});

  Future<bool> call({
    required int loanId,
    required String verifiedAt,
    required double verificationAmount,
    String? verificationNotes,
    Map<int, double>? verifiedAmounts,
    double? latitude,
    double? longitude,
    Map<String, int>? scoring5C,
    String? signatureBase64,
  }) {
    return repository.submitVerification(
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
  }
}
