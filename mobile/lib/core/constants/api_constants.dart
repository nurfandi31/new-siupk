class ApiConstants {
  static const String baseUrl = 'http://10.0.2.2:8000/api/v1/mobile';
  
  // Auth endpoints
  static const String login = '/auth/login';
  static const String me = '/auth/me';
  static const String logout = '/auth/logout';
  
  // Collection & Installment endpoints
  static const String collectionLoans = '/collection/loans';
  static const String collectionLoanDetail = '/collection/loans/{id}';
  static const String collectionPay = '/collection/loans/{id}/pay';
  
  // Verification endpoints
  static const String verificationProposals = '/verification/proposals';
  static const String verificationDetail = '/verification/proposals/{id}';
  static const String verificationSubmit = '/verification/proposals/{id}/verify';
  
  // Executive Dashboard & Quick Approval
  static const String executiveSummary = '/executive/summary';
  static const String executiveApprovals = '/executive/approvals';
  static const String executiveApprovalDetail = '/executive/approvals/{id}';
  static const String executiveApprove = '/executive/approvals/{id}/approve';
  static const String executiveReject = '/executive/approvals/{id}/reject';
  
  // Headers
  static const String headerAuthorization = 'Authorization';
  static const String headerTenantCode = 'X-Tenant-Code';
  static const String headerAccept = 'Accept';
  static const String contentTypeJson = 'application/json';
}
