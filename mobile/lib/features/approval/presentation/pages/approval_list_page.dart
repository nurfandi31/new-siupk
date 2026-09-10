import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/currency_formatter.dart';
import '../../../../core/widgets/app_badge.dart';
import '../../../../core/widgets/app_card.dart';
import '../../../../injection_container.dart' as di;
import '../bloc/approval_bloc.dart';
import 'approval_detail_page.dart';

class ApprovalListPage extends StatefulWidget {
  const ApprovalListPage({super.key});

  @override
  State<ApprovalListPage> createState() => _ApprovalListPageState();
}

class _ApprovalListPageState extends State<ApprovalListPage> {
  final TextEditingController _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _fetchQueue();
  }

  void _fetchQueue() {
    context.read<ApprovalBloc>().add(
          LoadApprovalQueueEvent(search: _searchController.text.trim()),
        );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: const Text('Persetujuan Pinjaman (Approval)'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: TextField(
              controller: _searchController,
              onSubmitted: (_) => _fetchQueue(),
              decoration: InputDecoration(
                hintText: 'Cari usulan / kelompok...',
                hintStyle: const TextStyle(fontSize: 13, color: AppColors.onSurfaceVariant),
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          _fetchQueue();
                        },
                      )
                    : null,
                filled: true,
                fillColor: AppColors.surfaceContainerLowest,
                contentPadding: const EdgeInsets.symmetric(vertical: 0),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.outlineVariant),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.outlineVariant),
                ),
              ),
            ),
          ),
        ),
      ),
      body: BlocConsumer<ApprovalBloc, ApprovalState>(
        listener: (context, state) {
          if (state is ApprovalActionSuccess) {
            _fetchQueue();
          }
        },
        builder: (context, state) {
          if (state is ApprovalQueueLoading) {
            return const Center(child: CircularProgressIndicator());
          } else if (state is ApprovalError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                    const SizedBox(height: 12),
                    Text(state.message, textAlign: TextAlign.center),
                    const SizedBox(height: 16),
                    ElevatedButton(onPressed: _fetchQueue, child: const Text('Coba Lagi')),
                  ],
                ),
              ),
            );
          } else if (state is ApprovalQueueLoaded) {
            if (state.items.isEmpty) {
              return Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.verified_outlined, size: 48, color: AppColors.primary),
                      const SizedBox(height: 12),
                      const Text(
                        'Tidak ada antrean persetujuan',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Semua usulan yang telah diverifikasi sudah diproses.',
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
                      ),
                    ],
                  ),
                ),
              );
            }

            return RefreshIndicator(
              onRefresh: () async => _fetchQueue(),
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: state.items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final item = state.items[index];
                  return _buildApprovalCard(context, item);
                },
              ),
            );
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }

  Widget _buildApprovalCard(BuildContext context, dynamic item) {
    return AppCard(
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => BlocProvider(
              create: (_) => di.sl<ApprovalBloc>(),
              child: ApprovalDetailPage(loanId: item.rowId),
            ),
          ),
        ).then((_) => _fetchQueue());
      },
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                item.loanNumber,
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
              ),
              const AppBadge(label: 'SIAP APPROVAL', tone: AppBadgeTone.info),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            item.borrowerName,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.onSurface),
          ),
          const SizedBox(height: 2),
          Text(
            '${item.borrowerType} • ${item.villageName}',
            style: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
          ),
          if (item.verificationNotes != null && item.verificationNotes.isNotEmpty) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                'Hasil Survei: ${item.verificationNotes}',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11, fontStyle: FontStyle.italic, color: AppColors.onSurfaceVariant),
              ),
            ),
          ],
          const Divider(height: 20, color: AppColors.outlineVariant),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Rekomendasi Plafon', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                  const SizedBox(height: 2),
                  Text(
                    CurrencyFormatter.formatRupiah(item.verifiedAmount),
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.primary),
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text('Pemanfaat', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                  const SizedBox(height: 2),
                  Text(
                    '${item.beneficiaryCount} Anggota (${item.termMonths} Bln)',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.onSurface),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => BlocProvider(
                      create: (_) => di.sl<ApprovalBloc>(),
                      child: ApprovalDetailPage(loanId: item.rowId),
                    ),
                  ),
                ).then((_) => _fetchQueue());
              },
              icon: const Icon(Icons.check_circle_outline_rounded, size: 16),
              label: const Text('Review & Eksekusi', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

