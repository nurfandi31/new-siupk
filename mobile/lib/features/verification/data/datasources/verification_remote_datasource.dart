import 'package:dio/dio.dart';
import '../../../../core/constants/api_constants.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/network/dio_client.dart';
import '../models/verification_models.dart';

abstract class VerificationRemoteDataSource {
  Future<List<ProposalItemModel>> getProposals({String? search, String? status, int? villageId});
  Future<ProposalDetailModel> getProposalDetail(int loanId);
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

class VerificationRemoteDataSourceImpl implements VerificationRemoteDataSource {
  final DioClient dioClient;

  VerificationRemoteDataSourceImpl({required this.dioClient});

  @override
  Future<List<ProposalItemModel>> getProposals({String? search, String? status, int? villageId}) async {
    try {
      final response = await dioClient.dio.get(
        ApiConstants.verificationProposals,
        queryParameters: {
          if (search != null && search.isNotEmpty) 'search': search,
          if (status != null && status.isNotEmpty) 'status': status,
          if (villageId != null) 'village_id': villageId,
        },
      );

      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        final rawData = data['data'];
        final items = rawData is List
            ? rawData
            : (rawData is Map<String, dynamic> ? (rawData['items'] as List<dynamic>? ?? []) : []);
        return items.map((e) => ProposalItemModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      throw ServerException(message: data['message'] ?? 'Gagal memuat daftar proposal');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
    }
  }

  @override
  Future<ProposalDetailModel> getProposalDetail(int loanId) async {
    try {
      final url = ApiConstants.verificationDetail.replaceAll('{id}', loanId.toString());
      final response = await dioClient.dio.get(url);

      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return ProposalDetailModel.fromJson(data['data'] as Map<String, dynamic>);
      }
      throw ServerException(message: data['message'] ?? 'Gagal memuat detail proposal');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
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
      final url = ApiConstants.verificationSubmit.replaceAll('{id}', loanId.toString());
      final Map<String, dynamic> payload = {
        'verified_at': verifiedAt,
        'verification_amount': verificationAmount,
        if (verificationNotes != null) 'verification_notes': verificationNotes,
        if (verifiedAmounts != null)
          'verified_amounts': verifiedAmounts.map((k, v) => MapEntry(k.toString(), v)),
        if (latitude != null) 'latitude': latitude,
        if (longitude != null) 'longitude': longitude,
        if (scoring5C != null) 'scoring_5c': scoring5C,
        if (signatureBase64 != null) 'signature_base64': signatureBase64,
      };

      final response = await dioClient.dio.post(url, data: payload);
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return true;
      }
      throw ServerException(message: data['message'] ?? 'Gagal menyimpan hasil verifikasi');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Gagal mengirim data verifikasi ke server');
    }
  }
}
