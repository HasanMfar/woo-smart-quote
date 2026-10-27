class ApiEndpoints {
  static const String pair = '/auth/pair';
  static const String quotes = '/quotes';
  static const String updates = '/updates';

  static String quoteDetail(int id) => '/quotes/$id';
  static String quotePrice(int id) => '/quotes/$id/price';
  static String quotePdf(int id) => '/quotes/$id/pdf';
  static String quoteNotify(int id) => '/quotes/$id/notify';
}
