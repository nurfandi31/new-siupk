import 'package:blue_thermal_printer/blue_thermal_printer.dart';
// import 'package:flutter/services.dart';

class ReceiptData {
  final String receiptNumber;
  final String transactionDate;
  final String organizationName;
  final String collectorName;
  final String loanNumber;
  final String borrowerName;
  final String payerName;
  final double principalAmount;
  final double interestAmount;
  final double penaltyAmount;
  final double totalPaid;
  final double remainingPrincipal;
  final double remainingInterest;

  const ReceiptData({
    required this.receiptNumber,
    required this.transactionDate,
    required this.organizationName,
    required this.collectorName,
    required this.loanNumber,
    required this.borrowerName,
    required this.payerName,
    required this.principalAmount,
    required this.interestAmount,
    this.penaltyAmount = 0,
    required this.totalPaid,
    required this.remainingPrincipal,
    required this.remainingInterest,
  });

  factory ReceiptData.fromJson(Map<String, dynamic> json) {
    return ReceiptData(
      receiptNumber: json['receipt_number'] as String? ?? '-',
      transactionDate: json['transaction_date'] as String? ?? '-',
      organizationName: json['organization_name'] as String? ?? 'BUMDes / LKD',
      collectorName: json['collector_name'] as String? ?? 'Petugas',
      loanNumber: json['loan_number'] as String? ?? '-',
      borrowerName: json['borrower_name'] as String? ?? '-',
      payerName: json['payer_name'] as String? ?? '-',
      principalAmount: (json['principal_amount'] as num?)?.toDouble() ?? 0.0,
      interestAmount: (json['interest_amount'] as num?)?.toDouble() ?? 0.0,
      penaltyAmount: (json['penalty_amount'] as num?)?.toDouble() ?? 0.0,
      totalPaid: (json['total_paid'] as num?)?.toDouble() ?? 0.0,
      remainingPrincipal: (json['remaining_principal'] as num?)?.toDouble() ?? 0.0,
      remainingInterest: (json['remaining_interest'] as num?)?.toDouble() ?? 0.0,
    );
  }
}

class ThermalPrinterService {
  final BlueThermalPrinter _printer = BlueThermalPrinter.instance;

  Future<List<BluetoothDevice>> getBondedDevices() async {
    try {
      final List<BluetoothDevice> devices = await _printer.getBondedDevices();
      return devices;
    } catch (_) {
      return [];
    }
  }

  Future<bool> connect(BluetoothDevice device) async {
    try {
      final isConnected = await _printer.isConnected ?? false;
      if (!isConnected) {
        await _printer.connect(device);
      }
      return true;
    } catch (_) {
      return false;
    }
  }

  Future<void> disconnect() async {
    try {
      await _printer.disconnect();
    } catch (_) {}
  }

  Future<bool> printReceipt(ReceiptData receipt) async {
    try {
      final isConnected = await _printer.isConnected ?? false;
      if (!isConnected) {
        return false;
      }

      // Header
      _printer.printCustom(receipt.organizationName, 2, 1); // Size 2, Center
      _printer.printCustom('BUKTI ANGSURAN PINJAMAN', 1, 1);
      _printer.printCustom('--------------------------------', 1, 1);

      // Meta
      _printer.printLeftRight('No. Bukti', receipt.receiptNumber, 1);
      _printer.printLeftRight('Tanggal', receipt.transactionDate, 1);
      _printer.printLeftRight('Peminjam', receipt.borrowerName, 1);
      if (receipt.payerName != receipt.borrowerName) {
        _printer.printLeftRight('Penyetor', receipt.payerName, 1);
      }
      _printer.printCustom('--------------------------------', 1, 1);

      // Nominal
      _printer.printLeftRight('Pokok', 'Rp ${_formatNum(receipt.principalAmount)}', 1);
      _printer.printLeftRight('Jasa', 'Rp ${_formatNum(receipt.interestAmount)}', 1);
      if (receipt.penaltyAmount > 0) {
        _printer.printLeftRight('Denda', 'Rp ${_formatNum(receipt.penaltyAmount)}', 1);
      }
      _printer.printCustom('--------------------------------', 1, 1);
      _printer.printLeftRight('TOTAL BAYAR', 'Rp ${_formatNum(receipt.totalPaid)}', 2);
      _printer.printCustom('--------------------------------', 1, 1);

      // Remaining & Footer
      _printer.printLeftRight('Sisa Pokok', 'Rp ${_formatNum(receipt.remainingPrincipal)}', 1);
      _printer.printLeftRight('Petugas', receipt.collectorName, 1);
      _printer.printNewLine();
      _printer.printCustom('Simpan struk ini sebagai', 0, 1);
      _printer.printCustom('bukti pembayaran sah.', 0, 1);
      _printer.printNewLine();
      _printer.printNewLine();
      _printer.paperCut();

      return true;
    } catch (_) {
      return false;
    }
  }

  String _formatNum(double amount) {
    return amount.toStringAsFixed(0).replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]}.',
        );
  }
}

