import '../entities/approval_entities.dart';
import '../repositories/approval_repository.dart';

class GetExecutiveSummaryUseCase {
  final ApprovalRepository repository;
  GetExecutiveSummaryUseCase({required this.repository});
  Future<ExecutiveSummary> call() => repository.getExecutiveSummary();
}

class GetApprovalQueueUseCase {
  final ApprovalRepository repository;
  GetApprovalQueueUseCase({required this.repository});
  Future<List<ApprovalItem>> call({String? search, int? villageId}) {
    return repository.getApprovals(search: search, villageId: villageId);
  }
}

class GetApprovalDetailUseCase {
  final ApprovalRepository repository;
  GetApprovalDetailUseCase({required this.repository});
  Future<ApprovalDetail> call(int loanId) => repository.getApprovalDetail(loanId);
}

class ApproveLoanUseCase {
  final ApprovalRepository repository;
  ApproveLoanUseCase({required this.repository});
  Future<bool> call({
    required int loanId,
    required String approvedAt,
    required String plannedDisbursedAt,
    required double allocatedPrincipal,
    String? allocationNotes,
    required List<Map<String, dynamic>> beneficiaries,
  }) {
    return repository.approveLoan(
      loanId: loanId,
      approvedAt: approvedAt,
      plannedDisbursedAt: plannedDisbursedAt,
      allocatedPrincipal: allocatedPrincipal,
      allocationNotes: allocationNotes,
      beneficiaries: beneficiaries,
    );
  }
}

class RejectLoanUseCase {
  final ApprovalRepository repository;
  RejectLoanUseCase({required this.repository});
  Future<bool> call({required int loanId, required String reason}) {
    return repository.rejectLoan(loanId: loanId, reason: reason);
  }
}
