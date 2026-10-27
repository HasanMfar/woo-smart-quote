class QuoteItem {
  final int itemId;
  final int productId;
  final int variationId;
  final String name;
  final int quantity;
  double unitPrice;
  double discountPercent;
  double subtotal;
  double total;
  final String imageUrl;
  final String sku;

  QuoteItem({
    required this.itemId,
    required this.productId,
    required this.variationId,
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.discountPercent,
    required this.subtotal,
    required this.total,
    required this.imageUrl,
    required this.sku,
  });

  factory QuoteItem.fromJson(Map<String, dynamic> json) {
    return QuoteItem(
      itemId: json['item_id'] as int? ?? 0,
      productId: json['product_id'] as int? ?? 0,
      variationId: json['variation_id'] as int? ?? 0,
      name: json['name']?.toString() ?? '',
      quantity: json['quantity'] as int? ?? 1,
      unitPrice: (json['unit_price'] as num?)?.toDouble() ?? 0.0,
      discountPercent: (json['discount_percent'] as num?)?.toDouble() ?? 0.0,
      subtotal: (json['subtotal'] as num?)?.toDouble() ?? 0.0,
      total: (json['total'] as num?)?.toDouble() ?? 0.0,
      imageUrl: json['image_url']?.toString() ?? '',
      sku: json['sku']?.toString() ?? '',
    );
  }

  void recalculate() {
    subtotal = unitPrice * quantity;
    total = subtotal * (1 - (discountPercent / 100));
  }
}

class QuoteDetail {
  final int id;
  final String number;
  final String status;
  final String statusLabel;
  final String createdAtJalali;
  final String customerName;
  final String customerPhone;
  final String customerEmail;
  final String customerNote;
  final List<QuoteItem> items;
  final double total;
  final String totalFormatted;
  final String currency;
  final String validity;
  final String paymentUrl;
  final String pdfUrl;
  final List<Map<String, dynamic>> orderNotes;
  final List<Map<String, dynamic>> pricingHistory;

  QuoteDetail({
    required this.id,
    required this.number,
    required this.status,
    required this.statusLabel,
    required this.createdAtJalali,
    required this.customerName,
    required this.customerPhone,
    required this.customerEmail,
    required this.customerNote,
    required this.items,
    required this.total,
    required this.totalFormatted,
    required this.currency,
    required this.validity,
    required this.paymentUrl,
    required this.pdfUrl,
    required this.orderNotes,
    this.pricingHistory = const [],
  });

  factory QuoteDetail.fromJson(Map<String, dynamic> json) {
    final customer = json['customer'] as Map<String, dynamic>? ?? {};
    final itemsRaw = json['items'] as List? ?? [];
    final itemsList = itemsRaw.map((e) => QuoteItem.fromJson(e as Map<String, dynamic>)).toList();
    final notesRaw = json['order_notes'] as List? ?? [];
    final notesList = notesRaw.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    final historyRaw = json['pricing_history'] as List? ?? [];
    final historyList = historyRaw.map((e) => Map<String, dynamic>.from(e as Map)).toList();

    return QuoteDetail(
      id: json['id'] as int? ?? 0,
      number: json['number']?.toString() ?? '',
      status: json['status']?.toString() ?? 'quote-request',
      statusLabel: json['status_label']?.toString() ?? '',
      createdAtJalali: json['created_at_jalali']?.toString() ?? '',
      customerName: customer['name']?.toString() ?? '',
      customerPhone: customer['phone']?.toString() ?? '',
      customerEmail: customer['email']?.toString() ?? '',
      customerNote: customer['note']?.toString() ?? '',
      items: itemsList,
      total: (json['total'] as num?)?.toDouble() ?? 0.0,
      totalFormatted: json['total_formatted']?.toString() ?? '',
      currency: json['currency']?.toString() ?? 'تومان',
      validity: json['validity']?.toString() ?? '۲۴ ساعت کاری',
      paymentUrl: json['payment_url']?.toString() ?? '',
      pdfUrl: json['pdf_url']?.toString() ?? '',
      orderNotes: notesList,
      pricingHistory: historyList,
    );
  }

  bool get isPending => status == 'quote-request' || status == 'wc-quote-request';
  bool get isPriced => status == 'quote-sent' || status == 'wc-quote-sent';
}
