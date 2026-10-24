import 'package:flutter/material.dart';

import '../../services/app_api.dart';
import '../../services/auth_session.dart';
import '../../theme/app_theme.dart';
import '../../widgets/common_widgets.dart';
import '../../widgets/safe_insets.dart';
import '../auth/login_screen.dart';
import '../orders/order_form_screen.dart';
import '../products/product_form_screen.dart';
import '../stock/stock_screen.dart';
import '../users/users_screen.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key, this.onChanged});

  final VoidCallback? onChanged;

  Future<void> _logout(BuildContext context) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Đăng xuất?'),
        content: const Text('Bạn sẽ cần đăng nhập lại để quản lý bán hàng.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Hủy'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Đăng xuất'),
          ),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;
    await AppApi.instance.logout();
    if (!context.mounted) return;
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
      (_) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = AuthSession.instance.user;
    final name = user?.name ?? 'Người dùng';
    return Scaffold(
      backgroundColor: AppColors.background,
      body: CustomScrollView(
        slivers: [
          SliverToBoxAdapter(
            child: Builder(
              builder: (context) {
                final top = MediaQuery.viewPaddingOf(context).top;
                return Padding(
                  padding: EdgeInsets.fromLTRB(16, top + 8, 16, 8),
                  child: const Text(
                    'Thêm',
                    style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
                  ),
                );
              },
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              child: SoftCard(
                radius: 12,
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    AppAvatar(initials: initialsOf(name), size: 54),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            name,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            user?.roleLabel ?? '',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                            ),
                          ),
                          Text(
                            user?.email ?? '',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
              child: SoftCard(
                radius: 8,
                padding: const EdgeInsets.fromLTRB(8, 14, 8, 8),
                child: GridView.count(
                  crossAxisCount: 4,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [
                    _tile(
                      context,
                      Icons.add_shopping_cart_rounded,
                      'Tạo đơn',
                      AppColors.orange,
                      () async {
                        final ok = await Navigator.push<bool>(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const OrderFormScreen(),
                          ),
                        );
                        if (ok == true) onChanged?.call();
                      },
                    ),
                    _tile(
                      context,
                      Icons.add_box_rounded,
                      'Sản phẩm',
                      AppColors.primary,
                      () async {
                        final ok = await Navigator.push<bool>(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const ProductFormScreen(),
                          ),
                        );
                        if (ok == true) onChanged?.call();
                      },
                    ),
                    _tile(
                      context,
                      Icons.move_to_inbox_rounded,
                      'Nhập kho',
                      AppColors.green,
                      () => Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const StockScreen()),
                      ),
                    ),
                    if (user?.isAdmin == true)
                      _tile(
                        context,
                        Icons.manage_accounts_rounded,
                        'Tài khoản',
                        AppColors.purple,
                        () => Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const UsersScreen()),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
              child: SoftCard(
                radius: 12,
                padding: EdgeInsets.zero,
                child: ListTile(
                  leading: CircleAvatar(
                    backgroundColor: AppColors.red.withValues(alpha: 0.12),
                    child: const Icon(Icons.logout, color: AppColors.red),
                  ),
                  title: const Text(
                    'Đăng xuất',
                    style: TextStyle(
                      fontWeight: FontWeight.w700,
                      color: AppColors.red,
                    ),
                  ),
                  subtitle: Text(user?.email ?? ''),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => _logout(context),
                ),
              ),
            ),
          ),
          SliverToBoxAdapter(
            child: SizedBox(height: Insets.tabContentBottom(context)),
          ),
        ],
      ),
    );
  }

  Widget _tile(
    BuildContext context,
    IconData icon,
    String label,
    Color color,
    VoidCallback onTap,
  ) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Column(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: const Color(0xFFF3F6FA),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 26),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}
