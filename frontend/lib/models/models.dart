int asInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? 0}') ?? 0;
}

bool asBool(dynamic value) {
  if (value is bool) return value;
  return value == 1 || value == '1' || value == 'true';
}

class AuthUser {
  const AuthUser({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.role = 'staff',
    this.isActive = true,
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final bool isActive;

  bool get isAdmin => role == 'admin';

  String get roleLabel => isAdmin ? 'Quản trị' : 'Nhân viên';

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: asInt(json['id']),
      name: '${json['name'] ?? ''}',
      email: '${json['email'] ?? ''}',
      phone: json['phone']?.toString(),
      role: '${json['role'] ?? 'staff'}',
      isActive: asBool(json['is_active'] ?? true),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'phone': phone,
        'role': role,
        'is_active': isActive,
      };
}

class Product {
  const Product({
    required this.id,
    required this.name,
    required this.listedPrice,
    required this.stockQty,
    required this.importedQty,
    required this.soldQty,
    this.sku,
    this.imageUrl,
    this.note,
    this.isActive = true,
  });

  final int id;
  final String name;
  final String? sku;
  final int listedPrice;
  final int stockQty;
  final int importedQty;
  final int soldQty;
  final String? imageUrl;
  final String? note;
  final bool isActive;

  factory Product.fromJson(Map<String, dynamic> json) {
    return Product(
      id: asInt(json['id']),
      name: '${json['name'] ?? ''}',
      sku: json['sku']?.toString(),
      listedPrice: asInt(json['listed_price']),
      stockQty: asInt(json['stock_qty']),
      importedQty: asInt(json['imported_qty']),
      soldQty: asInt(json['sold_qty']),
      imageUrl: json['image_url']?.toString(),
      note: json['note']?.toString(),
      isActive: asBool(json['is_active'] ?? true),
    );
  }
}

class OrderItem {
  const OrderItem({
    required this.id,
    required this.productName,
    required this.listedPrice,
    required this.salePrice,
    required this.quantity,
    required this.lineTotal,
    this.productId,
    this.imageUrl,
  });

  final int id;
  final int? productId;
  final String productName;
  final String? imageUrl;
  final int listedPrice;
  final int salePrice;
  final int quantity;
  final int lineTotal;

  factory OrderItem.fromJson(Map<String, dynamic> json) {
    return OrderItem(
      id: asInt(json['id']),
      productId: json['product_id'] == null ? null : asInt(json['product_id']),
      productName: '${json['product_name'] ?? ''}',
      imageUrl: json['product_image_url']?.toString(),
      listedPrice: asInt(json['listed_price']),
      salePrice: asInt(json['sale_price']),
      quantity: asInt(json['quantity']),
      lineTotal: asInt(json['line_total']),
    );
  }
}

class SaleOrder {
  const SaleOrder({
    required this.id,
    required this.code,
    required this.status,
    required this.totalAmount,
    required this.itemCount,
    this.customerName,
    this.customerPhone,
    this.note,
    this.userName,
    this.createdAt,
    this.items = const [],
  });

  final int id;
  final String code;
  final String? customerName;
  final String? customerPhone;
  final String status;
  final int totalAmount;
  final String? note;
  final String? userName;
  final int itemCount;
  final String? createdAt;
  final List<OrderItem> items;

  bool get isCancelled => status == 'cancelled';

  factory SaleOrder.fromJson(Map<String, dynamic> json) {
    final rawItems = json['items'];
    return SaleOrder(
      id: asInt(json['id']),
      code: '${json['code'] ?? ''}',
      customerName: json['customer_name']?.toString(),
      customerPhone: json['customer_phone']?.toString(),
      status: '${json['status'] ?? 'completed'}',
      totalAmount: asInt(json['total_amount']),
      note: json['note']?.toString(),
      userName: json['user_name']?.toString(),
      itemCount: asInt(json['item_count']),
      createdAt: json['created_at']?.toString(),
      items: rawItems is List
          ? rawItems
              .whereType<Map>()
              .map((e) => OrderItem.fromJson(Map<String, dynamic>.from(e)))
              .toList()
          : const [],
    );
  }
}

class StockImport {
  const StockImport({
    required this.id,
    required this.productId,
    required this.quantity,
    this.productName,
    this.imageUrl,
    this.unitCost,
    this.note,
    this.userName,
    this.createdAt,
  });

  final int id;
  final int productId;
  final String? productName;
  final String? imageUrl;
  final int quantity;
  final int? unitCost;
  final String? note;
  final String? userName;
  final String? createdAt;

  factory StockImport.fromJson(Map<String, dynamic> json) {
    return StockImport(
      id: asInt(json['id']),
      productId: asInt(json['product_id']),
      productName: json['product_name']?.toString(),
      imageUrl: json['product_image_url']?.toString(),
      quantity: asInt(json['quantity']),
      unitCost: json['unit_cost'] == null ? null : asInt(json['unit_cost']),
      note: json['note']?.toString(),
      userName: json['user_name']?.toString(),
      createdAt: json['created_at']?.toString(),
    );
  }
}

class DashboardData {
  const DashboardData({
    required this.todayRevenue,
    required this.monthRevenue,
    required this.todayOrders,
    required this.monthOrders,
    required this.productCount,
    required this.userCount,
    required this.lowStockCount,
    required this.lowStock,
    required this.recentOrders,
  });

  final int todayRevenue;
  final int monthRevenue;
  final int todayOrders;
  final int monthOrders;
  final int productCount;
  final int userCount;
  final int lowStockCount;
  final List<Product> lowStock;
  final List<SaleOrder> recentOrders;

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    return DashboardData(
      todayRevenue: asInt(json['today_revenue']),
      monthRevenue: asInt(json['month_revenue']),
      todayOrders: asInt(json['today_orders']),
      monthOrders: asInt(json['month_orders']),
      productCount: asInt(json['product_count']),
      userCount: asInt(json['user_count']),
      lowStockCount: asInt(json['low_stock_count']),
      lowStock: (json['low_stock'] as List? ?? [])
          .whereType<Map>()
          .map((e) => Product.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
      recentOrders: (json['recent_orders'] as List? ?? [])
          .whereType<Map>()
          .map((e) => SaleOrder.fromJson(Map<String, dynamic>.from(e)))
          .toList(),
    );
  }
}

class Paged<T> {
  const Paged({
    required this.items,
    required this.page,
    required this.lastPage,
    required this.total,
  });

  final List<T> items;
  final int page;
  final int lastPage;
  final int total;
}
