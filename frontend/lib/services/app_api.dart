import '../models/models.dart';
import 'api_client.dart';
import 'auth_session.dart';

class AppApi {
  AppApi._();
  static final AppApi instance = AppApi._();
  final _api = ApiClient.instance;

  Future<AuthUser> login({required String login, required String password}) async {
    final json = await _api.postJson('/login', {
      'login': login,
      'password': password,
    });
    final data = Map<String, dynamic>.from(json['data'] as Map);
    final user = AuthUser.fromJson(Map<String, dynamic>.from(data['user'] as Map));
    await AuthSession.instance.save(
      token: '${data['token']}',
      user: user,
    );
    return user;
  }

  Future<void> logout() async {
    try {
      await _api.postJson('/logout', {});
    } catch (_) {}
    await AuthSession.instance.clear();
  }

  Future<DashboardData> dashboard() async {
    final json = await _api.get('/dashboard');
    return DashboardData.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<Paged<Product>> products({String q = '', bool activeOnly = false}) async {
    final json = await _api.get('/products', query: {
      if (q.isNotEmpty) 'q': q,
      if (activeOnly) 'active_only': '1',
      'per_page': '50',
    });
    return _api.paged(json, Product.fromJson);
  }

  Future<Product> saveProduct({
    int? id,
    required String name,
    String? sku,
    required int listedPrice,
    int? initialQty,
    String? note,
    bool isActive = true,
    List<int>? imageBytes,
    String? filename,
  }) async {
    final fields = <String, String>{
      'name': name,
      'listed_price': '$listedPrice',
      'is_active': isActive ? '1' : '0',
      if (sku != null && sku.isNotEmpty) 'sku': sku,
      'note': ?note,
      if (id == null && initialQty != null) 'initial_qty': '$initialQty',
    };
    final path = id == null ? '/products' : '/products/$id';
    final json = await _api.postMultipart(
      path,
      fields: fields,
      fileBytes: imageBytes,
      filename: filename ?? 'product.jpg',
    );
    return Product.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<void> deleteProduct(int id) async {
    await _api.delete('/products/$id');
  }

  Future<Paged<SaleOrder>> orders({String q = '', String? status}) async {
    final json = await _api.get('/orders', query: {
      if (q.isNotEmpty) 'q': q,
      'status': ?status,
      'per_page': '40',
    });
    return _api.paged(json, SaleOrder.fromJson);
  }

  Future<SaleOrder> orderDetail(int id) async {
    final json = await _api.get('/orders/$id');
    return SaleOrder.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<SaleOrder> createOrder({
    String? customerName,
    String? customerPhone,
    String? note,
    required List<Map<String, dynamic>> items,
  }) async {
    final json = await _api.postJson('/orders', {
      'customer_name': customerName,
      'customer_phone': customerPhone,
      'note': note,
      'items': items,
    });
    return SaleOrder.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<SaleOrder> cancelOrder(int id) async {
    final json = await _api.postJson('/orders/$id/cancel', {});
    return SaleOrder.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<Paged<StockImport>> stockImports() async {
    final json = await _api.get('/stock-imports', query: {'per_page': '40'});
    return _api.paged(json, StockImport.fromJson);
  }

  Future<StockImport> importStock({
    required int productId,
    required int quantity,
    int? unitCost,
    String? note,
  }) async {
    final json = await _api.postJson('/stock-imports', {
      'product_id': productId,
      'quantity': quantity,
      'unit_cost': ?unitCost,
      'note': ?note,
    });
    return StockImport.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<Paged<AuthUser>> users({String q = ''}) async {
    final json = await _api.get('/users', query: {
      if (q.isNotEmpty) 'q': q,
      'per_page': '40',
    });
    return _api.paged(json, AuthUser.fromJson);
  }

  Future<AuthUser> saveUser({
    int? id,
    required String name,
    required String email,
    String? phone,
    String? password,
    required String role,
    bool isActive = true,
  }) async {
    final body = {
      'name': name,
      'email': email,
      'phone': phone,
      'role': role,
      'is_active': isActive,
      if (password != null && password.isNotEmpty) 'password': password,
    };
    final json = id == null
        ? await _api.postJson('/users', body)
        : await _api.putJson('/users/$id', body);
    return AuthUser.fromJson(Map<String, dynamic>.from(json['data'] as Map));
  }

  Future<void> deleteUser(int id) async {
    await _api.delete('/users/$id');
  }
}
