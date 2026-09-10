import 'package:dio/dio.dart';
import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/network/dio_client.dart';
import '../models/approval_models.dart';

abstract class ApprovalRemoteDataSource {
  Future<ExecutiveSummaryModel> getExecutiveSummary();
  Future<List<ApprovalItemModel>> getApprovals({String? search, int? villageId});
  Future<ApprovalDetailModel> getApprovalDetail(int loanId);
  Future<bool> approveLoan({
    required int loanId,
    required String approvedAt,
    required String plannedDisbursedAt,
    required double allocatedPrincipal,
    String? allocationNotes,
    required List<Map<String, dynamic>> beneficiaries,
  });
  Future<bool> rejectLoan({required int loanId, required String reason});
}

class ApprovalRemoteDataSourceImpl implements ApprovalRemoteDataSource {
  final DioClient dioClient;

  ApprovalRemoteDataSourceImpl({required this.dioClient});

  @override
  Future<ExecutiveSummaryModel> getExecutiveSummary() async {
    try {
      final response = await dioClient.dio.get(ApiConstants.executiveSummary);
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return ExecutiveSummaryModel.fromJson(data['data'] as Map<String, dynamic>);
      }
      throw ServerException(message: data['message'] ?? 'Gagal memuat ringkasan eksekutif');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
    }
  }

  @override
  Future<List<ApprovalItemModel>> getApprovals({String? search, int? villageId}) async {
    try {
      final response = await dioClient.dio.get(
        ApiConstants.executiveApprovals,
        queryParameters: {
          if (search != null && search.isNotEmpty) 'search': search,
          if (villageId != null) 'village_id': villageId,
        },
      );
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        final rawData = data['data'];
        final items = rawData is List
            ? rawData
            : (rawData is Map<String, dynamic> ? (rawData['items'] as List<dynamic>? ?? []) : []);
        return items.map((e) => ApprovalItemModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      throw ServerException(message: data['message'] ?? 'Gagal memuat antrean persetujuan');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
    }
  }

  @override
  Future<ApprovalDetailModel> getApprovalDetail(int loanId) async {
    try {
      final url = ApiConstants.executiveApprovalDetail.replaceAll('{id}', loanId.toString());
      final response = await dioClient.dio.get(url);
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return ApprovalDetailModel.fromJson(data['data'] as Map<String, dynamic>);
      }
      throw ServerException(message: data['message'] ?? 'Gagal memuat detail persetujuan');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
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
      final url = ApiConstants.executiveApprove.replaceAll('{id}', loanId.toString());
      final payload = {
        'approved_at': approvedAt,
        'planned_disbursed_at': plannedDisbursedAt,
        'allocated_principal': allocatedPrincipal,
        if (allocationNotes != null) 'allocation_notes': allocationNotes,
        'beneficiaries': beneficiaries,
      };
      final response = await dioClient.dio.post(url, data: payload);
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return true;
      }
      throw ServerException(message: data['message'] ?? 'Gagal memproses persetujuan pinjaman');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Gagal mengirim persetujuan ke server');
    }
  }

  @override
  Future<bool> rejectLoan({required int loanId, required String reason}) async {
    try {
      final url = ApiConstants.executiveReject.replaceAll('{id}', loanId.toString());
      final response = await dioClient.dio.post(url, data: {'reason': reason});
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return true;
      }
      throw ServerException(message: data['message'] ?? 'Gagal menolak proposal pinjaman');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Gagal mengirim penolakan ke server');
    }
  }
}
