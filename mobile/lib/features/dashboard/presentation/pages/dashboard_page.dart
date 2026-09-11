import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/widgets/app_badge.dart';
import '../../../../core/widgets/app_card.dart';
import '../../../../injection_container.dart' as di;
import '../../../approval/presentation/bloc/approval_bloc.dart';
import '../../../approval/presentation/pages/executive_dashboard_page.dart';
import '../../../assistant/presentation/pages/assistant_chat_page.dart';
import '../../../auth/domain/entities/auth_entities.dart';
import '../../../auth/presentation/bloc/auth_bloc.dart';
import '../../../auth/presentation/pages/login_page.dart';
import '../../../collection/presentation/bloc/collection_bloc.dart';
import '../../../collection/presentation/pages/collection_list_page.dart';
import '../../../verification/presentation/bloc/verification_bloc.dart';
import '../../../verification/presentation/pages/verification_list_page.dart';

class DashboardPage extends StatelessWidget {
  final AuthSession session;

  const DashboardPage({super.key, required this.session});

  void _onLogoutPressed(BuildContext context) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Konfirmasi Keluar'),
        content: const Text('Apakah Anda yakin ingin keluar dari akun ini?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Batal'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error),
            onPressed: () {
              Navigator.of(ctx).pop();
              context.read<AuthBloc>().add(AuthLogoutRequested());
              Navigator.of(context).pushReplacement(
                MaterialPageRoute(builder: (_) => const LoginPage()),
              );
            },
            child: const Text('Keluar', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = session.user;
    final tenant = session.tenant;

    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              tenant?.name ?? 'SIUPK Next',
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
            ),
            if (tenant?.districtName != null)
              Text(
                'Kec. ${tenant!.districtName} - ${tenant.regencyName ?? ''}',
                style: const TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant),
              ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout_rounded, color: AppColors.error),
            tooltip: 'Keluar',
            onPressed: () => _onLogoutPressed(context),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () async {
          context.read<AuthBloc>().add(AuthCheckRequested());
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // User Greeting Card
              AppCard(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 26,
                      backgroundColor: AppColors.primaryContainer,
                      child: Text(
                        user.name.isNotEmpty ? user.name[0].toUpperCase() : 'U',
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user.name,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: AppColors.onSurface,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            user.isSuperadmin ? 'Superadmin Platform' : (user.username.isNotEmpty ? user.username : (user.email ?? '-')),
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                    ),
                    AppBadge(
                      label: user.isSuperadmin ? 'SUPERADMIN' : 'AKTIF',
                      tone: AppBadgeTone.success,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // Quick Action Menus (Minimalis & Ringkas)
              const Text(
                'Aksi Cepat Lapangan & Eksekutif',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  color: AppColors.onSurface,
                ),
              ),
              const SizedBox(height: 12),
              GridView.count(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 1.22,
                children: [
                  _buildMenuItem(
                    context,
                    title: 'Penagihan Lapangan',
                    subtitle: 'Setoran & cetak struk Bluetooth',
                    icon: Icons.receipt_long_rounded,
                    color: AppColors.primary,
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => BlocProvider(
                            create: (_) => di.sl<CollectionBloc>(),
                            child: const CollectionListPage(),
                          ),
                        ),
                      );
                    },
                  ),
                  _buildMenuItem(
                    context,
                    title: 'Survei & Verifikasi',
                    subtitle: 'Checksheet 5C & GPS',
                    icon: Icons.fact_check_rounded,
                    color: AppColors.secondary,
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => BlocProvider(
                            create: (_) => di.sl<VerificationBloc>(),
                            child: const VerificationListPage(),
                          ),
                        ),
                      );
                    },
                  ),
                  _buildMenuItem(
                    context,
                    title: 'Approval Pimpinan',
                    subtitle: 'Ringkasan kas & 1-tap approve',
                    icon: Icons.check_circle_outline_rounded,
                    color: AppColors.warning,
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => BlocProvider(
                            create: (_) => di.sl<ApprovalBloc>(),
                            child: const ExecutiveDashboardPage(),
                          ),
                        ),
                      );
                    },
                  ),
                  _buildMenuItem(
                    context,
                    title: 'Asisten AI Ariel',
                    subtitle: 'Tanya data kas & tunggakan',
                    icon: Icons.auto_awesome_rounded,
                    color: AppColors.info,
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const AssistantChatPage(),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMenuItem(
    BuildContext context, {
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.surfaceContainerLowest,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: AppColors.outlineVariant, width: 1),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, size: 22, color: color),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.onSurface,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 10,
                    color: AppColors.onSurfaceVariant,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}



