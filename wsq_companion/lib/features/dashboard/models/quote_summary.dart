class QuoteSummary {
  final int id;
  final String number;
  final String status;
  final String statusLabel;
  final String createdAtJalali;
  final String customerName;
  final String customerPhone;
  final int itemsCount;
  final double total;
  final String totalFormatted;
  final String validity;

  QuoteSummary({
    required this.id,
    required this.number,
    required this.status,
    required this.statusLabel,
    required this.createdAtJalali,
    required this.customerName,
    required this.customerPhone,
    required this.itemsCount,
    required this.total,
    required this.totalFormatted,
    required this.validity,
  });

  factory QuoteSummary.fromJson(Map<String, dynamic> json) {
    final customer = json['customer'] as Map<String, dynamic>? ?? {};
    return QuoteSummary(
      id: json['id'] as int? ?? 0,
      number: json['number']?.toString() ?? '',
      status: json['status']?.toString() ?? 'quote-request',
      statusLabel: json['status_label']?.toString() ?? '',
      createdAtJalali: json['created_at_jalali']?.toString() ?? '',
      customerName: customer['name']?.toString() ?? '',
      customerPhone: customer['phone']?.toString() ?? '',
      itemsCount: json['items_count'] as int? ?? 0,
      total: (json['total'] as num?)?.toDouble() ?? 0.0,
      totalFormatted: json['total_formatted']?.toString() ?? '',
      validity: json['validity']?.toString() ?? '',
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'number': number,
      'status': status,
      'status_label': statusLabel,
      'created_at_jalali': createdAtJalali,
      'customer': {
        'name': customerName,
        'phone': customerPhone,
      },
      'items_count': itemsCount,
      'total': total,
      'total_formatted': totalFormatted,
      'validity': validity,
    };
  }

  bool get isPending => status == 'quote-request' || status == 'wc-quote-request';
  bool get isPriced => status == 'quote-sent' || status == 'wc-quote-sent';
}
