import '../entities/approval_entities.dart';

abstract class ApprovalRepository {
  Future<ExecutiveSummary> getExecutiveSummary();
  Future<List<ApprovalItem>> getApprovals({String? search, int? villageId});
  Future<ApprovalDetail> getApprovalDetail(int loanId);
  Future<bool> approveLoan({
    required int loanId,
    required String approvedAt,
    required String plannedDisbursedAt,
    required double allocatedPrincipal,
    String? allocationNotes,
    required List<Map<String, dynamic>> beneficiaries,
  });
  Future<bool> rejectLoan({
    required int loanId,
    required String reason,
  });
}
