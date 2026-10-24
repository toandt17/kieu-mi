import 'package:flutter/material.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/app_api.dart';
import '../../theme/app_theme.dart';
import '../../utils/format.dart';
import '../../widgets/common_widgets.dart';
import '../../widgets/safe_insets.dart';
import 'order_detail_screen.dart';
import 'order_form_screen.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final _items = <SaleOrder>[];
  String _query = '';
  bool _loading = true;
  String? _error;

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
      final page = await AppApi.instance.orders(q: _query);
      if (!mounted) return;
      setState(() {
        _items
          ..clear()
          ..addAll(page.items);
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _openCreate() async {
    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(builder: (_) => const OrderFormScreen()),
    );
    if (created == true) _load();
  }

  @override
  Widget build(BuildContext context) {
    final top = MediaQuery.viewPaddingOf(context).top;
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Column(
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(16, top + 8, 12, 4),
            child: Row(
              children: [
                const Text(
                  'Đơn hàng',
                  style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                ),
                const Spacer(),
                CircleIconButton(
                  icon: Icons.add,
                  onTap: _openCreate,
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: AppSearchField(
              hint: 'Tìm mã đơn, tên khách, SĐT...',
              onChanged: (v) {
                _query = v;
                _load();
              },
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: _load,
              child: _loading && _items.isEmpty
                  ? const Center(child: CircularProgressIndicator())
                  : _error != null && _items.isEmpty
                      ? ListView(
                          children: [
                            EmptyState(icon: Icons.error_outline, title: _error!),
                          ],
                        )
                      : _items.isEmpty
                          ? ListView(
                              children: const [
                                EmptyState(
                                  icon: Icons.receipt_long_outlined,
                                  title: 'Chưa có đơn hàng',
                                  subtitle: 'Tạo đơn để ghi nhận bán hàng',
                                ),
                              ],
                            )
                          : ListView.separated(
                              padding: EdgeInsets.fromLTRB(
                                16,
                                4,
                                16,
                                Insets.tabContentBottom(context),
                              ),
                              itemCount: _items.length,
                              separatorBuilder: (_, _) =>
                                  const SizedBox(height: 10),
                              itemBuilder: (context, index) {
                                final o = _items[index];
                                return SoftCard(
                                  radius: 14,
                                  padding: const EdgeInsets.all(14),
                                  onTap: () async {
                                    final changed = await Navigator.push<bool>(
                                      context,
                                      MaterialPageRoute(
                                        builder: (_) =>
                                            OrderDetailScreen(orderId: o.id),
                                      ),
                                    );
                                    if (changed == true) _load();
                                  },
                                  child: Row(
                                    children: [
                                      CircleAvatar(
                                        backgroundColor: AppColors.headerBlue,
                                        child: Icon(
                                          Icons.receipt_long_rounded,
                                          color: o.isCancelled
                                              ? AppColors.red
                                              : AppColors.primary,
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              o.code,
                                              style: const TextStyle(
                                                fontWeight: FontWeight.w800,
                                              ),
                                            ),
                                            const SizedBox(height: 4),
                                            Text(
                                              o.customerName?.isNotEmpty == true
                                                  ? o.customerName!
                                                  : 'Khách lẻ',
                                              style: const TextStyle(
                                                color: AppColors.textSecondary,
                                                fontSize: 13,
                                              ),
                                            ),
                                            Text(
                                              '${o.itemCount} sản phẩm · ${dateTimeText(o.createdAt)}',
                                              style: const TextStyle(
                                                color: AppColors.textSecondary,
                                                fontSize: 12,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.end,
                                        children: [
                                          Text(
                                            money(o.totalAmount),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          const SizedBox(height: 6),
                                          StatusChip(
                                            label: o.isCancelled
                                                ? 'Đã hủy'
                                                : 'Hoàn tất',
                                            color: o.isCancelled
                                                ? AppColors.red
                                                : AppColors.green,
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
            ),
          ),
        ],
      ),
    );
  }
}
