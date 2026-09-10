import 'package:dio/dio.dart';
import '../../../../core/error/exceptions.dart';
import '../../../../core/network/dio_client.dart';
import '../../../../core/utils/thermal_printer_service.dart';
import '../models/collection_models.dart';

abstract class CollectionRemoteDataSource {
  Future<List<CollectionLoanItemModel>> searchLoans({String? search, int? villageId});
  Future<LoanCollectionDetailModel> getLoanDetail(int loanId);
  Future<ReceiptData> payInstallment({
    required int loanId,
    required int memberId,
    required double principalAmount,
    required double interestAmount,
    double penaltyAmount = 0,
    int? cashAccountId,
    String? description,
  });
}

class CollectionRemoteDataSourceImpl implements CollectionRemoteDataSource {
  final DioClient dioClient;

  CollectionRemoteDataSourceImpl({required this.dioClient});

  @override
  Future<List<CollectionLoanItemModel>> searchLoans({String? search, int? villageId}) async {
    try {
      final response = await dioClient.dio.get(
        '/collection/loans',
        queryParameters: {
          if (search != null && search.isNotEmpty) 'search': search,
          if (villageId != null) 'village_id': villageId,
        },
      );

      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        final items = (data['data']['items'] as List<dynamic>?) ?? [];
        return items.map((e) => CollectionLoanItemModel.fromJson(e as Map<String, dynamic>)).toList();
      }
      throw ServerException(message: data['message'] ?? 'Gagal memuat pinjaman');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
    }
  }

  @override
  Future<LoanCollectionDetailModel> getLoanDetail(int loanId) async {
    try {
      final response = await dioClient.dio.get('/collection/loans/$loanId');
      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return LoanCollectionDetailModel.fromJson(data['data'] as Map<String, dynamic>);
      }
      throw ServerException(message: data['message'] ?? 'Gagal memuat detail pinjaman');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
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
      final response = await dioClient.dio.post(
        '/collection/loans/$loanId/pay',
        data: {
          'member_id': memberId,
          'principal_amount': principalAmount,
          'interest_amount': interestAmount,
          'penalty_amount': penaltyAmount,
          if (cashAccountId != null) 'cash_account_row_id': cashAccountId,
          if (description != null) 'description': description,
        },
      );

      final data = response.data;
      if (data is Map<String, dynamic> && data['success'] == true) {
        return ReceiptData.fromJson(data['data'] as Map<String, dynamic>);
      }
      throw ServerException(message: data['message'] ?? 'Pembayaran gagal');
    } on DioException catch (e) {
      if (e.error is ServerException) throw e.error as ServerException;
      throw NetworkException(message: 'Tidak dapat terhubung ke server');
    }
  }
}
