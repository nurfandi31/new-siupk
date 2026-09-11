import 'package:flutter_test/flutter_test.dart';
import 'package:siupk_mobile/core/utils/currency_formatter.dart';
import 'package:siupk_mobile/core/utils/date_formatter.dart';
import 'package:siupk_mobile/features/auth/domain/entities/auth_entities.dart';

void main() {
  group('CurrencyFormatter', () {
    test('formats numbers to Indonesian Rupiah', () {
      expect(CurrencyFormatter.formatRupiah(1500000), contains('1.500.000'));
      expect(CurrencyFormatter.formatRupiah(0), contains('0'));
      expect(CurrencyFormatter.formatRupiah(null), contains('0'));
    });

    test('parses formatted string to double', () {
      expect(CurrencyFormatter.parse('Rp 1.500.000'), equals(1500000.0));
    });
  });

  group('DateFormatter', () {
    test('formats valid date string to short date', () {
      expect(DateFormatter.formatShort('2026-08-22'), equals('22/08/2026'));
    });
  });

  group('UserEntity', () {
    test('correctly evaluates permissions', () {
      const user = UserEntity(
        id: 1,
        name: 'Kasir Lapangan',
        username: 'kasir',
        permissions: ['loan.create', 'collection.create'],
      );

      expect(user.hasPermission('loan.create'), isTrue);
      expect(user.hasPermission('finance.report'), isFalse);
    });

    test('superadmin bypasses all permissions', () {
      const superadmin = UserEntity(
        id: 2,
        name: 'Super Admin',
        username: 'superadmin',
        isSuperadmin: true,
        permissions: [],
      );

      expect(superadmin.hasPermission('anything'), isTrue);
    });
  });
}
