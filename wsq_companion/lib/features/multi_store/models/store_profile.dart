class StoreProfile {
  final String storeUrl;
  final String apiBase;
  final String storeName;
  final String currency;
  final int lastUsed;

  StoreProfile({
    required this.storeUrl,
    required this.apiBase,
    required this.storeName,
    required this.currency,
    required this.lastUsed,
  });

  factory StoreProfile.fromJson(Map<String, dynamic> json) {
    return StoreProfile(
      storeUrl: json['store_url']?.toString() ?? '',
      apiBase: json['api_base']?.toString() ?? '',
      storeName: json['store_name']?.toString() ?? 'فروشگاه',
      currency: json['currency']?.toString() ?? 'تومان',
      lastUsed: json['last_used'] as int? ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'store_url': storeUrl,
      'api_base': apiBase,
      'store_name': storeName,
      'currency': currency,
      'last_used': lastUsed,
    };
  }
}
