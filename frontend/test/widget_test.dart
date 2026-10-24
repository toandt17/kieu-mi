import 'package:flutter_test/flutter_test.dart';
import 'package:quan_ly_ban_hang/utils/format.dart';

void main() {
  test('định dạng tiền Việt Nam', () {
    final text = money(189000);
    expect(text.contains('189'), isTrue);
    expect(text.contains('₫'), isTrue);
  });
}
