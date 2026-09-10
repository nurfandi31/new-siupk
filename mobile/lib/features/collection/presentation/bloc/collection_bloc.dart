import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/utils/thermal_printer_service.dart';
import '../../domain/entities/collection_entities.dart';
import '../../domain/usecases/collection_usecases.dart';

// Events
abstract class CollectionEvent extends Equatable {
  const CollectionEvent();
  @override
  List<Object?> get props => [];
}

class SearchLoansEvent extends CollectionEvent {
  final String? query;
  final int? villageId;
  const SearchLoansEvent({this.query, this.villageId});
  @override
  List<Object?> get props => [query, villageId];
}

class LoadLoanDetailEvent extends CollectionEvent {
  final int loanId;
  const LoadLoanDetailEvent({required this.loanId});
  @override
  List<Object?> get props => [loanId];
}

class SubmitPaymentEvent extends CollectionEvent {
  final int loanId;
  final int memberId;
  final double principalAmount;
  final double interestAmount;
  final double penaltyAmount;
  final int? cashAccountId;
  final String? description;

  const SubmitPaymentEvent({
    required this.loanId,
    required this.memberId,
    required this.principalAmount,
    required this.interestAmount,
    this.penaltyAmount = 0,
    this.cashAccountId,
    this.description,
  });

  @override
  List<Object?> get props => [
        loanId,
        memberId,
        principalAmount,
        interestAmount,
        penaltyAmount,
        cashAccountId,
        description,
      ];
}

// States
abstract class CollectionState extends Equatable {
  const CollectionState();
  @override
  List<Object?> get props => [];
}

class CollectionInitial extends CollectionState {}

class CollectionLoading extends CollectionState {}

class CollectionLoansLoaded extends CollectionState {
  final List<CollectionLoanItem> loans;
  const CollectionLoansLoaded({required this.loans});
  @override
  List<Object?> get props => [loans];
}

class LoanDetailLoaded extends CollectionState {
  final LoanCollectionDetail detail;
  const LoanDetailLoaded({required this.detail});
  @override
  List<Object?> get props => [detail];
}

class PaymentSuccessState extends CollectionState {
  final ReceiptData receipt;
  const PaymentSuccessState({required this.receipt});
  @override
  List<Object?> get props => [receipt];
}

class CollectionError extends CollectionState {
  final String message;
  const CollectionError({required this.message});
  @override
  List<Object?> get props => [message];
}

// BLoC
class CollectionBloc extends Bloc<CollectionEvent, CollectionState> {
  final SearchCollectionLoansUseCase searchLoansUseCase;
  final GetLoanDetailUseCase getLoanDetailUseCase;
  final PayInstallmentUseCase payInstallmentUseCase;

  CollectionBloc({
    required this.searchLoansUseCase,
    required this.getLoanDetailUseCase,
    required this.payInstallmentUseCase,
  }) : super(CollectionInitial()) {
    on<SearchLoansEvent>(_onSearchLoans);
    on<LoadLoanDetailEvent>(_onLoadLoanDetail);
    on<SubmitPaymentEvent>(_onSubmitPayment);
  }

  Future<void> _onSearchLoans(
    SearchLoansEvent event,
    Emitter<CollectionState> emit,
  ) async {
    emit(CollectionLoading());
    try {
      final loans = await searchLoansUseCase.execute(
        search: event.query,
        villageId: event.villageId,
      );
      emit(CollectionLoansLoaded(loans: loans));
    } catch (e) {
      emit(CollectionError(message: e.toString()));
    }
  }

  Future<void> _onLoadLoanDetail(
    LoadLoanDetailEvent event,
    Emitter<CollectionState> emit,
  ) async {
    emit(CollectionLoading());
    try {
      final detail = await getLoanDetailUseCase.execute(event.loanId);
      emit(LoanDetailLoaded(detail: detail));
    } catch (e) {
      emit(CollectionError(message: e.toString()));
    }
  }

  Future<void> _onSubmitPayment(
    SubmitPaymentEvent event,
    Emitter<CollectionState> emit,
  ) async {
    emit(CollectionLoading());
    try {
      final receipt = await payInstallmentUseCase.execute(
        loanId: event.loanId,
        memberId: event.memberId,
        principalAmount: event.principalAmount,
        interestAmount: event.interestAmount,
        penaltyAmount: event.penaltyAmount,
        cashAccountId: event.cashAccountId,
        description: event.description,
      );
      emit(PaymentSuccessState(receipt: receipt));
    } catch (e) {
      emit(CollectionError(message: e.toString()));
    }
  }
}
