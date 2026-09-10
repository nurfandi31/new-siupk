import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/verification_entities.dart';
import '../../domain/usecases/verification_usecases.dart';

// EVENTS
abstract class VerificationEvent extends Equatable {
  const VerificationEvent();

  @override
  List<Object?> get props => [];
}

class LoadProposalsEvent extends VerificationEvent {
  final String? search;
  final String? status;
  final int? villageId;

  const LoadProposalsEvent({this.search, this.status, this.villageId});

  @override
  List<Object?> get props => [search, status, villageId];
}

class LoadProposalDetailEvent extends VerificationEvent {
  final int loanId;

  const LoadProposalDetailEvent(this.loanId);

  @override
  List<Object?> get props => [loanId];
}

class SubmitVerificationEvent extends VerificationEvent {
  final int loanId;
  final String verifiedAt;
  final double verificationAmount;
  final String? verificationNotes;
  final Map<int, double>? verifiedAmounts;
  final double? latitude;
  final double? longitude;
  final Map<String, int>? scoring5C;
  final String? signatureBase64;

  const SubmitVerificationEvent({
    required this.loanId,
    required this.verifiedAt,
    required this.verificationAmount,
    this.verificationNotes,
    this.verifiedAmounts,
    this.latitude,
    this.longitude,
    this.scoring5C,
    this.signatureBase64,
  });

  @override
  List<Object?> get props => [
        loanId,
        verifiedAt,
        verificationAmount,
        verificationNotes,
        verifiedAmounts,
        latitude,
        longitude,
        scoring5C,
        signatureBase64,
      ];
}

// STATES
abstract class VerificationState extends Equatable {
  const VerificationState();

  @override
  List<Object?> get props => [];
}

class VerificationInitial extends VerificationState {}

class ProposalsLoading extends VerificationState {}

class ProposalsLoaded extends VerificationState {
  final List<ProposalItem> proposals;

  const ProposalsLoaded(this.proposals);

  @override
  List<Object?> get props => [proposals];
}

class ProposalDetailLoading extends VerificationState {}

class ProposalDetailLoaded extends VerificationState {
  final ProposalDetail proposal;

  const ProposalDetailLoaded(this.proposal);

  @override
  List<Object?> get props => [proposal];
}

class VerificationSubmitting extends VerificationState {}

class VerificationSubmitSuccess extends VerificationState {
  final String message;

  const VerificationSubmitSuccess(this.message);

  @override
  List<Object?> get props => [message];
}

class VerificationError extends VerificationState {
  final String message;

  const VerificationError(this.message);

  @override
  List<Object?> get props => [message];
}

// BLOC
class VerificationBloc extends Bloc<VerificationEvent, VerificationState> {
  final GetProposalsUseCase getProposalsUseCase;
  final GetProposalDetailUseCase getProposalDetailUseCase;
  final SubmitVerificationUseCase submitVerificationUseCase;

  VerificationBloc({
    required this.getProposalsUseCase,
    required this.getProposalDetailUseCase,
    required this.submitVerificationUseCase,
  }) : super(VerificationInitial()) {
    on<LoadProposalsEvent>(_onLoadProposals);
    on<LoadProposalDetailEvent>(_onLoadProposalDetail);
    on<SubmitVerificationEvent>(_onSubmitVerification);
  }

  Future<void> _onLoadProposals(
    LoadProposalsEvent event,
    Emitter<VerificationState> emit,
  ) async {
    emit(ProposalsLoading());
    try {
      final proposals = await getProposalsUseCase(
        search: event.search,
        status: event.status,
        villageId: event.villageId,
      );
      emit(ProposalsLoaded(proposals));
    } on Failure catch (f) {
      emit(VerificationError(f.message));
    } catch (e) {
      emit(VerificationError(e.toString()));
    }
  }

  Future<void> _onLoadProposalDetail(
    LoadProposalDetailEvent event,
    Emitter<VerificationState> emit,
  ) async {
    emit(ProposalDetailLoading());
    try {
      final detail = await getProposalDetailUseCase(event.loanId);
      emit(ProposalDetailLoaded(detail));
    } on Failure catch (f) {
      emit(VerificationError(f.message));
    } catch (e) {
      emit(VerificationError(e.toString()));
    }
  }

  Future<void> _onSubmitVerification(
    SubmitVerificationEvent event,
    Emitter<VerificationState> emit,
  ) async {
    emit(VerificationSubmitting());
    try {
      await submitVerificationUseCase(
        loanId: event.loanId,
        verifiedAt: event.verifiedAt,
        verificationAmount: event.verificationAmount,
        verificationNotes: event.verificationNotes,
        verifiedAmounts: event.verifiedAmounts,
        latitude: event.latitude,
        longitude: event.longitude,
        scoring5C: event.scoring5C,
        signatureBase64: event.signatureBase64,
      );
      emit(const VerificationSubmitSuccess('Hasil verifikasi & survei berhasil disimpan'));
    } on Failure catch (f) {
      emit(VerificationError(f.message));
    } catch (e) {
      emit(VerificationError(e.toString()));
    }
  }
}
