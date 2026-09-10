import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/error/failures.dart';
import '../../domain/entities/approval_entities.dart';
import '../../domain/usecases/approval_usecases.dart';

// EVENTS
abstract class ApprovalEvent extends Equatable {
  const ApprovalEvent();

  @override
  List<Object?> get props => [];
}

class LoadExecutiveSummaryEvent extends ApprovalEvent {}

class LoadApprovalQueueEvent extends ApprovalEvent {
  final String? search;
  final int? villageId;

  const LoadApprovalQueueEvent({this.search, this.villageId});

  @override
  List<Object?> get props => [search, villageId];
}

class LoadApprovalDetailEvent extends ApprovalEvent {
  final int loanId;

  const LoadApprovalDetailEvent(this.loanId);

  @override
  List<Object?> get props => [loanId];
}

class ExecuteApproveLoanEvent extends ApprovalEvent {
  final int loanId;
  final String approvedAt;
  final String plannedDisbursedAt;
  final double allocatedPrincipal;
  final String? allocationNotes;
  final List<Map<String, dynamic>> beneficiaries;

  const ExecuteApproveLoanEvent({
    required this.loanId,
    required this.approvedAt,
    required this.plannedDisbursedAt,
    required this.allocatedPrincipal,
    this.allocationNotes,
    required this.beneficiaries,
  });

  @override
  List<Object?> get props => [
        loanId,
        approvedAt,
        plannedDisbursedAt,
        allocatedPrincipal,
        allocationNotes,
        beneficiaries,
      ];
}

class ExecuteRejectLoanEvent extends ApprovalEvent {
  final int loanId;
  final String reason;

  const ExecuteRejectLoanEvent({required this.loanId, required this.reason});

  @override
  List<Object?> get props => [loanId, reason];
}

// STATES
abstract class ApprovalState extends Equatable {
  const ApprovalState();

  @override
  List<Object?> get props => [];
}

class ApprovalInitial extends ApprovalState {}

class ExecutiveSummaryLoading extends ApprovalState {}

class ExecutiveSummaryLoaded extends ApprovalState {
  final ExecutiveSummary summary;

  const ExecutiveSummaryLoaded(this.summary);

  @override
  List<Object?> get props => [summary];
}

class ApprovalQueueLoading extends ApprovalState {}

class ApprovalQueueLoaded extends ApprovalState {
  final List<ApprovalItem> items;

  const ApprovalQueueLoaded(this.items);

  @override
  List<Object?> get props => [items];
}

class ApprovalDetailLoading extends ApprovalState {}

class ApprovalDetailLoaded extends ApprovalState {
  final ApprovalDetail detail;

  const ApprovalDetailLoaded(this.detail);

  @override
  List<Object?> get props => [detail];
}

class ApprovalActionLoading extends ApprovalState {}

class ApprovalActionSuccess extends ApprovalState {
  final String message;

  const ApprovalActionSuccess(this.message);

  @override
  List<Object?> get props => [message];
}

class ApprovalError extends ApprovalState {
  final String message;

  const ApprovalError(this.message);

  @override
  List<Object?> get props => [message];
}

// BLOC
class ApprovalBloc extends Bloc<ApprovalEvent, ApprovalState> {
  final GetExecutiveSummaryUseCase getExecutiveSummaryUseCase;
  final GetApprovalQueueUseCase getApprovalQueueUseCase;
  final GetApprovalDetailUseCase getApprovalDetailUseCase;
  final ApproveLoanUseCase approveLoanUseCase;
  final RejectLoanUseCase rejectLoanUseCase;

  ApprovalBloc({
    required this.getExecutiveSummaryUseCase,
    required this.getApprovalQueueUseCase,
    required this.getApprovalDetailUseCase,
    required this.approveLoanUseCase,
    required this.rejectLoanUseCase,
  }) : super(ApprovalInitial()) {
    on<LoadExecutiveSummaryEvent>(_onLoadExecutiveSummary);
    on<LoadApprovalQueueEvent>(_onLoadApprovalQueue);
    on<LoadApprovalDetailEvent>(_onLoadApprovalDetail);
    on<ExecuteApproveLoanEvent>(_onApproveLoan);
    on<ExecuteRejectLoanEvent>(_onRejectLoan);
  }

  Future<void> _onLoadExecutiveSummary(
    LoadExecutiveSummaryEvent event,
    Emitter<ApprovalState> emit,
  ) async {
    emit(ExecutiveSummaryLoading());
    try {
      final summary = await getExecutiveSummaryUseCase();
      emit(ExecutiveSummaryLoaded(summary));
    } on Failure catch (f) {
      emit(ApprovalError(f.message));
    } catch (e) {
      emit(ApprovalError(e.toString()));
    }
  }

  Future<void> _onLoadApprovalQueue(
    LoadApprovalQueueEvent event,
    Emitter<ApprovalState> emit,
  ) async {
    emit(ApprovalQueueLoading());
    try {
      final items = await getApprovalQueueUseCase(
        search: event.search,
        villageId: event.villageId,
      );
      emit(ApprovalQueueLoaded(items));
    } on Failure catch (f) {
      emit(ApprovalError(f.message));
    } catch (e) {
      emit(ApprovalError(e.toString()));
    }
  }

  Future<void> _onLoadApprovalDetail(
    LoadApprovalDetailEvent event,
    Emitter<ApprovalState> emit,
  ) async {
    emit(ApprovalDetailLoading());
    try {
      final detail = await getApprovalDetailUseCase(event.loanId);
      emit(ApprovalDetailLoaded(detail));
    } on Failure catch (f) {
      emit(ApprovalError(f.message));
    } catch (e) {
      emit(ApprovalError(e.toString()));
    }
  }

  Future<void> _onApproveLoan(
    ExecuteApproveLoanEvent event,
    Emitter<ApprovalState> emit,
  ) async {
    emit(ApprovalActionLoading());
    try {
      await approveLoanUseCase(
        loanId: event.loanId,
        approvedAt: event.approvedAt,
        plannedDisbursedAt: event.plannedDisbursedAt,
        allocatedPrincipal: event.allocatedPrincipal,
        allocationNotes: event.allocationNotes,
        beneficiaries: event.beneficiaries,
      );
      emit(const ApprovalActionSuccess('Alokasi pinjaman berhasil disetujui'));
    } on Failure catch (f) {
      emit(ApprovalError(f.message));
    } catch (e) {
      emit(ApprovalError(e.toString()));
    }
  }

  Future<void> _onRejectLoan(
    ExecuteRejectLoanEvent event,
    Emitter<ApprovalState> emit,
  ) async {
    emit(ApprovalActionLoading());
    try {
      await rejectLoanUseCase(
        loanId: event.loanId,
        reason: event.reason,
      );
      emit(const ApprovalActionSuccess('Proposal pinjaman telah dikembalikan / ditolak'));
    } on Failure catch (f) {
      emit(ApprovalError(f.message));
    } catch (e) {
      emit(ApprovalError(e.toString()));
    }
  }
}
