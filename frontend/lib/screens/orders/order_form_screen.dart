import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/app_api.dart';
import '../../theme/app_theme.dart';
import '../../utils/format.dart';
import '../../widgets/common_widgets.dart';

class _DraftItem {
  _DraftItem({
    required this.product,
    required this.salePrice,
    required this.quantity,
  });

  final Product product;
  int salePrice;
  int quantity;

  int get lineTotal => salePrice * quantity;
}

class OrderFormScreen extends StatefulWidget {
  const OrderFormScreen({super.key});

  @override
  State<OrderFormScreen> createState() => _OrderFormScreenState();
}

class _OrderFormScreenState extends State<OrderFormScreen> {
  final _customer = TextEditingController();
  final _phone = TextEditingController();
  final _note = TextEditingController();
  final _items = <_DraftItem>[];
  bool _saving = false;

  int get _total => _items.fold(0, (sum, e) => sum + e.lineTotal);

  @override
  void dispose() {
    _customer.dispose();
    _phone.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _pickProduct() async {
    final product = await showModalBottomSheet<Product>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (_) => const _ProductPickerSheet(),
    );
    if (product == null) return;
    final existing = _items.where((e) => e.product.id == product.id);
    if (existing.isNotEmpty) {
      setState(() => existing.first.quantity += 1);
      return;
    }
    setState(() {
      _items.add(
        _DraftItem(
          product: product,
          salePrice: product.listedPrice,
          quantity: 1,
        ),
      );
    });
  }

  Future<void> _save() async {
    if (_items.isEmpty) {
      showAppSnack(context, 'Chọn ít nhất một sản phẩm');
      return;
    }
    setState(() => _saving = true);
    try {
      await AppApi.instance.createOrder(
        customerName: _customer.text.trim(),
        customerPhone: _phone.text.trim(),
        note: _note.text.trim(),
        items: _items
            .map(
              (e) => {
                'product_id': e.product.id,
                'quantity': e.quantity,
                'sale_price': e.salePrice,
              },
            )
            .toList(),
      );
      if (!mounted) return;
      showAppSnack(context, 'Đã tạo đơn hàng');
      Navigator.pop(context, true);
    } on ApiException catch (e) {
      if (!mounted) return;
      showAppSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Tạo đơn hàng')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 120),
        children: [
          SoftCard(
            radius: 14,
            padding: const EdgeInsets.all(16),
            child: Column(
              children: [
                TextField(
                  controller: _customer,
                  decoration: const InputDecoration(
                    labelText: 'Tên khách hàng',
                    filled: true,
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(
                    labelText: 'Số điện thoại',
                    filled: true,
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _note,
                  decoration: const InputDecoration(
                    labelText: 'Ghi chú đơn',
                    filled: true,
                    border: OutlineInputBorder(),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Text(
                'Sản phẩm trong đơn',
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
              ),
              const Spacer(),
              TextButton.icon(
                onPressed: _pickProduct,
                icon: const Icon(Icons.add),
                label: const Text('Thêm'),
              ),
            ],
          ),
          if (_items.isEmpty)
            const SoftCard(
              radius: 14,
              padding: EdgeInsets.all(16),
              child: Text(
                'Chọn sản phẩm và chỉnh giá bán ra cho từng dòng.',
                style: TextStyle(color: AppColors.textSecondary),
              ),
            )
          else
            ..._items.asMap().entries.map((entry) {
              final item = entry.value;
              return Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: SoftCard(
                  radius: 14,
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    children: [
                      Row(
                        children: [
                          ProductThumb(url: item.product.imageUrl),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.product.name,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Niêm yết ${money(item.product.listedPrice)} · Kho ${item.product.stockQty}',
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            onPressed: () => setState(() => _items.removeAt(entry.key)),
                            icon: const Icon(Icons.close),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(
                            child: TextFormField(
                              initialValue: '${item.salePrice}',
                              keyboardType: TextInputType.number,
                              inputFormatters: [
                                FilteringTextInputFormatter.digitsOnly,
                              ],
                              decoration: const InputDecoration(
                                labelText: 'Giá bán ra',
                                filled: true,
                                border: OutlineInputBorder(),
                                isDense: true,
                              ),
                              onChanged: (v) => setState(
                                () => item.salePrice = parseMoney(v),
                              ),
                            ),
                          ),
                          const SizedBox(width: 10),
                          _QtyBox(
                            value: item.quantity,
                            onChanged: (v) => setState(() => item.quantity = v),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Align(
                        alignment: Alignment.centerRight,
                        child: Text(
                          'Thành tiền ${money(item.lineTotal)}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
      bottomNavigationBar: Material(
        color: Colors.white,
        elevation: 10,
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Tổng thanh toán',
                        style: TextStyle(color: AppColors.textSecondary),
                      ),
                      Text(
                        money(_total),
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                ),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  style: FilledButton.styleFrom(
                    backgroundColor: AppColors.orange,
                  ),
                  child: _saving
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Lưu đơn'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _QtyBox extends StatelessWidget {
  const _QtyBox({required this.value, required this.onChanged});

  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: const Color(0xFFF3F4F6),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          IconButton(
            onPressed: value > 1 ? () => onChanged(value - 1) : null,
            icon: const Icon(Icons.remove),
          ),
          Text('$value', style: const TextStyle(fontWeight: FontWeight.w800)),
          IconButton(
            onPressed: () => onChanged(value + 1),
            icon: const Icon(Icons.add),
          ),
        ],
      ),
    );
  }
}

class _ProductPickerSheet extends StatefulWidget {
  const _ProductPickerSheet();

  @override
  State<_ProductPickerSheet> createState() => _ProductPickerSheetState();
}

class _ProductPickerSheetState extends State<_ProductPickerSheet> {
  List<Product> _items = const [];
  bool _loading = true;
  String _q = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final page = await AppApi.instance.products(q: _q, activeOnly: true);
      if (!mounted) return;
      setState(() => _items = page.items);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.78,
      child: Column(
        children: [
          const SizedBox(height: 10),
          Container(
            width: 36,
            height: 4,
            decoration: BoxDecoration(
              color: AppColors.border,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const Padding(
            padding: EdgeInsets.all(16),
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Chọn sản phẩm',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: AppSearchField(
              hint: 'Tìm sản phẩm...',
              onChanged: (v) {
                _q = v;
                _load();
              },
            ),
          ),
          const SizedBox(height: 8),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    itemCount: _items.length,
                    itemBuilder: (context, index) {
                      final p = _items[index];
                      return ListTile(
                        leading: ProductThumb(url: p.imageUrl),
                        title: Text(
                          p.name,
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                          '${money(p.listedPrice)} · Còn ${p.stockQty}',
                        ),
                        enabled: p.stockQty > 0,
                        onTap: p.stockQty > 0
                            ? () => Navigator.pop(context, p)
                            : null,
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
