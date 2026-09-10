import 'package:get_it/get_it.dart';
import 'core/network/dio_client.dart';
import 'core/network/mobile_offline_sync_service.dart';
import 'core/storage/offline_queue_service.dart';
import 'core/storage/secure_storage_service.dart';
import 'core/utils/thermal_printer_service.dart';
import 'features/approval/data/datasources/approval_remote_datasource.dart';
import 'features/approval/data/repositories/approval_repository_impl.dart';
import 'features/approval/domain/repositories/approval_repository.dart';
import 'features/approval/domain/usecases/approval_usecases.dart';
import 'features/approval/presentation/bloc/approval_bloc.dart';
import 'features/auth/data/datasources/auth_remote_datasource.dart';
import 'features/auth/data/repositories/auth_repository_impl.dart';
import 'features/auth/domain/repositories/auth_repository.dart';
import 'features/auth/domain/usecases/auth_usecases.dart';
import 'features/auth/presentation/bloc/auth_bloc.dart';
import 'features/collection/data/datasources/collection_remote_datasource.dart';
import 'features/collection/data/repositories/collection_repository_impl.dart';
import 'features/collection/domain/repositories/collection_repository.dart';
import 'features/collection/domain/usecases/collection_usecases.dart';
import 'features/collection/presentation/bloc/collection_bloc.dart';
import 'features/verification/data/datasources/verification_remote_datasource.dart';
import 'features/verification/data/repositories/verification_repository_impl.dart';
import 'features/verification/domain/repositories/verification_repository.dart';
import 'features/verification/domain/usecases/verification_usecases.dart';
import 'features/verification/presentation/bloc/verification_bloc.dart';

final sl = GetIt.instance;

Future<void> initDependencies() async {
  // Core
  sl.registerLazySingleton<SecureStorageService>(() => SecureStorageService());
  sl.registerLazySingleton<OfflineQueueService>(() => OfflineQueueService());
  sl.registerLazySingleton<DioClient>(() => DioClient(storageService: sl()));
  sl.registerLazySingleton<MobileOfflineSyncService>(() => MobileOfflineSyncService(
    dio: sl<DioClient>().dio,
    queue: sl<OfflineQueueService>(),
    storage: sl<SecureStorageService>(),
  ));
  sl.registerLazySingleton<ThermalPrinterService>(() => ThermalPrinterService());

  // Features - Auth
  sl.registerLazySingleton<AuthRemoteDataSource>(
    () => AuthRemoteDataSourceImpl(dioClient: sl()),
  );
  sl.registerLazySingleton<AuthRepository>(
    () => AuthRepositoryImpl(
      remoteDataSource: sl(),
      storageService: sl(),
    ),
  );
  sl.registerLazySingleton(() => LoginUseCase(repository: sl()));
  sl.registerLazySingleton(() => GetCurrentSessionUseCase(repository: sl()));
  sl.registerLazySingleton(() => LogoutUseCase(repository: sl()));
  sl.registerFactory(
    () => AuthBloc(
      loginUseCase: sl(),
      getCurrentSessionUseCase: sl(),
      logoutUseCase: sl(),
    ),
  );

  // Features - Collection
  sl.registerLazySingleton<CollectionRemoteDataSource>(
    () => CollectionRemoteDataSourceImpl(dioClient: sl()),
  );
  sl.registerLazySingleton<CollectionRepository>(
    () => CollectionRepositoryImpl(remoteDataSource: sl(), offlineQueue: sl()),
  );
  sl.registerLazySingleton(() => SearchCollectionLoansUseCase(repository: sl()));
  sl.registerLazySingleton(() => GetLoanDetailUseCase(repository: sl()));
  sl.registerLazySingleton(() => PayInstallmentUseCase(repository: sl()));
  sl.registerFactory(
    () => CollectionBloc(
      searchLoansUseCase: sl(),
      getLoanDetailUseCase: sl(),
      payInstallmentUseCase: sl(),
    ),
  );

  // Features - Verification
  sl.registerLazySingleton<VerificationRemoteDataSource>(
    () => VerificationRemoteDataSourceImpl(dioClient: sl()),
  );
  sl.registerLazySingleton<VerificationRepository>(
    () => VerificationRepositoryImpl(remoteDataSource: sl()),
  );
  sl.registerLazySingleton(() => GetProposalsUseCase(repository: sl()));
  sl.registerLazySingleton(() => GetProposalDetailUseCase(repository: sl()));
  sl.registerLazySingleton(() => SubmitVerificationUseCase(repository: sl()));
  sl.registerFactory(
    () => VerificationBloc(
      getProposalsUseCase: sl(),
      getProposalDetailUseCase: sl(),
      submitVerificationUseCase: sl(),
    ),
  );

  // Features - Executive & Approval
  sl.registerLazySingleton<ApprovalRemoteDataSource>(
    () => ApprovalRemoteDataSourceImpl(dioClient: sl()),
  );
  sl.registerLazySingleton<ApprovalRepository>(
    () => ApprovalRepositoryImpl(remoteDataSource: sl()),
  );
  sl.registerLazySingleton(() => GetExecutiveSummaryUseCase(repository: sl()));
  sl.registerLazySingleton(() => GetApprovalQueueUseCase(repository: sl()));
  sl.registerLazySingleton(() => GetApprovalDetailUseCase(repository: sl()));
  sl.registerLazySingleton(() => ApproveLoanUseCase(repository: sl()));
  sl.registerLazySingleton(() => RejectLoanUseCase(repository: sl()));
  sl.registerFactory(
    () => ApprovalBloc(
      getExecutiveSummaryUseCase: sl(),
      getApprovalQueueUseCase: sl(),
      getApprovalDetailUseCase: sl(),
      approveLoanUseCase: sl(),
      rejectLoanUseCase: sl(),
    ),
  );
}
