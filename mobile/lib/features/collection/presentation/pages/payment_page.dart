import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/currency_formatter.dart';
import '../../../../core/utils/thermal_printer_service.dart';
import '../../../../core/utils/whatsapp_helper.dart';
import '../../../../core/widgets/app_button.dart';
import '../../../../core/widgets/app_card.dart';
import '../../../../core/widgets/app_text_field.dart';
import '../../domain/entities/collection_entities.dart';
import '../bloc/collection_bloc.dart';

class PaymentPage extends StatefulWidget {
  final int loanId;

  const PaymentPage({super.key, required this.loanId});

  @override
  State<PaymentPage> createState() => _PaymentPageState();
}

class _PaymentPageState extends State<PaymentPage> {
  final _principalController = TextEditingController();
  final _interestController = TextEditingController();
  final _penaltyController = TextEditingController(text: '0');
  final _notesController = TextEditingController();

  int? _selectedMemberId;
  int? _selectedCashAccountId;
  LoanCollectionDetail? _loanDetail;
  final ThermalPrinterService _printerService = ThermalPrinterService();

  @override
  void initState() {
    super.initState();
    context.read<CollectionBloc>().add(LoadLoanDetailEvent(loanId: widget.loanId));
  }

  @override
  void dispose() {
    _principalController.dispose();
    _interestController.dispose();
    _penaltyController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  void _fillPreset(double principal, double interest) {
    setState(() {
      _principalController.text = principal.toStringAsFixed(0);
      _interestController.text = interest.toStringAsFixed(0);
    });
  }

  void _showSuccessDialog(ReceiptData receipt) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: const [
            Icon(Icons.check_circle_rounded, color: AppColors.success, size: 26),
            SizedBox(width: 8),
            Text('Pembayaran Berhasil', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('No. Bukti: ${receipt.receiptNumber}'),
            const SizedBox(height: 4),
            Text('Penyetor: ${receipt.payerName}'),
            const SizedBox(height: 4),
            Text('Total Bayar: ${CurrencyFormatter.format(receipt.totalPaid)}', style: const TextStyle(fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text('Sisa Pokok: ${CurrencyFormatter.format(receipt.remainingPrincipal)}'),
            const SizedBox(height: 16),
            const Divider(),
            const SizedBox(height: 8),
            // Action Buttons in Dialog
            AppButton(
              text: 'Cetak Struk Bluetooth',
              icon: Icons.print_rounded,
              onPressed: () async {
                final success = await _printerService.printReceipt(receipt);
                if (!success) {
                  ScaffoldMessenger.of(ctx).showSnackBar(
                    const SnackBar(content: Text('Printer Bluetooth belum terhubung.')),
                  );
                }
              },
            ),
            const SizedBox(height: 8),
            AppButton(
              text: 'Kirim WhatsApp',
              variant: AppButtonVariant.outline,
              icon: Icons.chat_rounded,
              onPressed: () {
                WhatsAppHelper.copyMessageToClipboard(receipt.loanNumber);
                ScaffoldMessenger.of(ctx).showSnackBar(
                  const SnackBar(content: Text('Teks struk berhasil disalin.')),
                );
              },
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(ctx).pop();
              Navigator.of(context).pop(); // Back to list
            },
            child: const Text('Selesai'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: const Text('Form Pembayaran Angsuran'),
      ),
      body: BlocConsumer<CollectionBloc, CollectionState>(
        listener: (context, state) {
          if (state is PaymentSuccessState) {
            _showSuccessDialog(state.receipt);
          } else if (state is CollectionError) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.message), backgroundColor: AppColors.error),
            );
          }
        },
        builder: (context, state) {
          if (state is CollectionLoading && _loanDetail == null) {
            return const Center(child: CircularProgressIndicator());
          }

          if (state is LoanDetailLoaded) {
            _loanDetail = state.detail;
            if (_selectedMemberId == null && _loanDetail!.beneficiaries.isNotEmpty) {
              _selectedMemberId = _loanDetail!.beneficiaries.first.id;
              _principalController.text = _loanDetail!.suggestedPrincipal.toStringAsFixed(0);
              _interestController.text = _loanDetail!.suggestedInterest.toStringAsFixed(0);
            }
            if (_selectedCashAccountId == null && _loanDetail!.cashAccounts.isNotEmpty) {
              _selectedCashAccountId = _loanDetail!.cashAccounts.first.id;
            }
          }

          if (_loanDetail == null) {
            return const Center(child: Text('Memuat detail pinjaman...'));
          }

          final detail = _loanDetail!;
          final isSubmitting = state is CollectionLoading;

          return SingleChildScrollView(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Minimalist Bill Summary
                AppCard(
                  title: detail.borrowerName,
                  trailing: Text(detail.villageName, style: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant)),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Tagihan Bulan Ini', style: TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant)),
                          Text(
                            CurrencyFormatter.format(detail.suggestedPrincipal + detail.suggestedInterest),
                            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.primary),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Sisa Pokok', style: TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant)),
                          Text(CurrencyFormatter.format(detail.remainingPrincipal), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                // Payer Beneficiary Selection
                if (detail.beneficiaries.length > 1) ...[
                  const Text('Pilih Anggota Penyetor', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    decoration: BoxDecoration(
                      color: AppColors.surfaceContainerLowest,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.outline),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<int>(
                        value: _selectedMemberId,
                        isExpanded: true,
                        items: detail.beneficiaries.map((b) {
                          return DropdownMenuItem<int>(
                            value: b.id,
                            child: Text('${b.name} (${CurrencyFormatter.format(b.allocatedAmount)})', style: const TextStyle(fontSize: 13)),
                          );
                        }).toList(),
                        onChanged: (val) {
                          setState(() {
                            _selectedMemberId = val;
                          });
                        },
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],

                // Preset Quick Buttons
                const Text('Pilihan Cepat Setoran', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        onPressed: () => _fillPreset(detail.suggestedPrincipal, detail.suggestedInterest),
                        child: const Text('Pas 1 Bulan', style: TextStyle(fontSize: 12)),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        onPressed: () => _fillPreset(detail.suggestedPrincipal, 0),
                        child: const Text('Pokok Saja', style: TextStyle(fontSize: 12)),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        onPressed: () => _fillPreset(0, detail.suggestedInterest),
                        child: const Text('Jasa Saja', style: TextStyle(fontSize: 12)),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),

                // Form Fields
                AppTextField(
                  label: 'Nominal Pokok (Rp)',
                  controller: _principalController,
                  keyboardType: TextInputType.number,
                  prefixIcon: const Icon(Icons.money_rounded, size: 20),
                ),
                const SizedBox(height: 12),
                AppTextField(
                  label: 'Nominal Jasa (Rp)',
                  controller: _interestController,
                  keyboardType: TextInputType.number,
                  prefixIcon: const Icon(Icons.percent_rounded, size: 20),
                ),
                const SizedBox(height: 12),
                AppTextField(
                  label: 'Catatan (Opsional)',
                  hint: 'Lokasi setor, pertemuan, dll',
                  controller: _notesController,
                ),
                const SizedBox(height: 24),

                // Submit Button
                AppButton(
                  text: 'Simpan Pembayaran & Cetak',
                  icon: Icons.check_circle_outline_rounded,
                  isLoading: isSubmitting,
                  onPressed: () {
                    final p = double.tryParse(_principalController.text) ?? 0.0;
                    final i = double.tryParse(_interestController.text) ?? 0.0;
                    final pen = double.tryParse(_penaltyController.text) ?? 0.0;

                    if (p + i + pen <= 0) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Total bayar harus lebih dari Rp 0')),
                      );
                      return;
                    }

                    context.read<CollectionBloc>().add(
                          SubmitPaymentEvent(
                            loanId: detail.id,
                            memberId: _selectedMemberId ?? detail.beneficiaries.first.id,
                            principalAmount: p,
                            interestAmount: i,
                            penaltyAmount: pen,
                            cashAccountId: _selectedCashAccountId,
                            description: _notesController.text.isNotEmpty ? _notesController.text : null,
                          ),
                        );
                  },
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
