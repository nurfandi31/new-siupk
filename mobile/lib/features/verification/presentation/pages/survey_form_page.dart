import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:intl/intl.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/currency_formatter.dart';
import '../../../../core/widgets/app_button.dart';
import '../../../../core/widgets/app_card.dart';
import '../../domain/entities/verification_entities.dart';
import '../bloc/verification_bloc.dart';
import '../widgets/camera_gps_widget.dart';
import '../widgets/signature_canvas_widget.dart';

class SurveyFormPage extends StatefulWidget {
  final int loanId;

  const SurveyFormPage({super.key, required this.loanId});

  @override
  State<SurveyFormPage> createState() => _SurveyFormPageState();
}

class _SurveyFormPageState extends State<SurveyFormPage> {
  final TextEditingController _notesController = TextEditingController();
  final Map<int, TextEditingController> _amountControllers = {};
  final Map<String, int> _scores5C = {
    'character': 5,
    'capacity': 5,
    'capital': 4,
    'collateral': 5,
    'condition': 4,
  };

  double? _latitude;
  double? _longitude;
  String? _signatureBase64;

  @override
  void initState() {
    super.initState();
    context.read<VerificationBloc>().add(LoadProposalDetailEvent(widget.loanId));
  }

  @override
  void dispose() {
    _notesController.dispose();
    for (final c in _amountControllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  void _submitForm(ProposalDetail proposal) {
    final Map<int, double> verifiedAmounts = {};
    for (final b in proposal.beneficiaries) {
      final text = _amountControllers[b.memberRowId]?.text.replaceAll(RegExp(r'[^0-9]'), '') ?? '';
      verifiedAmounts[b.memberRowId] = double.tryParse(text) ?? b.proposedAmount;
    }

    final totalVerified = verifiedAmounts.values.fold(0.0, (sum, val) => sum + val);

    context.read<VerificationBloc>().add(
          SubmitVerificationEvent(
            loanId: proposal.rowId,
            verifiedAt: DateFormat('yyyy-MM-dd').format(DateTime.now()),
            verificationAmount: totalVerified,
            verificationNotes: _notesController.text.trim(),
            verifiedAmounts: verifiedAmounts,
            latitude: _latitude,
            longitude: _longitude,
            scoring5C: _scores5C,
            signatureBase64: _signatureBase64,
          ),
        );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.surface,
      appBar: AppBar(
        title: const Text('Formulir Survei 5C'),
      ),
      body: BlocConsumer<VerificationBloc, VerificationState>(
        listener: (context, state) {
          if (state is VerificationSubmitSuccess) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.message), backgroundColor: AppColors.success),
            );
            Navigator.of(context).pop();
          } else if (state is VerificationError) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(state.message), backgroundColor: AppColors.error),
            );
          }
        },
        builder: (context, state) {
          if (state is ProposalDetailLoading || state is VerificationSubmitting) {
            return const Center(child: CircularProgressIndicator());
          } else if (state is ProposalDetailLoaded) {
            final proposal = state.proposal;
            _initControllers(proposal);

            return SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Summary Card
                  AppCard(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          proposal.loanNumber,
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          proposal.borrowerName,
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, color: AppColors.onSurface),
                        ),
                        Text(
                          '${proposal.villageName} • ${proposal.groupAddress}',
                          style: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Plafon Usulan: ${CurrencyFormatter.formatRupiah(proposal.principalAmount)}',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
                            ),
                            Text(
                              'Tenor: ${proposal.termMonths} Bln',
                              style: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Section 1: Checksheet 5C
                  const Text('1. Evaluasi Kelayakan (5C)', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  AppCard(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      children: [
                        _build5CRatingRow('Karakter Pemohon (Character)', 'character'),
                        const Divider(height: 16, color: AppColors.outlineVariant),
                        _build5CRatingRow('Kapasitas Usaha (Capacity)', 'capacity'),
                        const Divider(height: 16, color: AppColors.outlineVariant),
                        _build5CRatingRow('Kondisi Permodalan (Capital)', 'capital'),
                        const Divider(height: 16, color: AppColors.outlineVariant),
                        _build5CRatingRow('Agunan / Tanggung Renteng (Collateral)', 'collateral'),
                        const Divider(height: 16, color: AppColors.outlineVariant),
                        _build5CRatingRow('Kondisi Pasar & Ekonomi (Condition)', 'condition'),
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),

                  // Section 2: Rekomendasi Plafon Anggota
                  const Text('2. Rekomendasi Plafon Anggota', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  ...proposal.beneficiaries.map((b) => _buildBeneficiaryCard(b)),
                  const SizedBox(height: 16),

                  // Section 3: GeoTagging & Foto
                  const Text('3. Lokasi & Bukti Survei', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  CameraGpsWidget(
                    onLocationCaptured: (lat, lng) {
                      _latitude = lat;
                      _longitude = lng;
                    },
                  ),
                  const SizedBox(height: 16),

                  // Section 4: Tanda Tangan
                  const Text('4. Tanda Tangan Surveyor / Pemohon', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  SignatureCanvasWidget(
                    title: 'Kanvas Tanda Tangan',
                    onSignatureSaved: (base64) => _signatureBase64 = base64,
                  ),
                  const SizedBox(height: 16),

                  // Section 5: Catatan Verifikator
                  const Text('5. Catatan & Kesimpulan Survei', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 8),
                  TextField(
                    controller: _notesController,
                    maxLines: 3,
                    decoration: InputDecoration(
                      hintText: 'Tuliskan catatan kelayakan, rekomendasi jadwal pencairan, atau observasi lapangan...',
                      hintStyle: const TextStyle(fontSize: 12, color: AppColors.onSurfaceVariant),
                      filled: true,
                      fillColor: AppColors.surfaceContainerLowest,
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
                  const SizedBox(height: 24),

                  // Submit Button
                  AppButton(
                    text: 'Simpan Hasil Survei & Verifikasi',
                    icon: Icons.check_circle_rounded,
                    onPressed: () => _submitForm(proposal),
                  ),
                  const SizedBox(height: 32),
                ],
              ),
            );
          }
          return const SizedBox.shrink();
        },
      ),
    );
  }

  void _initControllers(ProposalDetail proposal) {
    if (_notesController.text.isEmpty && proposal.verificationNotes != null) {
      _notesController.text = proposal.verificationNotes!;
    }

    for (final b in proposal.beneficiaries) {
      if (!_amountControllers.containsKey(b.memberRowId)) {
        _amountControllers[b.memberRowId] = TextEditingController(
          text: b.verifiedAmount.toInt().toString(),
        );
      }
    }
  }

  Widget _build5CRatingRow(String label, String key) {
    final currentVal = _scores5C[key] ?? 5;
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.onSurface),
          ),
        ),
        Row(
          children: List.generate(5, (index) {
            final star = index + 1;
            return InkWell(
              onTap: () => setState(() => _scores5C[key] = star),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: Icon(
                  star <= currentVal ? Icons.star_rounded : Icons.star_border_rounded,
                  size: 22,
                  color: star <= currentVal ? AppColors.warning : AppColors.outlineVariant,
                ),
              ),
            );
          }),
        ),
      ],
    );
  }

  Widget _buildBeneficiaryCard(ProposalBeneficiary beneficiary) {
    final controller = _amountControllers[beneficiary.memberRowId];

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.surfaceContainerLowest,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.outlineVariant),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                beneficiary.fullName,
                style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.onSurface),
              ),
              Text(
                'Usulan: ${CurrencyFormatter.formatRupiah(beneficiary.proposedAmount)}',
                style: const TextStyle(fontSize: 11, color: AppColors.onSurfaceVariant),
              ),
            ],
          ),
          const SizedBox(height: 6),
          TextField(
            controller: controller,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(
              labelText: 'Rekomendasi Plafon (Rp)',
              labelStyle: const TextStyle(fontSize: 12),
              prefixText: 'Rp ',
              isDense: true,
              contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
            ),
          ),
        ],
      ),
    );
  }
}
