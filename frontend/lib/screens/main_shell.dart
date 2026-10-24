import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/app_theme.dart';
import '../widgets/safe_insets.dart';
import 'home/home_screen.dart';
import 'more/more_screen.dart';
import 'orders/order_form_screen.dart';
import 'orders/orders_screen.dart';
import 'products/products_screen.dart';
import 'stock/stock_screen.dart';

class MainShell extends StatefulWidget {
  const MainShell({super.key});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;
  int _ordersTick = 0;
  int _productsTick = 0;
  int _stockTick = 0;
  int _homeTick = 0;

  void _refreshAll() {
    setState(() {
      _homeTick++;
      _ordersTick++;
      _productsTick++;
      _stockTick++;
    });
  }

  Future<void> _newOrder() async {
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const OrderFormScreen()),
    );
    if (created == true) _refreshAll();
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = Insets.bottom(context);
    final pages = [
      HomeScreen(key: ValueKey('home-$_homeTick'), onOpenTab: (i) => setState(() => _index = i)),
      OrdersScreen(key: ValueKey('orders-$_ordersTick')),
      ProductsScreen(key: ValueKey('products-$_productsTick')),
      StockScreen(key: ValueKey('stock-$_stockTick')),
      MoreScreen(onChanged: _refreshAll),
    ];

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: kLightOverlay,
      child: Scaffold(
        body: IndexedStack(index: _index, children: pages),
        floatingActionButton: _index == 1
            ? FloatingActionButton(
                onPressed: _newOrder,
                backgroundColor: AppColors.orange,
                child: const Icon(Icons.add, color: Colors.white, size: 28),
              )
            : null,
        bottomNavigationBar: Material(
          color: Colors.white,
          elevation: 8,
          shadowColor: Colors.black26,
          child: Padding(
            padding: EdgeInsets.only(bottom: bottomInset),
            child: SizedBox(
              height: 56,
              child: Row(
                children: [
                  _NavItem(
                    icon: Icons.space_dashboard_outlined,
                    activeIcon: Icons.space_dashboard_rounded,
                    label: 'Tổng quan',
                    selected: _index == 0,
                    onTap: () => setState(() => _index = 0),
                  ),
                  _NavItem(
                    icon: Icons.receipt_long_outlined,
                    activeIcon: Icons.receipt_long_rounded,
                    label: 'Đơn hàng',
                    selected: _index == 1,
                    onTap: () => setState(() => _index = 1),
                  ),
                  _NavItem(
                    icon: Icons.inventory_2_outlined,
                    activeIcon: Icons.inventory_2_rounded,
                    label: 'Sản phẩm',
                    selected: _index == 2,
                    onTap: () => setState(() => _index = 2),
                  ),
                  _NavItem(
                    icon: Icons.warehouse_outlined,
                    activeIcon: Icons.warehouse_rounded,
                    label: 'Kho',
                    selected: _index == 3,
                    onTap: () => setState(() => _index = 3),
                  ),
                  _NavItem(
                    icon: Icons.widgets_outlined,
                    activeIcon: Icons.widgets_rounded,
                    label: 'Thêm',
                    selected: _index == 4,
                    onTap: () => setState(() => _index = 4),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AppColors.primary : AppColors.textSecondary;
    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(selected ? activeIcon : icon, color: color, size: 22),
            const SizedBox(height: 3),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                fontSize: 9,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
