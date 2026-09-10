import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/currency_formatter.dart';
import '../../../../core/widgets/app_card.dart';
import '../../domain/entities/approval_entities.dart';
import '../bloc/approval_bloc.dart';

class ApprovalDetailPage extends StatefulWidget {
  final int loanId;

  const ApprovalDetailPage({super.key, required this.loanId});

  @override
  State<ApprovalDetailPage> createState() => _ApprovalDetailPageState();
}

class _ApprovalDetailPageState extends State<ApprovalDetailPage> {
  final Map<int, TextEditingController> _allocatedControllers = {};
  final TextEditingController _notesController = TextEditingController();
  final TextEditingController _rejectReasonController = TextEditingController();

  @override
  void initState() {
    super.initState();
    context.read<ApprovalBloc>().add(LoadApprovalDetailEvent(widget.loanId));
  }

  @override
  void dispose() {
    _notesController.dispose();
    _rejectReasonController.dispose();
    for (final c in _allocatedControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _showApproveDialog(ApprovalDetail detail) {
    final today = DateFormat('yyyy-MM-dd').format(DateTime.now());
    final plannedDisburse = detail.suggestedDisbursementDate.isNotEmpty
        ? detail.suggestedDisbursementDate
        : DateFormat('yyyy-MM-dd').format(DateTime.now().add(const Duration(days: 7)));

    final List<Map<String, dynamic>> beneficiariesPayload = [];
    double totalAllocated = 0;

    for (final b in detail.beneficiaries) {
      final text = _allocatedControllers[b.memberRowId]?.text.replaceAll(RegExp(r'[^0-9]'), '') ?? '';
      final val = double.tryParse(text) ?? b.verifiedAmount;
      beneficiariesPayload.add({
        'member_row_id': b.memberRowId,
        'allocated_amount': val,
      });
      totalAllocated += val;
    }

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi Persetujuan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Kelompok: ${detail.borrowerName}'),
            const SizedBox(height: 4),
            Text('Total Plafon: ${CurrencyFormatter.formatRupiah(totalAllocated)}', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.primary)),
            const SizedBox(height: 4),
            Text('Rencana Pencairan: $plannedDisburse', style: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant)),
            const SizedBox(height: 8),
            const Text('Status pinjaman akan dialihkan ke "Waiting" siap pencairan dana.', style: TextStyle(fontSize: 11, fontStyle: FontStyle.italic)),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.primary, foregroundColor: Colors.white),
            onPressed: () {
              Navigator.of(ctx).pop();
              context.read<ApprovalBloc>().add(
                    ExecuteApproveLoanEvent(
                      loanId: detail.rowId,
                      approvedAt: today,
                      plannedDisbursedAt: plannedDisburse,
                      allocatedPrincipal: totalAllocated,
                      allocationNotes: _notesController.text.trim().isNotEmpty ? _notesController.text.trim() : null,
                      beneficiaries: beneficiariesPayload,
                    ),
                  );
            },
            child: const Text('Setujui Plafon'),
          ),
        ],
      ),
    );
  }

  void _showRejectDialog(ApprovalDetail detail) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Kembalikan / Tolak Usulan'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Tuliskan alasan penolakan atau catatan perbaikan berkas:', style: TextStyle(fontSize: 12)),
            const SizedBox(height: 8),
            TextField(
              controller: _rejectReasonController,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'Misal: Omzet usaha belum mencukupi, jaminan kurang lengkap...',
                hintStyle: const TextStyle(fontSize: 12),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('Batal')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error, foregroundColor: Colors.white),
            onPressed: () {
              final reason = _rejectReasonController.text.trim();
              if (reason.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Alasan wajib diisi')),
                );
                return;
              }
              Navigator.of(ctx).pop();
              context.read<ApprovalBloc>().add(
                    ExecuteRejectLoanEvent(
                      loanId: detail.rowId,
                      reason: reason,
                    ),
                  );
            },
            child: const Text('Tolak Usulan'),
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
        title: const Text('Keputusan Persetujuan'),
      ),
      body: BlocConsumer<ApprovalBloc, ApprovalState>(
        listener: (context, state) {
          if (state is ApprovalActionSuccess) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.message), backgroundColor: AppColors.success),
            );
            Navigator.of(context).pop();
          } else if (state is ApprovalError) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.message), backgroundColor: AppColors.error),
            );
          }
        },
        builder: (context, state) {
          if (state is ApprovalDetailLoading || state is ApprovalActionLoading) {
            return const Center(child: CircularProgressIndicator());
          } else if (state is ApprovalDetailLoaded) {
            final detail = state.detail;
            _initControllers(detail);

            return Column(
              children: [
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        // Borrower & Proposal Profile Card
                        AppCard(
                          padding: const EdgeInsets.all(14),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                detail.loanNumber,
                                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                detail.borrowerName,
                                style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.onSurface),
                              ),
                              Text(
                                '${detail.borrowerType} • ${detail.villageName}',
                                style: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
                              ),
                              const Divider(height: 16, color: AppColors.outlineVariant),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      const Text('Plafon Pengajuan', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                                      Text(CurrencyFormatter.formatRupiah(detail.proposedAmount), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
                                    ],
                                  ),
                                  Column(
                                    crossAxisAlignment: CrossAxisAlignment.end,
                                    children: [
                                      const Text('Rekomendasi Survei', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                                      Text(CurrencyFormatter.formatRupiah(detail.verifiedAmount), style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.primary)),
                                    ],
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 14),

                        // Surveyor Notes
                        if (detail.verificationNotes != null && detail.verificationNotes!.isNotEmpty) ...[
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.primaryContainer.withValues(alpha: 0.4),
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppColors.primaryContainer),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Row(
                                  children: [
                                    Icon(Icons.rate_review_outlined, size: 16, color: AppColors.primary),
                                    SizedBox(width: 6),
                                    Text('Catatan Hasil Survei Lapangan', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  detail.verificationNotes!,
                                  style: const TextStyle(fontSize: 12, color: AppColors.onSurface),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(height: 14),
                        ],

                        // Member Allocation Section
                        const Text('Alokasi Plafon Per Anggota', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 8),
                        ...detail.beneficiaries.map((b) => _buildBeneficiaryRow(b)),
                        const SizedBox(height: 14),

                        // Executive Notes Field
                        const Text('Catatan Persetujuan (Opsional)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                        const SizedBox(height: 6),
                        TextField(
                          controller: _notesController,
                          maxLines: 2,
                          decoration: InputDecoration(
                            hintText: 'Tambahkan instruksi pencairan atau catatan pimpinan...',
                            hintStyle: const TextStyle(fontSize: 12),
                            filled: true,
                            fillColor: AppColors.surfaceContainerLowest,
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                // Bottom Fixed Actions
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceContainerLowest,
                    border: const Border(top: BorderSide(color: AppColors.outlineVariant)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.05),
                        offset: const Offset(0, -2),
                        blurRadius: 6,
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        flex: 1,
                        child: OutlinedButton(
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.error,
                            side: const BorderSide(color: AppColors.error),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          onPressed: () => _showRejectDialog(detail),
                          child: const Text('Tolak', style: TextStyle(fontWeight: FontWeight.w700)),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        flex: 2,
                        child: ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          onPressed: () => _showApproveDialog(detail),
                          icon: const Icon(Icons.check_circle_rounded, size: 18),
                          label: const Text('1-Tap Setujui', style: TextStyle(fontWeight: FontWeight.w700)),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            );
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }

  void _initControllers(ApprovalDetail detail) {
    for (final b in detail.beneficiaries) {
      if (!_allocatedControllers.containsKey(b.memberRowId)) {
        _allocatedControllers[b.memberRowId] = TextEditingController(
          text: b.allocatedAmount.toInt().toString(),
        );
      }
    }
  }

  Widget _buildBeneficiaryRow(ApprovalBeneficiary b) {
    final controller = _allocatedControllers[b.memberRowId];

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.outlineVariant),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(b.fullName, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(
                  'Verifikasi: ${CurrencyFormatter.formatRupiah(b.verifiedAmount)}',
                  style: const TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant),
                ),
              ],
            ),
          ),
          SizedBox(
            width: 130,
            child: TextField(
              controller: controller,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                isDense: true,
                labelText: 'Plafon Disetujui',
                labelStyle: const TextStyle(fontSize: 10),
                prefixText: 'Rp ',
                contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
