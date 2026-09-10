import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/currency_formatter.dart';
import '../../../../core/widgets/app_badge.dart';
import '../../../../core/widgets/app_card.dart';
import '../../../../injection_container.dart' as di;
import '../bloc/verification_bloc.dart';
import 'survey_form_page.dart';

class VerificationListPage extends StatefulWidget {
  const VerificationListPage({super.key});

  @override
  State<VerificationListPage> createState() => _VerificationListPageState();
}

class _VerificationListPageState extends State<VerificationListPage> {
  final TextEditingController _searchController = TextEditingController();
  String _selectedStatusFilter = '';

  @override
  void initState() {
    super.initState();
    _fetchProposals();
  }

  void _fetchProposals() {
    context.read<VerificationBloc>().add(
          LoadProposalsEvent(
            search: _searchController.text.trim(),
            status: _selectedStatusFilter.isNotEmpty ? _selectedStatusFilter : null,
          ),
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
        title: const Text('Survei & Verifikasi Lapangan'),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: TextField(
              controller: _searchController,
              onSubmitted: (_) => _fetchProposals(),
              decoration: InputDecoration(
                hintText: 'Cari usulan / kelompok / pemanfaat...',
                hintStyle: const TextStyle(fontSize: 13, color: AppColors.onSurfaceVariant),
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          _fetchProposals();
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
      body: Column(
        children: [
          // Filter Chips
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            child: Row(
              children: [
                _buildFilterChip('Semua Status', ''),
                const SizedBox(width: 8),
                _buildFilterChip('Perlu Verifikasi', 'draft'),
                const SizedBox(width: 8),
                _buildFilterChip('Sudah Diverifikasi', 'verified'),
              ],
            ),
          ),
          const Divider(height: 1, color: AppColors.outlineVariant),
          // List View
          Expanded(
            child: BlocConsumer<VerificationBloc, VerificationState>(
              listener: (context, state) {
                if (state is VerificationSubmitSuccess) {
                  _fetchProposals();
                }
              },
              builder: (context, state) {
                if (state is ProposalsLoading) {
                  return const Center(child: CircularProgressIndicator());
                } else if (state is VerificationError) {
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
                            onPressed: _fetchProposals,
                            child: const Text('Coba Lagi'),
                          ),
                        ],
                      ),
                    ),
                  );
                } else if (state is ProposalsLoaded) {
                  if (state.proposals.isEmpty) {
                    return Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(Icons.fact_check_outlined, size: 48, color: AppColors.onSurfaceVariant),
                            const SizedBox(height: 12),
                            const Text(
                              'Tidak ada antrean usulan pinjaman',
                              style: TextStyle(fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Semua proposal telah disurvei atau belum ada pengajuan baru.',
                              textAlign: TextAlign.center,
                              style: TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
                            ),
                          ],
                        ),
                      ),
                    );
                  }

                  return RefreshIndicator(
                    onRefresh: () async => _fetchProposals(),
                    child: ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: state.proposals.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final proposal = state.proposals[index];
                        return _buildProposalCard(context, proposal);
                      },
                    ),
                  );
                }
                return const SizedBox.shrink();
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String label, String statusValue) {
    final isSelected = _selectedStatusFilter == statusValue;
    return ChoiceChip(
      label: Text(label, style: TextStyle(fontSize: 12, fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500)),
      selected: isSelected,
      onSelected: (_) {
        setState(() => _selectedStatusFilter = statusValue);
        _fetchProposals();
      },
      selectedColor: AppColors.primaryContainer,
      backgroundColor: AppColors.surfaceContainerLowest,
      labelStyle: TextStyle(color: isSelected ? AppColors.primary : AppColors.onSurface),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
    );
  }

  Widget _buildProposalCard(BuildContext context, dynamic proposal) {
    final bool isVerified = proposal.status == 'verified';

    return AppCard(
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => BlocProvider(
              create: (_) => di.sl<VerificationBloc>(),
              child: SurveyFormPage(loanId: proposal.rowId),
            ),
          ),
        ).then((_) => _fetchProposals());
      },
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                proposal.loanNumber,
                style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
              ),
              AppBadge(
                label: isVerified ? 'TERVERIFIKASI' : 'PERLU SURVEI',
                tone: isVerified ? AppBadgeTone.success : AppBadgeTone.warning,
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            proposal.borrowerName,
            style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.onSurface),
          ),
          const SizedBox(height: 2),
          Text(
            '${proposal.borrowerType} • ${proposal.villageName}',
            style: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
          ),
          const Divider(height: 20, color: AppColors.outlineVariant),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('Plafon Pengajuan', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                  const SizedBox(height: 2),
                  Text(
                    CurrencyFormatter.formatRupiah(proposal.proposedAmount),
                    style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: AppColors.onSurface),
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text('Pemanfaat', style: TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant)),
                  const SizedBox(height: 2),
                  Text(
                    '${proposal.beneficiaryCount} Anggota',
                    style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.onSurface),
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
                backgroundColor: isVerified ? AppColors.surfaceContainerHigh : AppColors.primary,
                foregroundColor: isVerified ? AppColors.onSurface : Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => BlocProvider(
                      create: (_) => di.sl<VerificationBloc>(),
                      child: SurveyFormPage(loanId: proposal.rowId),
                    ),
                  ),
                ).then((_) => _fetchProposals());
              },
              icon: Icon(isVerified ? Icons.edit_note : Icons.fact_check_rounded, size: 16),
              label: Text(isVerified ? 'Edit Hasil Survei' : 'Mulai Survei Lapangan', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

