import 'package:flutter/material.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/app_api.dart';
import '../../theme/app_theme.dart';
import '../../utils/format.dart';
import '../../widgets/common_widgets.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  SaleOrder? _order;
  String? _error;
  bool _loading = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final order = await AppApi.instance.orderDetail(widget.orderId);
      if (!mounted) return;
      setState(() => _order = order);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Hủy đơn hàng?'),
        content: const Text('Kho sẽ được hoàn lại số lượng đã bán.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Không'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Hủy đơn'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    setState(() => _busy = true);
    try {
      final order = await AppApi.instance.cancelOrder(widget.orderId);
      if (!mounted) return;
      setState(() => _order = order);
      showAppSnack(context, 'Đã hủy đơn và hoàn kho');
    } on ApiException catch (e) {
      if (!mounted) return;
      showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: Text(order?.code ?? 'Chi tiết đơn')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? EmptyState(icon: Icons.error_outline, title: _error!)
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                  children: [
                    SoftCard(
                      radius: 14,
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                order!.code,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 18,
                                ),
                              ),
                              const Spacer(),
                              StatusChip(
                                label: order.isCancelled ? 'Đã hủy' : 'Hoàn tất',
                                color: order.isCancelled
                                    ? AppColors.red
                                    : AppColors.green,
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Text(
                            order.customerName?.isNotEmpty == true
                                ? order.customerName!
                                : 'Khách lẻ',
                          ),
                          if (order.customerPhone?.isNotEmpty == true)
                            Text(order.customerPhone!),
                          const SizedBox(height: 6),
                          Text(
                            dateTimeText(order.createdAt),
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                            ),
                          ),
                          if (order.userName != null)
                            Text(
                              'Người lập: ${order.userName}',
                              style: const TextStyle(
                                color: AppColors.textSecondary,
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                    ...order.items.map(
                      (item) => Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: SoftCard(
                          radius: 14,
                          padding: const EdgeInsets.all(12),
                          child: Row(
                            children: [
                              ProductThumb(url: item.imageUrl),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      item.productName,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      'Niêm yết ${money(item.listedPrice)}',
                                      style: const TextStyle(
                                        color: AppColors.textSecondary,
                                        fontSize: 12,
                                      ),
                                    ),
                                    Text(
                                      'Bán ra ${money(item.salePrice)} × ${item.quantity}',
                                      style: const TextStyle(fontSize: 13),
                                    ),
                                  ],
                                ),
                              ),
                              Text(
                                money(item.lineTotal),
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    SoftCard(
                      radius: 14,
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        children: [
                          const Text(
                            'Tổng cộng',
                            style: TextStyle(fontWeight: FontWeight.w700),
                          ),
                          const Spacer(),
                          Text(
                            money(order.totalAmount),
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 18,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (!order.isCancelled) ...[
                      const SizedBox(height: 16),
                      OutlinedButton(
                        onPressed: _busy ? null : _cancel,
                        child: const Text('Hủy đơn và hoàn kho'),
                      ),
                    ],
                  ],
                ),
    );
  }
}
