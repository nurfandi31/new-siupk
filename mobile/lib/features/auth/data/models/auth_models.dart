import '../../domain/entities/auth_entities.dart';

class UserModel extends UserEntity {
  const UserModel({
    required super.id,
    required super.name,
    required super.username,
    super.email,
    super.phone,
    super.isSuperadmin,
    super.isRegencyUser,
    super.isProvinceUser,
    super.isVillageUser,
    super.villageRowId,
    super.permissions,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as int? ?? json['row_id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      username: json['username'] as String? ?? '',
      email: json['email'] as String?,
      phone: json['phone'] as String?,
      isSuperadmin: json['is_superadmin'] as bool? ?? false,
      isRegencyUser: json['is_regency_user'] as bool? ?? false,
      isProvinceUser: json['is_province_user'] as bool? ?? false,
      isVillageUser: json['is_village_user'] as bool? ?? false,
      villageRowId: json['village_row_id'] as int?,
      permissions: (json['permissions'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'username': username,
      'email': email,
      'phone': phone,
      'is_superadmin': isSuperadmin,
      'is_regency_user': isRegencyUser,
      'is_province_user': isProvinceUser,
      'is_village_user': isVillageUser,
      'village_row_id': villageRowId,
      'permissions': permissions,
    };
  }
}

class TenantModel extends TenantEntity {
  const TenantModel({
    required super.id,
    required super.code,
    required super.name,
    required super.status,
    super.districtName,
    super.regencyName,
    super.isTraining,
  });

  factory TenantModel.fromJson(Map<String, dynamic> json) {
    return TenantModel(
      id: json['id'] as int? ?? json['row_id'] as int? ?? 0,
      code: json['code'] as String? ?? '',
      name: json['name'] as String? ?? '',
      status: json['status'] as String? ?? 'active',
      districtName: json['district_name'] as String?,
      regencyName: json['regency_name'] as String?,
      isTraining: json['is_training'] as bool? ?? false,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'code': code,
      'name': name,
      'status': status,
      'district_name': districtName,
      'regency_name': regencyName,
      'is_training': isTraining,
    };
  }
}
