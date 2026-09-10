import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/currency_formatter.dart';
import '../../../../core/widgets/app_card.dart';
import '../../../../injection_container.dart' as di;
import '../bloc/approval_bloc.dart';
import 'approval_list_page.dart';

class ExecutiveDashboardPage extends StatefulWidget {
  const ExecutiveDashboardPage({super.key});

  @override
  State<ExecutiveDashboardPage> createState() => _ExecutiveDashboardPageState();
}

class _ExecutiveDashboardPageState extends State<ExecutiveDashboardPage> {
  @override
  void initState() {
    super.initState();
    context.read<ApprovalBloc>().add(LoadExecutiveSummaryEvent());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: const Text('Ringkasan Eksekutif'),
      ),
      body: BlocBuilder<ApprovalBloc, ApprovalState>(
        builder: (context, state) {
          if (state is ExecutiveSummaryLoading) {
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
                    ElevatedButton(
                      onPressed: () => context.read<ApprovalBloc>().add(LoadExecutiveSummaryEvent()),
                      child: const Text('Coba Lagi'),
                    ),
                  ],
                ),
              ),
            );
          } else if (state is ExecutiveSummaryLoaded) {
            final s = state.summary;

            return RefreshIndicator(
              onRefresh: () async {
                context.read<ApprovalBloc>().add(LoadExecutiveSummaryEvent());
              },
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Pending Approval Call-to-action Banner
                    if (s.pendingApprovalCount > 0) ...[
                      InkWell(
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => BlocProvider(
                                create: (_) => di.sl<ApprovalBloc>(),
                                child: const ApprovalListPage(),
                              ),
                            ),
                          ).then((_) => context.read<ApprovalBloc>().add(LoadExecutiveSummaryEvent()));
                        },
                        borderRadius: BorderRadius.circular(12),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [AppColors.primary, Color(0xFF0F766E)],
                            ),
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(8),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.2),
                                  shape: BoxShape.circle,
                                ),
                                child: const Icon(Icons.pending_actions_rounded, color: Colors.white, size: 24),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      '${s.pendingApprovalCount} Usulan Siap Disetujui',
                                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: Colors.white),
                                    ),
                                    const Text(
                                      'Ketuk untuk review dan 1-tap quick approval',
                                      style: TextStyle(fontSize: 11, color: Colors.white70),
                                    ),
                                  ],
                                ),
                              ),
                              const Icon(Icons.arrow_forward_ios_rounded, color: Colors.white, size: 14),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // Liquidity Card (Kas & Bank)
                    AppCard(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text('Total Likuiditas (Kas & Bank)', style: TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant)),
                              Icon(Icons.account_balance_wallet_outlined, size: 18, color: AppColors.primary),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            CurrencyFormatter.formatRupiah(s.totalLiquidity),
                            style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: AppColors.onSurface),
                          ),
                          const Divider(height: 20, color: AppColors.outlineVariant),
                          Row(
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Kas Tunai', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                                    Text(CurrencyFormatter.formatRupiah(s.cashBalance), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                  ],
                                ),
                              ),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Text('Rekening Bank', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                                    Text(CurrencyFormatter.formatRupiah(s.bankBalance), style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                                  ],
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Active Loans & Repayment Grid
                    Row(
                      children: [
                        Expanded(
                          child: AppCard(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Outstanding Pokok', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                                const SizedBox(height: 4),
                                Text(
                                  CurrencyFormatter.formatRupiah(s.outstandingPrincipal),
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.onSurface),
                                ),
                                const SizedBox(height: 2),
                                Text('${s.activeLoansCount} Kelompok Aktif', style: const TextStyle(fontSize: 10, color: AppColors.onSurfaceVariant)),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: AppCard(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Setoran Hari Ini', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                                const SizedBox(height: 4),
                                Text(
                                  CurrencyFormatter.formatRupiah(s.todayCollectionsAmount),
                                  style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.success),
                                ),
                                const SizedBox(height: 2),
                                Text('${s.todayCollectionsCount} Transaksi Masuk', style: const TextStyle(fontSize: 10, color: AppColors.onSurfaceVariant)),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),

                    // Quick Actions
                    const Text('Navigasi Eksekutif', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                    const SizedBox(height: 10),
                    ListTile(
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      tileColor: AppColors.surfaceContainerLowest,
                      leading: const CircleAvatar(
                        backgroundColor: AppColors.primaryContainer,
                        child: Icon(Icons.checklist_rtl_rounded, color: AppColors.primary, size: 20),
                      ),
                      title: const Text('Daftar Antrean Approval', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                      subtitle: Text('${s.pendingApprovalCount} usulan menunggu persetujuan', style: const TextStyle(fontSize: 11)),
                      trailing: const Icon(Icons.chevron_right_rounded),
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => BlocProvider(
                              create: (_) => di.sl<ApprovalBloc>(),
                              child: const ApprovalListPage(),
                            ),
                          ),
                        ).then((_) => context.read<ApprovalBloc>().add(LoadExecutiveSummaryEvent()));
                      },
                    ),
                  ],
                ),
              ),
            );
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }
}
