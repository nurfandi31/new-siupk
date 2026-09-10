import 'package:equatable/equatable.dart';

class UserEntity extends Equatable {
  final int id;
  final String name;
  final String username;
  final String? email;
  final String? phone;
  final bool isSuperadmin;
  final bool isRegencyUser;
  final bool isProvinceUser;
  final bool isVillageUser;
  final int? villageRowId;
  final List<String> permissions;

  const UserEntity({
    required this.id,
    required this.name,
    required this.username,
    this.email,
    this.phone,
    this.isSuperadmin = false,
    this.isRegencyUser = false,
    this.isProvinceUser = false,
    this.isVillageUser = false,
    this.villageRowId,
    this.permissions = const [],
  });

  bool hasPermission(String permission) {
    if (isSuperadmin || permissions.contains('*')) return true;
    return permissions.contains(permission);
  }

  @override
  List<Object?> get props => [
        id,
        name,
        username,
        email,
        phone,
        isSuperadmin,
        isRegencyUser,
        isProvinceUser,
        isVillageUser,
        villageRowId,
        permissions,
      ];
}

class TenantEntity extends Equatable {
  final int id;
  final String code;
  final String name;
  final String status;
  final String? districtName;
  final String? regencyName;
  final bool isTraining;

  const TenantEntity({
    required this.id,
    required this.code,
    required this.name,
    required this.status,
    this.districtName,
    this.regencyName,
    this.isTraining = false,
  });

  @override
  List<Object?> get props => [id, code, name, status, districtName, regencyName, isTraining];
}

class AuthSession extends Equatable {
  final String token;
  final UserEntity user;
  final TenantEntity? tenant;

  const AuthSession({
    required this.token,
    required this.user,
    this.tenant,
  });

  @override
  List<Object?> get props => [token, user, tenant];
}
