(function () {
  var token = readPref('auth_token');
  var user = readPref('auth_user', true) || {};
  if (!token) { location.replace('/'); return; }

  var cache = { dash: null, orders: [], products: [], stock: [], returns: [], users: [] };
  var filters = {
    orders: { status: '', from: '', to: '' },
    products: { stock: '', active: '' },
    stock: { from: '', to: '' },
    returns: { from: '', to: '' }
  };
  var draft = {};

  function readPref(key, asObject) {
    try {
      var raw = localStorage.getItem('flutter.' + key);
      if (!raw) return asObject ? null : '';
      var v = JSON.parse(raw);
      if (typeof v === 'string') {
        if (!asObject) return v;
        try { return JSON.parse(v); } catch (e) { return null; }
      }
      return asObject ? (v || null) : String(v || '');
    } catch (e) {
      return asObject ? null : '';
    }
  }
  function money(n) { return Number(n || 0).toLocaleString('vi-VN') + 'đ'; }
  function greet() {
    var h = new Date().getHours();
    return h < 12 ? 'Chào buổi sáng' : h < 18 ? 'Chào buổi chiều' : 'Chào buổi tối';
  }
  function when(s) {
    if (!s) return '';
    var d = new Date(s);
    if (isNaN(d)) return s;
    return d.toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
  }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  function initials(name) {
    var p = String(name || '').trim().split(/\s+/);
    if (!p[0]) return '?';
    if (p.length === 1) return p[0].slice(0, 1).toUpperCase();
    return (p[p.length - 2].slice(0, 1) + p[p.length - 1].slice(0, 1)).toUpperCase();
  }
  function ico(name) { return '<span class="ms">' + name + '</span>'; }
  function toast(msg) {
    var el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.remove('hidden');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { el.classList.add('hidden'); }, 2200);
  }
  function firstErr(json, fallback) {
    if (json && json.errors) {
      var k = Object.keys(json.errors)[0];
      if (k && json.errors[k] && json.errors[k][0]) return json.errors[k][0];
    }
    return (json && json.message) || fallback || 'Thất bại';
  }
  function statusChip(st) {
    if (st === 'cancelled') return '<span class="chip bad">Đã hủy</span>';
    if (st === 'returned') return '<span class="chip warn">Đã trả</span>';
    if (st === 'partial') return '<span class="chip info">Trả một phần</span>';
    return '<span class="chip ok">Hoàn tất</span>';
  }
  function statusLabel(st) {
    return ({ cancelled: 'Đã hủy', returned: 'Đã trả', partial: 'Trả một phần', completed: 'Hoàn tất' })[st] || st;
  }
  function qs(obj) {
    var p = [];
    Object.keys(obj).forEach(function (k) {
      if (obj[k] !== '' && obj[k] != null) p.push(encodeURIComponent(k) + '=' + encodeURIComponent(obj[k]));
    });
    return p.length ? '&' + p.join('&') : '';
  }
  function filterOn(key) {
    var f = filters[key] || {};
    return Object.keys(f).some(function (k) { return f[k]; });
  }
  function go(path) { location.hash = path; }
  function route() {
    var parts = (location.hash || '#/').replace(/^#\/?/, '').split('/').filter(Boolean);
    return { parts: parts, a: parts[0] || 'home', b: parts[1] || '', c: parts[2] || '' };
  }
  function tabOf(r) {
    if (!r.a || r.a === 'home') return 'home';
    if (r.a === 'orders' || r.a === 'returns') return 'orders';
    if (r.a === 'products') return 'products';
    if (r.a === 'stock') return 'stock';
    return 'more';
  }

  async function api(path, opt) {
    opt = opt || {};
    var headers = { Accept: 'application/json', Authorization: 'Bearer ' + token };
    if (opt.body && !(opt.body instanceof FormData)) headers['Content-Type'] = 'application/json';
    var ctrl = new AbortController();
    var t = setTimeout(function () { ctrl.abort(); }, 20000);
    var res, json = {};
    try {
      res = await fetch('/api' + path, {
        method: opt.method || 'GET',
        headers: headers,
        body: opt.body ? (opt.body instanceof FormData ? opt.body : JSON.stringify(opt.body)) : undefined,
        signal: ctrl.signal
      });
      json = await res.json().catch(function () { return {}; });
    } finally { clearTimeout(t); }
    if (res && res.status === 401) {
      localStorage.removeItem('flutter.auth_token');
      localStorage.removeItem('flutter.auth_user');
      location.replace('/');
      throw new Error('Hết phiên đăng nhập');
    }
    if (!res || !res.ok || json.success === false) throw new Error(firstErr(json, 'Không kết nối được máy chủ'));
    return json;
  }

  function openSheet(html) {
    var el = document.getElementById('sheet');
    el.innerHTML = '<div class="box"><div class="grab"></div>' + html + '</div>';
    el.classList.remove('hidden');
  }
  function closeSheet() {
    document.getElementById('sheet').classList.add('hidden');
    document.getElementById('sheet').innerHTML = '';
  }

  function navHtml(r) {
    var tab = tabOf(r);
    var items = [
      ['home', '/', 'space_dashboard', 'Tổng quan'],
      ['orders', '/orders', 'receipt_long', 'Đơn hàng'],
      ['products', '/products', 'inventory_2', 'Sản phẩm'],
      ['stock', '/stock', 'warehouse', 'Kho'],
      ['more', '/more', 'widgets', 'Thêm']
    ];
    return items.map(function (it) {
      return '<button type="button" data-go="' + it[1] + '" class="' + (tab === it[0] ? 'on' : '') + '">' +
        ico(it[2]) + '<span class="lbl">' + it[3] + '</span></button>';
    }).join('');
  }

  function pageBar(title, extra) {
    return '<div class="appbar">' +
      '<button type="button" class="round ghost" data-back>' + ico('arrow_back') + '</button>' +
      '<div class="grow"><h1>' + title + '</h1></div>' +
      (extra || '') + '</div>';
  }
  function listBar(title, extra) {
    return '<div class="topbar"><div class="grow"><h1>' + title + '</h1></div>' + (extra || '') + '</div>';
  }
  function kv(label, value) {
    return '<div class="kv"><span>' + label + '</span><b>' + (value == null || value === '' ? '—' : value) + '</b></div>';
  }
  function card(title, inner) {
    return '<div class="pad" style="margin-top:12px"><div class="soft card">' +
      (title ? '<div class="card-title">' + title + '</div>' : '') + inner + '</div></div>';
  }
  function whenFull(s) {
    if (!s) return '—';
    var d = new Date(s);
    if (isNaN(d)) return s;
    return d.toLocaleString('vi-VN', { weekday: 'short', hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit', year: 'numeric' });
  }
  function searchRow(id, placeholder, filterKey, addPath) {
    return '<div class="search-row">' +
      '<input class="search" id="' + id + '" placeholder="' + placeholder + '">' +
      '<button type="button" class="round light" data-filter="' + filterKey + '">' + ico('tune') +
        (filterOn(filterKey) ? '<i class="badge"></i>' : '') + '</button>' +
      (addPath ? '<button type="button" class="round light" data-go="' + addPath + '">' + ico('add') + '</button>' : '') +
      '</div>';
  }

  function render() {
    var r = route();
    document.getElementById('nav').innerHTML = navHtml(r);
    var app = document.getElementById('app');
    if (r.a === 'home') app.innerHTML = viewHome();
    else if (r.a === 'orders' && r.b === 'new') app.innerHTML = viewOrderForm();
    else if (r.a === 'orders' && r.b && r.c === 'return') app.innerHTML = viewReturnForm(r.b);
    else if (r.a === 'orders' && r.b) app.innerHTML = viewOrderDetail(r.b);
    else if (r.a === 'orders') app.innerHTML = viewOrders();
    else if (r.a === 'returns' && r.b === 'new') app.innerHTML = viewReturnPick();
    else if (r.a === 'returns' && r.b) app.innerHTML = viewReturnDetail(r.b);
    else if (r.a === 'returns') app.innerHTML = viewReturns();
    else if (r.a === 'products' && r.b === 'new') app.innerHTML = viewProductForm();
    else if (r.a === 'products' && r.b && r.c === 'edit') app.innerHTML = viewProductForm(r.b);
    else if (r.a === 'products' && r.b) app.innerHTML = viewProductDetail(r.b);
    else if (r.a === 'products') app.innerHTML = viewProducts();
    else if (r.a === 'stock' && r.b === 'new') app.innerHTML = viewStockForm();
    else if (r.a === 'stock' && r.b) app.innerHTML = viewStockDetail(r.b);
    else if (r.a === 'stock') app.innerHTML = viewStock();
    else if (r.a === 'users' && r.b === 'new') app.innerHTML = viewUserForm();
    else if (r.a === 'users' && r.b && r.c === 'edit') app.innerHTML = viewUserForm(r.b);
    else if (r.a === 'users' && r.b) app.innerHTML = viewUserDetail(r.b);
    else if (r.a === 'users') app.innerHTML = viewUsers();
    else app.innerHTML = viewMore();
  }

  function viewHome() {
    var d = cache.dash;
    var name = user.name || 'Bạn';
    var role = user.role === 'admin' ? 'Quản trị' : 'Nhân viên';
    var body = '<div class="empty">Đang tải tổng quan...</div>';
    if (d) {
      body =
        '<div class="pad"><div class="stats">' +
          stat('payments', '#FF6A00', money(d.today_revenue), 'Doanh thu hôm nay') +
          stat('receipt_long', '#2F7BFF', d.today_orders, 'Đơn hôm nay') +
          stat('trending_up', '#34C759', money(d.month_revenue), 'Doanh thu tháng') +
          stat('inventory_2', '#AF52DE', (d.product_count || 0) + ' / ' + (d.low_stock_count || 0), 'Sản phẩm / sắp hết') +
        '</div></div>' +
        '<div class="sec"><h3>Thao tác nhanh</h3></div>' +
        '<div class="pad"><div class="soft"><div class="quick">' +
          tile('add_shopping_cart', '#FF6A00', 'Tạo đơn', '/orders/new') +
          tile('add_box', '#2F7BFF', 'Tạo hàng', '/products/new') +
          tile('assignment_return', '#AF52DE', 'Trả hàng', '/returns/new') +
          tile('move_to_inbox', '#34C759', 'Nhập kho', '/stock/new') +
        '</div></div></div>' +
        '<div class="sec"><h3>Đơn gần đây</h3><a href="#/orders">Tất cả</a></div>' +
        orderCards(d.recent_orders || []) +
        '<div class="sec"><h3>Sắp hết hàng</h3><a href="#/products">Kho</a></div>' +
        lowStock(d.low_stock || []);
    } else if (cache.homeErr) {
      body = '<div class="empty">' + esc(cache.homeErr) + '</div>';
    }
    return '<div class="hero"><div class="hero-top"><div class="brand">KIỀU MI</div><div class="grow"></div>' +
      '<button type="button" class="round" data-go="/orders">' + ico('search') + '</button></div>' +
      '<p class="greet">' + greet() + '</p><h1>' + esc(name) + '</h1><p class="role">' + role + '</p></div>' +
      '<div class="gap"></div>' + body;
  }
  function stat(icon, color, value, label) {
    return '<div class="soft stat" style="color:' + color + '">' + ico(icon) +
      '<div class="v">' + value + '</div><div class="k">' + label + '</div></div>';
  }
  function tile(icon, color, label, path) {
    return '<button type="button" class="tile" data-go="' + path + '"><div class="box" style="color:' + color + '">' +
      ico(icon) + '</div><span>' + label + '</span></button>';
  }
  function orderCards(items) {
    if (!items.length) return '<div class="pad"><div class="soft item"><div class="muted">Chưa có đơn hàng.</div></div></div>';
    return '<div class="list">' + items.map(orderRow).join('') + '</div>';
  }
  function orderRow(o) {
    var extra = [];
    if (o.customer_name) extra.push(esc(o.customer_name));
    else extra.push('Khách lẻ');
    if (o.qty_total) extra.push(o.qty_total + ' SP');
    else if (o.item_count) extra.push(o.item_count + ' món');
    return '<button type="button" class="item" data-go="/orders/' + o.id + '"><div class="row">' +
      '<div><b>' + esc(o.code) + '</b><div class="muted" style="margin-top:3px">' + extra.join(' · ') + '</div></div>' +
      '<div style="text-align:right"><b>' + money(o.total_amount) + '</b><div style="margin-top:4px">' + statusChip(o.status) + '</div></div></div></button>';
  }
  function lowStock(items) {
    if (!items.length) return '<div class="pad"><div class="soft item"><div class="muted">Tồn kho đang ổn.</div></div></div>';
    return '<div class="hscroll">' + items.map(function (p) {
      return '<button type="button" class="soft stock-card" data-go="/products/' + p.id + '"><b>' + esc(p.name) +
        '</b><div class="muted" style="margin-top:6px;color:#c45300;font-weight:700">Còn ' + p.stock_qty + '</div><div class="muted">' + money(p.listed_price) + '</div></button>';
    }).join('') + '</div>';
  }

  function viewOrders() {
    return listBar('Đơn hàng') +
      searchRow('q-orders', 'Tìm mã đơn, khách, SĐT', 'orders', '/orders/new') +
      (cache.orders.length ? '<div class="list" style="padding-top:12px">' + cache.orders.map(orderRow).join('') + '</div>' : '<div class="empty">Đang tải đơn hàng...</div>');
  }
  function viewOrderDetail(id) {
    var o = cache.order;
    if (!o || String(o.id) !== String(id)) {
      return pageBar('Chi tiết đơn hàng') + '<div class="empty">Đang tải...</div>';
    }
    var qty = (o.items || []).reduce(function (s, it) { return s + Number(it.quantity || 0); }, 0) || o.qty_total || 0;
    var returnedAmt = (o.items || []).reduce(function (s, it) { return s + Number(it.sale_price || 0) * Number(it.returned_qty || 0); }, 0);
    var net = Math.max(0, Number(o.total_amount || 0) - returnedAmt);
    var items = (o.items || []).map(function (it, i) {
      return '<div class="soft line-item">' +
        '<div class="row"><b>' + (i + 1) + '. ' + esc(it.product_name) + '</b><b>' + money(it.line_total) + '</b></div>' +
        kv('Giá niêm yết', money(it.listed_price)) +
        kv('Giá bán ra', money(it.sale_price)) +
        kv('Số lượng', it.quantity + ' sản phẩm') +
        (it.returned_qty ? kv('Đã trả', it.returned_qty + ' sản phẩm') : '') +
        kv('Thành tiền', money(it.line_total)) +
      '</div>';
    }).join('');
    var rets = (o.returns || []).map(function (r) {
      return '<button type="button" class="item" data-go="/returns/' + r.id + '"><div class="row"><div><b>' + esc(r.code) + '</b><div class="muted">' + esc(r.reason) + ' · ' + when(r.created_at) + '</div></div><b>' + money(r.total_amount) + '</b></div></button>';
    }).join('');
    var canReturn = o.status === 'completed' || o.status === 'partial';
    return pageBar('Chi tiết đơn hàng') +
      card('', '<div class="row"><div><b style="font-size:18px">' + esc(o.code) + '</b><div class="muted" style="margin-top:4px">' + whenFull(o.created_at) + '</div></div>' + statusChip(o.status) + '</div>') +
      card('Khách hàng',
        kv('Bán cho', esc(o.customer_name || 'Khách lẻ')) +
        kv('Số điện thoại', esc(o.customer_phone || 'Không có')) +
        kv('Ghi chú khách', esc(o.note || 'Không có'))
      ) +
      card('Thông tin đơn',
        kv('Trạng thái', statusLabel(o.status)) +
        kv('Số mặt hàng', (o.item_count || (o.items || []).length) + ' loại') +
        kv('Tổng số lượng', qty + ' sản phẩm') +
        kv('Người lập', esc(o.user_name || '—')) +
        kv('Tài khoản lập', esc(o.user_email || '—')) +
        kv('Thời gian tạo', whenFull(o.created_at))
      ) +
      '<div class="sec"><h3>Sản phẩm trong đơn</h3></div><div class="list">' + (items || '<div class="soft item"><div class="muted">Không có món</div></div>') + '</div>' +
      card('Thanh toán',
        kv('Tạm tính', money(o.total_amount)) +
        kv('Đã trả hàng', returnedAmt ? ('−' + money(returnedAmt)) : '0đ') +
        '<div class="kv"><span>Tổng thanh toán</span><b class="sum">' + money(net) + '</b></div>'
      ) +
      (rets ? '<div class="sec"><h3>Phiếu trả liên quan</h3></div><div class="list">' + rets + '</div>' : '') +
      '<div class="pad" style="margin:14px 16px 8px">' +
        (canReturn ? '<button class="btn o" type="button" data-go="/orders/' + o.id + '/return">Trả hàng</button><div class="gap"></div>' : '') +
        (o.status !== 'cancelled' && o.status !== 'returned' ? '<button class="btn ghost" type="button" data-cancel="' + o.id + '">Hủy đơn và hoàn kho</button>' : '') +
      '</div>';
  }
  function viewOrderForm() {
    draft.order = draft.order || { name: '', phone: '', note: '', items: [] };
    var d = draft.order;
    var total = d.items.reduce(function (s, it) { return s + it.sale_price * it.quantity; }, 0);
    var rows = d.items.map(function (it, i) {
      return '<div class="soft item"><div class="row"><div><b>' + esc(it.name) + '</b><div class="muted">Kho ' + it.stock + ' · ' + money(it.listed) + '</div></div>' +
        '<button type="button" class="round light" data-rm-item="' + i + '">' + ico('close') + '</button></div>' +
        '<div class="line" style="margin-top:8px"><input data-price="' + i + '" inputmode="numeric" value="' + it.sale_price + '" style="flex:1">' +
        qtyHtml(i, it.quantity) + '</div>' +
        '<div class="muted" style="margin-top:6px;text-align:right">Thành tiền ' + money(it.sale_price * it.quantity) + '</div></div>';
    }).join('');
    return pageBar('Tạo đơn hàng') + '<div class="gap"></div>' +
      '<div class="pad"><div class="soft item">' +
        field('Tên khách', 'o-name', d.name, 'Khách lẻ') +
        field('Số điện thoại', 'o-phone', d.phone, '090...') +
        field('Ghi chú', 'o-note', d.note, '') +
      '</div></div>' +
      '<div class="sec"><h3>Sản phẩm</h3><button type="button" class="link" data-pick-product>Thêm</button></div>' +
      '<div class="list">' + (rows || '<div class="soft item"><div class="muted">Chọn sản phẩm, chỉnh giá bán và số lượng.</div></div>') + '</div>' +
      '<div class="footbar"><div class="grow"><div class="muted">Tổng thanh toán</div><b>' + money(total) + '</b></div>' +
      '<button class="btn o sm" type="button" id="o-save">Lưu đơn</button></div>';
  }
  function qtyHtml(i, v) {
    return '<div class="qty"><button type="button" data-qty="' + i + '" data-d="-1">' + ico('remove') + '</button><b>' + v + '</b><button type="button" data-qty="' + i + '" data-d="1">' + ico('add') + '</button></div>';
  }
  function field(label, id, value, ph, type) {
    return '<div class="field"><label for="' + id + '">' + label + '</label><input id="' + id + '" type="' + (type || 'text') + '" value="' + esc(value || '') + '" placeholder="' + (ph || '') + '"></div>';
  }

  function viewReturnPick() {
    var list = (cache.orders || []).filter(function (o) { return o.status === 'completed' || o.status === 'partial'; });
    return pageBar('Chọn đơn trả hàng') + '<div class="pad" style="padding-top:10px"><div class="muted">Chọn đơn đã bán để lập phiếu trả.</div></div><div class="gap"></div>' +
      (list.length ? '<div class="list">' + list.map(orderRow).map(function (html) {
        return html.replace(/\/orders\/(\d+)/, '/orders/$1/return');
      }).join('') : '<div class="empty">Không có đơn hoàn tất để trả.</div>');
  }
  function viewReturnForm(orderId) {
    var o = cache.order;
    if (!o || String(o.id) !== String(orderId)) return pageBar('Tạo phiếu trả') + '<div class="empty">Đang tải đơn...</div>';
    draft.ret = draft.ret || { reason: 'Đổi ý', note: '', qty: {} };
    var rows = (o.items || []).map(function (it) {
      var max = it.returnable_qty != null ? it.returnable_qty : (it.quantity - (it.returned_qty || 0));
      var q = draft.ret.qty[it.id] || 0;
      if (max <= 0) return '<div class="soft item"><b>' + esc(it.product_name) + '</b><div class="muted">Đã trả hết</div></div>';
      return '<div class="soft item"><div class="row"><div><b>' + esc(it.product_name) + '</b><div class="muted">' + money(it.sale_price) + ' · còn trả ' + max + '</div></div>' +
        '<div class="qty"><button type="button" data-rq="' + it.id + '" data-max="' + max + '" data-d="-1">' + ico('remove') + '</button><b>' + q + '</b><button type="button" data-rq="' + it.id + '" data-max="' + max + '" data-d="1">' + ico('add') + '</button></div></div></div>';
    }).join('');
    var total = (o.items || []).reduce(function (s, it) { return s + (draft.ret.qty[it.id] || 0) * it.sale_price; }, 0);
    return pageBar('Tạo phiếu trả') +
      card('Đơn gốc', kv('Mã đơn', esc(o.code)) + kv('Khách hàng', esc(o.customer_name || 'Khách lẻ')) + kv('SĐT', esc(o.customer_phone || '—')) + kv('Tổng đơn', money(o.total_amount))) +
      '<div class="pad"><div class="soft item"><div class="field"><label>Lý do</label><select id="r-reason">' +
        ['Đổi ý', 'Lỗi sản phẩm', 'Sai hàng', 'Khách không vừa', 'Khác'].map(function (x) {
          return '<option' + (draft.ret.reason === x ? ' selected' : '') + '>' + x + '</option>';
        }).join('') + '</select></div>' + field('Ghi chú', 'r-note', draft.ret.note) + '</div></div>' +
      '<div class="sec"><h3>Chọn số lượng trả</h3></div><div class="list">' + rows + '</div>' +
      '<div class="footbar"><div class="grow"><div class="muted">Hoàn tiền</div><b>' + money(total) + '</b></div>' +
      '<button class="btn o sm" type="button" id="r-save" data-oid="' + o.id + '">Lưu phiếu</button></div>';
  }
  function viewReturns() {
    var rows = cache.returns.map(function (r) {
      return '<button type="button" class="item" data-go="/returns/' + r.id + '"><div class="row"><div><b>' + esc(r.code) + '</b><div class="muted">' + esc(r.order_code || '') + ' · ' + esc(r.reason) + '</div></div><div style="text-align:right"><b>' + money(r.total_amount) + '</b><div class="muted">' + when(r.created_at) + '</div></div></div></button>';
    }).join('');
    return listBar('Phiếu trả hàng') +
      searchRow('q-returns', 'Tìm mã phiếu, đơn, khách', 'returns', '/returns/new') +
      (rows ? '<div class="list" style="padding-top:12px">' + rows + '</div>' : '<div class="empty">Chưa có phiếu trả.</div>');
  }
  function viewReturnDetail(id) {
    var r = cache.returnOne;
    if (!r || String(r.id) !== String(id)) return pageBar('Chi tiết phiếu trả') + '<div class="empty">Đang tải...</div>';
    var qty = (r.items || []).reduce(function (s, it) { return s + Number(it.quantity || 0); }, 0);
    var items = (r.items || []).map(function (it, i) {
      return '<div class="soft line-item">' +
        '<div class="row"><b>' + (i + 1) + '. ' + esc(it.product_name) + '</b><b>' + money(it.line_total) + '</b></div>' +
        kv('Giá bán lúc mua', money(it.sale_price)) +
        kv('Số lượng trả', it.quantity + ' sản phẩm') +
        kv('Hoàn tiền dòng', money(it.line_total)) +
      '</div>';
    }).join('');
    return pageBar('Chi tiết phiếu trả') +
      card('', '<div class="row"><div><b style="font-size:18px">' + esc(r.code) + '</b><div class="muted" style="margin-top:4px">' + whenFull(r.created_at) + '</div></div><span class="chip warn">Phiếu trả</span></div>') +
      card('Thông tin phiếu',
        kv('Đơn gốc', esc(r.order_code || '—')) +
        kv('Khách hàng', esc(r.customer_name || 'Khách lẻ')) +
        kv('Lý do trả', esc(r.reason)) +
        kv('Ghi chú', esc(r.note || 'Không có')) +
        kv('Người lập', esc(r.user_name || '—')) +
        kv('Số mặt hàng trả', (r.item_count || (r.items || []).length) + ' loại') +
        kv('Tổng số lượng trả', qty + ' sản phẩm')
      ) +
      '<div class="sec"><h3>Hàng đã trả</h3></div><div class="list">' + (items || '<div class="soft item"><div class="muted">Không có món</div></div>') + '</div>' +
      card('Hoàn tiền', '<div class="kv"><span>Tổng hoàn lại khách</span><b class="sum">' + money(r.total_amount) + '</b></div>') +
      (r.order_id ? '<div class="pad" style="margin:14px 16px"><button class="btn sec" type="button" data-go="/orders/' + r.order_id + '">Xem đơn gốc</button></div>' : '');
  }

  function viewProducts() {
    var rows = cache.products.map(function (p) {
      return '<button type="button" class="item" data-go="/products/' + p.id + '"><div class="row">' +
        '<div><b>' + esc(p.name) + '</b><div class="muted">' + esc(p.sku || 'Không mã') + ' · ' + money(p.listed_price) + '</div></div>' +
        '<div style="text-align:right"><b>Kho ' + p.stock_qty + '</b><div class="muted">' + (p.is_active ? 'Đang bán' : 'Đã ẩn') + '</div></div></div></button>';
    }).join('');
    return listBar('Sản phẩm') +
      searchRow('q-products', 'Tìm tên, mã SKU', 'products', '/products/new') +
      (rows ? '<div class="list" style="padding-top:12px">' + rows + '</div>' : '<div class="empty">Đang tải sản phẩm...</div>');
  }
  function viewProductDetail(id) {
    var p = cache.product;
    if (!p || String(p.id) !== String(id)) return pageBar('Chi tiết sản phẩm') + '<div class="empty">Đang tải...</div>';
    return pageBar('Chi tiết sản phẩm', '<button type="button" class="round ghost" data-go="/products/' + p.id + '/edit">' + ico('edit') + '</button>') +
      '<div class="pad" style="margin-top:12px"><div class="soft" style="overflow:hidden">' +
        (p.image_url ? '<div class="img-pick" style="margin:0;border-radius:12px 12px 0 0;height:180px"><img src="' + esc(p.image_url) + '" alt=""></div>' : '<div class="img-pick" style="margin:0;border-radius:12px 12px 0 0">' + ico('inventory_2') + '</div>') +
        '<div class="card"><b style="font-size:20px">' + esc(p.name) + '</b><div class="muted" style="margin-top:4px">' + (p.sku ? 'SKU: ' + esc(p.sku) : 'Chưa có mã SKU') + '</div></div>' +
      '</div></div>' +
      card('Giá & kho',
        kv('Giá niêm yết', money(p.listed_price)) +
        kv('Tồn kho hiện tại', p.stock_qty + ' sản phẩm') +
        kv('Tổng đã nhập', p.imported_qty + ' sản phẩm') +
        kv('Đã bán', p.sold_qty + ' sản phẩm') +
        kv('Còn lại ước tính', Math.max(0, (p.imported_qty || 0) - (p.sold_qty || 0)) + ' sản phẩm')
      ) +
      card('Thông tin khác',
        kv('Trạng thái', p.is_active ? 'Đang bán' : 'Đã ẩn') +
        kv('Ghi chú', esc(p.note || 'Không có')) +
        kv('Ngày tạo', whenFull(p.created_at)) +
        kv('Cập nhật', whenFull(p.updated_at))
      ) +
      '<div class="pad" style="margin:14px 16px"><button class="btn" type="button" data-go="/products/' + p.id + '/edit">Sửa sản phẩm</button>' +
      '<div class="gap"></div><button class="btn sec" type="button" data-go="/stock/new">Nhập kho mặt hàng này</button>' +
      '<div class="gap"></div><button class="btn ghost" type="button" data-del-product="' + p.id + '">Xóa / ẩn</button></div>';
  }
  function viewProductForm(id) {
    var p = id ? (cache.product || {}) : {};
    return pageBar(id ? 'Sửa sản phẩm' : 'Tạo hàng') + '<div class="gap"></div>' +
      '<div class="pad"><div class="soft item">' +
        '<label class="img-pick" for="p-image">' + (p.image_url ? '<img id="p-preview" src="' + esc(p.image_url) + '">' : '<div id="p-preview-empty">' + ico('add_a_photo') + '<div class="muted">Ảnh sản phẩm</div></div>') + '</label>' +
        '<input id="p-image" type="file" accept="image/*" class="hidden">' +
        field('Tên sản phẩm', 'p-name', p.name, 'Son môi...') +
        field('Giá bán', 'p-price', p.listed_price, '0', 'text') +
        field('Mã SKU', 'p-sku', p.sku, 'SM-001') +
        (id ? '' : field('Tồn kho ban đầu', 'p-qty', '0', '0')) +
        '<div class="field"><label>Ghi chú</label><textarea id="p-note">' + esc(p.note || '') + '</textarea></div>' +
        '<div class="switch field"><label>Đang bán</label><select id="p-active"><option value="1"' + (p.is_active !== false ? ' selected' : '') + '>Có</option><option value="0"' + (p.is_active === false ? ' selected' : '') + '>Ẩn</option></select></div>' +
      '</div></div>' +
      '<div class="pad" style="margin-top:12px"><button class="btn" type="button" data-save-product="' + (id || '') + '">Lưu sản phẩm</button></div>';
  }

  function viewStock() {
    var rows = cache.stock.map(function (s) {
      return '<button type="button" class="item" data-go="/stock/' + s.id + '"><div class="row"><div><b>' + esc(s.product_name || 'Sản phẩm') + '</b><div class="muted">' + when(s.created_at) + (s.user_name ? ' · ' + esc(s.user_name) : '') + (s.note ? ' · ' + esc(s.note) : '') + '</div></div><b style="color:#c45300">+' + s.quantity + '</b></div></button>';
    }).join('');
    return listBar('Nhập kho') +
      searchRow('q-stock', 'Lọc theo ngày ở nút bộ lọc', 'stock', '/stock/new') +
      (rows ? '<div class="list" style="padding-top:12px">' + rows + '</div>' : '<div class="empty">Đang tải phiếu nhập...</div>');
  }
  function viewStockDetail(id) {
    var s = cache.stockOne;
    if (!s || String(s.id) !== String(id)) return pageBar('Chi tiết phiếu nhập') + '<div class="empty">Đang tải...</div>';
    return pageBar('Chi tiết phiếu nhập') +
      card('', '<div class="row"><div><b style="font-size:18px">' + esc(s.product_name || 'Sản phẩm') + '</b><div class="muted" style="margin-top:4px">' + whenFull(s.created_at) + '</div></div><span class="chip info">Nhập kho</span></div>') +
      card('Sản phẩm',
        kv('Tên hàng', esc(s.product_name || '—')) +
        kv('Mã SKU', esc(s.product_sku || 'Không mã')) +
        kv('Giá bán hiện tại', s.product_listed_price != null ? money(s.product_listed_price) : '—') +
        kv('Tồn kho sau nhập', s.product_stock_qty != null ? (s.product_stock_qty + ' sản phẩm') : '—')
      ) +
      card('Phiếu nhập',
        kv('Số lượng nhập', '+' + s.quantity + ' sản phẩm') +
        kv('Giá vốn / cái', s.unit_cost != null ? money(s.unit_cost) : 'Không ghi') +
        kv('Thành tiền vốn', s.line_cost != null ? money(s.line_cost) : '—') +
        kv('Ghi chú', esc(s.note || 'Không có')) +
        kv('Người nhập', esc(s.user_name || '—')) +
        kv('Thời gian', whenFull(s.created_at))
      ) +
      (s.product_id ? '<div class="pad" style="margin:14px 16px"><button class="btn sec" type="button" data-go="/products/' + s.product_id + '">Xem sản phẩm</button></div>' : '');
  }
  function viewStockForm() {
    var opts = cache.products.filter(function (p) { return p.is_active; }).map(function (p) {
      return '<option value="' + p.id + '">' + esc(p.name) + ' · kho ' + p.stock_qty + '</option>';
    }).join('');
    return pageBar('Nhập kho') + '<div class="gap"></div>' +
      '<div class="pad"><div class="soft card">' +
        '<div class="field"><label>Sản phẩm</label><select id="s-p">' + opts + '</select></div>' +
        field('Số lượng', 's-q', '1') +
        field('Giá vốn / cái (không bắt buộc)', 's-cost', '') +
        field('Ghi chú', 's-note', '') +
      '</div></div>' +
      '<div class="pad" style="margin-top:12px"><button class="btn o" type="button" id="s-save">Xác nhận nhập kho</button></div>';
  }

  function viewUsers() {
    if (user.role !== 'admin') return pageBar('Tài khoản') + '<div class="empty">Chỉ quản trị xem được trang này.</div>';
    var rows = cache.users.map(function (u) {
      return '<button type="button" class="item" data-go="/users/' + u.id + '"><div class="row"><div><b>' + esc(u.name) + '</b><div class="muted">' + esc(u.email) + (u.phone ? ' · ' + esc(u.phone) : '') + '</div></div><div class="chip info">' + (u.role === 'admin' ? 'Quản trị' : 'Nhân viên') + '</div></div></button>';
    }).join('');
    return listBar('Tài khoản', '<button type="button" class="round light" data-go="/users/new">' + ico('add') + '</button>') +
      (rows ? '<div class="list" style="padding-top:12px">' + rows + '</div>' : '<div class="empty">Đang tải...</div>');
  }
  function viewUserDetail(id) {
    var u = cache.userOne;
    if (!u || String(u.id) !== String(id)) return pageBar('Chi tiết tài khoản') + '<div class="empty">Đang tải...</div>';
    return pageBar('Chi tiết tài khoản', '<button type="button" class="round ghost" data-go="/users/' + u.id + '/edit">' + ico('edit') + '</button>') +
      '<div class="pad" style="margin-top:12px"><div class="soft profile" style="padding:16px"><div class="avatar" style="width:56px;height:56px">' + esc(initials(u.name)) + '</div>' +
      '<div><b style="font-size:17px">' + esc(u.name) + '</b><div class="muted">' + (u.role === 'admin' ? 'Quản trị' : 'Nhân viên') + '</div></div></div></div>' +
      card('Thông tin tài khoản',
        kv('Họ tên', esc(u.name)) +
        kv('Email', esc(u.email || '—')) +
        kv('Số điện thoại', esc(u.phone || 'Không có')) +
        kv('Vai trò', u.role === 'admin' ? 'Quản trị' : 'Nhân viên') +
        kv('Trạng thái', u.is_active === false ? 'Đã khóa' : 'Đang hoạt động') +
        kv('Ngày tạo', whenFull(u.created_at)) +
        kv('Cập nhật', whenFull(u.updated_at))
      ) +
      '<div class="pad" style="margin:14px 16px"><button class="btn" type="button" data-go="/users/' + u.id + '/edit">Sửa tài khoản</button></div>';
  }
  function viewUserForm(id) {
    var u = id ? (cache.userOne && String(cache.userOne.id) === String(id) ? cache.userOne : (cache.users.find(function (x) { return String(x.id) === String(id); }) || {})) : {};
    return pageBar(id ? 'Sửa tài khoản' : 'Tạo tài khoản') + '<div class="gap"></div>' +
      '<div class="pad"><div class="soft item">' +
        field('Tên', 'u-name', u.name) + field('Email', 'u-email', u.email, '', 'email') +
        field('Số điện thoại', 'u-phone', u.phone) + field('Mật khẩu' + (id ? ' (để trống nếu giữ)' : ''), 'u-pass', '', '', 'password') +
        '<div class="field"><label>Vai trò</label><select id="u-role"><option value="staff"' + (u.role !== 'admin' ? ' selected' : '') + '>Nhân viên</option><option value="admin"' + (u.role === 'admin' ? ' selected' : '') + '>Quản trị</option></select></div>' +
      '</div></div>' +
      '<div class="pad" style="margin-top:12px"><button class="btn" type="button" data-save-user="' + (id || '') + '">Lưu</button></div>';
  }

  function viewMore() {
    var admin = user.role === 'admin';
    var name = user.name || 'Người dùng';
    return listBar('Thêm') + '<div class="gap"></div>' +
      '<div class="pad"><div class="soft profile"><div class="avatar">' + esc(initials(name)) + '</div>' +
      '<div><b>' + esc(name) + '</b><div class="muted">' + (admin ? 'Quản trị' : 'Nhân viên') + '</div><div class="muted">' + esc(user.email || '') + '</div></div></div></div>' +
      '<div class="sec"><h3>Nghiệp vụ</h3></div>' +
      '<div class="pad"><div class="soft"><div class="quick">' +
        tile('add_shopping_cart', '#FF6A00', 'Tạo đơn', '/orders/new') +
        tile('add_box', '#2F7BFF', 'Tạo hàng', '/products/new') +
        tile('assignment_return', '#AF52DE', 'Trả hàng', '/returns') +
        tile('move_to_inbox', '#34C759', 'Nhập kho', '/stock/new') +
        tile('receipt_long', '#2F7BFF', 'Đơn hàng', '/orders') +
        tile('inventory_2', '#0D9488', 'Sản phẩm', '/products') +
        (admin ? tile('manage_accounts', '#AF52DE', 'Tài khoản', '/users') : '') +
      '</div></div></div>' +
      '<div class="pad" style="margin-top:14px"><button type="button" class="soft logout" data-go="logout">' +
        '<div class="ico">' + ico('logout') + '</div><div class="grow"><b>Đăng xuất</b><div class="muted">' + esc(user.email || '') + '</div></div>' + ico('chevron_right') +
      '</button></div>';
  }

  function showFilter(key) {
    var f = filters[key];
    var html = '<h3>Bộ lọc</h3>';
    if (key === 'orders') {
      html += '<div class="field"><label>Trạng thái</label><div class="chips">' +
        chipBtn('f-st', '', 'Tất cả', f.status) +
        chipBtn('f-st', 'completed', 'Hoàn tất', f.status) +
        chipBtn('f-st', 'partial', 'Trả một phần', f.status) +
        chipBtn('f-st', 'returned', 'Đã trả', f.status) +
        chipBtn('f-st', 'cancelled', 'Đã hủy', f.status) + '</div></div>';
    }
    if (key === 'products') {
      html += '<div class="field"><label>Trạng thái</label><div class="chips">' +
        chipBtn('f-ac', '', 'Tất cả', f.active) + chipBtn('f-ac', '1', 'Đang bán', f.active) + chipBtn('f-ac', '0', 'Đã ẩn', f.active) + '</div></div>' +
        '<div class="field"><label>Tồn kho</label><div class="chips">' +
        chipBtn('f-sk', '', 'Tất cả', f.stock) + chipBtn('f-sk', 'low', 'Sắp hết', f.stock) + chipBtn('f-sk', 'out', 'Hết hàng', f.stock) + '</div></div>';
    }
    if (key === 'orders' || key === 'stock' || key === 'returns') {
      html += '<div class="field"><label>Từ ngày</label><input id="f-from" type="date" value="' + esc(f.from) + '"></div>' +
        '<div class="field"><label>Đến ngày</label><input id="f-to" type="date" value="' + esc(f.to) + '"></div>';
    }
    html += '<button class="btn" type="button" data-apply-filter="' + key + '">Áp dụng</button><div class="gap"></div>' +
      '<button class="btn sec" type="button" data-clear-filter="' + key + '">Xóa lọc</button>';
    openSheet(html);
  }
  function chipBtn(name, value, label, current) {
    return '<button type="button" class="' + (String(current) === String(value) ? 'on' : '') + '" data-chip="' + name + '" data-v="' + value + '">' + label + '</button>';
  }
  function showProductPicker() {
    var q = '';
    function list(items) {
      return items.filter(function (p) { return p.is_active && (!q || (p.name + ' ' + (p.sku || '')).toLowerCase().indexOf(q) >= 0); }).map(function (p) {
        var off = p.stock_qty <= 0;
        return '<button type="button" class="item" data-add-p="' + p.id + '" ' + (off ? 'disabled' : '') + '><div class="row"><div><b>' + esc(p.name) + '</b><div class="muted">' + money(p.listed_price) + ' · Còn ' + p.stock_qty + '</div></div></div></button>';
      }).join('') || '<div class="muted">Không có sản phẩm</div>';
    }
    openSheet('<h3>Chọn sản phẩm</h3><div class="field"><input id="pick-q" placeholder="Tìm sản phẩm..."></div><div id="pick-list" class="list" style="padding:0">' + list(cache.products) + '</div>');
    var input = document.getElementById('pick-q');
    if (input) input.oninput = function () {
      q = input.value.trim().toLowerCase();
      document.getElementById('pick-list').innerHTML = list(cache.products);
    };
  }

  async function loadHome() {
    try { cache.homeErr = null; cache.dash = (await api('/dashboard')).data || {}; }
    catch (e) { cache.homeErr = e.message; }
    if (route().a === 'home') render();
  }
  async function loadOrders(q) {
    var f = filters.orders;
    cache.orders = (await api('/orders?per_page=50' + qs({ q: q || '', status: f.status, from: f.from, to: f.to }))).data || [];
    if (route().a === 'orders' && !route().b) render();
    if (route().a === 'returns' && route().b === 'new') render();
  }
  async function loadOrder(id) {
    cache.order = (await api('/orders/' + id)).data || null;
    draft.ret = { reason: 'Đổi ý', note: '', qty: {} };
    render();
  }
  async function loadProducts(q) {
    var f = filters.products;
    cache.products = (await api('/products?per_page=80' + qs({ q: q || '', stock: f.stock, active: f.active }))).data || [];
    var r = route();
    if ((r.a === 'products' && !r.b) || (r.a === 'stock' && r.b === 'new') || (r.a === 'orders' && r.b === 'new')) render();
  }
  async function loadProduct(id) {
    cache.product = (await api('/products/' + id)).data || null;
    render();
  }
  async function loadStock() {
    var f = filters.stock;
    cache.stock = (await api('/stock-imports?per_page=50' + qs({ from: f.from, to: f.to }))).data || [];
    if (route().a === 'stock' && !route().b) render();
  }
  async function loadStockOne(id) {
    cache.stockOne = (await api('/stock-imports/' + id)).data || null;
    render();
  }
  async function loadUser(id) {
    cache.userOne = (await api('/users/' + id)).data || null;
    render();
  }
  async function loadReturns(q) {
    var f = filters.returns;
    cache.returns = (await api('/returns?per_page=50' + qs({ q: q || '', from: f.from, to: f.to }))).data || [];
    if (route().a === 'returns' && !route().b) render();
  }
  async function loadReturn(id) {
    cache.returnOne = (await api('/returns/' + id)).data || null;
    render();
  }
  async function loadUsers() {
    cache.users = (await api('/users?per_page=50')).data || [];
    if (route().a === 'users') render();
  }
  async function logout() {
    try { await api('/logout', { method: 'POST', body: {} }); } catch (e) {}
    localStorage.removeItem('flutter.auth_token');
    localStorage.removeItem('flutter.auth_user');
    location.replace('/');
  }

  function bootPage(r) {
    if (r.a === 'home') loadHome();
    else if (r.a === 'orders' && r.b === 'new') { if (!cache.products.length) loadProducts().catch(function (e) { toast(e.message); }); }
    else if (r.a === 'orders' && r.b && r.c === 'return') {
      loadOrder(r.b).catch(function (e) { toast(e.message); });
    } else if (r.a === 'orders' && r.b) loadOrder(r.b).catch(function (e) { toast(e.message); });
    else if (r.a === 'orders') loadOrders().catch(function (e) { toast(e.message); });
    else if (r.a === 'returns' && r.b === 'new') loadOrders().catch(function (e) { toast(e.message); });
    else if (r.a === 'returns' && r.b) loadReturn(r.b).catch(function (e) { toast(e.message); });
    else if (r.a === 'returns') loadReturns().catch(function (e) { toast(e.message); });
    else if (r.a === 'products' && r.b === 'new') {}
    else if (r.a === 'products' && r.b && r.c === 'edit') loadProduct(r.b).catch(function (e) { toast(e.message); });
    else if (r.a === 'products' && r.b) loadProduct(r.b).catch(function (e) { toast(e.message); });
    else if (r.a === 'products') loadProducts().catch(function (e) { toast(e.message); });
    else if (r.a === 'stock' && r.b === 'new') { if (!cache.products.length) loadProducts().catch(function (e) { toast(e.message); }); }
    else if (r.a === 'stock' && r.b) loadStockOne(r.b).catch(function (e) { toast(e.message); });
    else if (r.a === 'stock') loadStock().catch(function (e) { toast(e.message); });
    else if (r.a === 'users' && r.b === 'new') {}
    else if (r.a === 'users' && r.b) loadUser(r.b).catch(function (e) { toast(e.message); });
    else if (r.a === 'users') loadUsers().catch(function (e) { toast(e.message); });
  }

  function syncOrderDraft() {
    if (!draft.order) return;
    var n = document.getElementById('o-name');
    if (n) { draft.order.name = n.value; draft.order.phone = document.getElementById('o-phone').value; draft.order.note = document.getElementById('o-note').value; }
  }

  document.getElementById('nav').onclick = function (e) {
    var b = e.target.closest('[data-go]');
    if (b) go(b.getAttribute('data-go'));
  };
  document.getElementById('app').onclick = async function (e) {
    var t = e.target.closest('[data-go],[data-back],[data-filter],[data-pick-product],[data-rm-item],[data-qty],[data-rq],[data-cancel],[data-save-product],[data-del-product],[data-save-user],#o-save,#r-save,#s-save');
    if (!t) return;
    if (t.dataset.go === 'logout') return logout();
    if (t.dataset.go) return go(t.dataset.go);
    if (t.hasAttribute('data-back')) return history.back();
    if (t.dataset.filter) return showFilter(t.dataset.filter);
    if (t.hasAttribute('data-pick-product')) {
      syncOrderDraft();
      if (!cache.products.length) { try { await loadProducts(); } catch (err) { return toast(err.message); } }
      return showProductPicker();
    }
    if (t.dataset.rmItem != null) {
      syncOrderDraft();
      draft.order.items.splice(Number(t.dataset.rmItem), 1);
      return render();
    }
    if (t.dataset.qty != null) {
      syncOrderDraft();
      var it = draft.order.items[Number(t.dataset.qty)];
      it.quantity = Math.max(1, it.quantity + Number(t.dataset.d));
      return render();
    }
    if (t.dataset.rq != null) {
      var id = t.dataset.rq;
      var max = Number(t.dataset.max);
      draft.ret.qty[id] = Math.max(0, Math.min(max, (draft.ret.qty[id] || 0) + Number(t.dataset.d)));
      var reason = document.getElementById('r-reason');
      if (reason) { draft.ret.reason = reason.value; draft.ret.note = document.getElementById('r-note').value; }
      return render();
    }
    if (t.dataset.cancel) {
      if (!confirm('Hủy đơn này và hoàn kho?')) return;
      try {
        await api('/orders/' + t.dataset.cancel + '/cancel', { method: 'POST', body: {} });
        toast('Đã hủy đơn'); loadOrder(t.dataset.cancel); loadHome();
      } catch (err) { toast(err.message); }
      return;
    }
    if (t.id === 'o-save') {
      syncOrderDraft();
      if (!draft.order.items.length) return toast('Chọn ít nhất một sản phẩm');
      t.disabled = true;
      try {
        var created = await api('/orders', { method: 'POST', body: {
          customer_name: draft.order.name || null,
          customer_phone: draft.order.phone || null,
          note: draft.order.note || null,
          items: draft.order.items.map(function (it) {
            return { product_id: it.id, quantity: it.quantity, sale_price: Number(it.sale_price || 0) };
          })
        }});
        draft.order = null;
        toast('Đã tạo đơn hàng');
        go(created.data && created.data.id ? '/orders/' + created.data.id : '/orders');
        loadHome();
      } catch (err) { toast(err.message); t.disabled = false; }
      return;
    }
    if (t.id === 'r-save') {
      var items = Object.keys(draft.ret.qty).filter(function (k) { return draft.ret.qty[k] > 0; }).map(function (k) {
        return { order_item_id: Number(k), quantity: draft.ret.qty[k] };
      });
      if (!items.length) return toast('Chọn số lượng cần trả');
      t.disabled = true;
      try {
        var created = await api('/returns', { method: 'POST', body: {
          order_id: Number(t.dataset.oid),
          reason: document.getElementById('r-reason').value,
          note: document.getElementById('r-note').value || null,
          items: items
        }});
        draft.ret = null;
        toast('Đã tạo phiếu trả hàng');
        go(created.data && created.data.id ? '/returns/' + created.data.id : '/returns');
        loadHome();
      } catch (err) { toast(err.message); t.disabled = false; }
      return;
    }
    if (t.dataset.saveProduct !== undefined) {
      var name = document.getElementById('p-name').value.trim();
      if (!name) return toast('Nhập tên sản phẩm');
      t.disabled = true;
      try {
        var fd = new FormData();
        fd.append('name', name);
        fd.append('listed_price', document.getElementById('p-price').value || '0');
        fd.append('sku', document.getElementById('p-sku').value.trim());
        fd.append('note', document.getElementById('p-note').value.trim());
        fd.append('is_active', document.getElementById('p-active').value);
        var qty = document.getElementById('p-qty');
        if (qty) fd.append('initial_qty', qty.value || '0');
        var img = document.getElementById('p-image').files[0];
        if (img) fd.append('image', img);
        var pid = t.dataset.saveProduct;
        var created = await api(pid ? '/products/' + pid : '/products', { method: 'POST', body: fd });
        toast('Đã lưu sản phẩm');
        go(pid ? '/products/' + pid : (created.data && created.data.id ? '/products/' + created.data.id : '/products'));
      } catch (err) { toast(err.message); t.disabled = false; }
      return;
    }
    if (t.dataset.delProduct) {
      if (!confirm('Xóa hoặc ẩn sản phẩm này?')) return;
      try {
        await api('/products/' + t.dataset.delProduct, { method: 'DELETE' });
        toast('Đã xử lý sản phẩm'); go('/products');
      } catch (err) { toast(err.message); }
      return;
    }
    if (t.id === 's-save') {
      t.disabled = true;
      try {
        var created = await api('/stock-imports', { method: 'POST', body: {
          product_id: Number(document.getElementById('s-p').value),
          quantity: Number(document.getElementById('s-q').value || 1),
          unit_cost: document.getElementById('s-cost') && document.getElementById('s-cost').value ? Number(document.getElementById('s-cost').value) : null,
          note: document.getElementById('s-note').value.trim() || null
        }});
        toast('Đã nhập kho');
        go(created.data && created.data.id ? '/stock/' + created.data.id : '/stock');
      } catch (err) { toast(err.message); t.disabled = false; }
      return;
    }
    if (t.dataset.saveUser !== undefined) {
      t.disabled = true;
      try {
        var body = {
          name: document.getElementById('u-name').value.trim(),
          email: document.getElementById('u-email').value.trim(),
          phone: document.getElementById('u-phone').value.trim() || null,
          role: document.getElementById('u-role').value
        };
        var pass = document.getElementById('u-pass').value;
        var uid = t.dataset.saveUser;
        if (pass) body.password = pass;
        if (!uid && !pass) { t.disabled = false; return toast('Nhập mật khẩu'); }
        var saved = await api(uid ? '/users/' + uid : '/users', { method: uid ? 'PUT' : 'POST', body: body });
        toast('Đã lưu tài khoản');
        go(saved.data && saved.data.id ? '/users/' + saved.data.id : '/users');
      } catch (err) { toast(err.message); t.disabled = false; }
    }
  };
  document.getElementById('app').oninput = function (e) {
    if (e.target.id === 'q-orders') {
      clearTimeout(e.target._t);
      e.target._t = setTimeout(function () { loadOrders(e.target.value.trim()).catch(function (err) { toast(err.message); }); }, 280);
    }
    if (e.target.id === 'q-products') {
      clearTimeout(e.target._t);
      e.target._t = setTimeout(function () { loadProducts(e.target.value.trim()).catch(function (err) { toast(err.message); }); }, 280);
    }
    if (e.target.id === 'q-returns') {
      clearTimeout(e.target._t);
      e.target._t = setTimeout(function () { loadReturns(e.target.value.trim()).catch(function (err) { toast(err.message); }); }, 280);
    }
    if (e.target.dataset.price != null && draft.order) {
      draft.order.items[Number(e.target.dataset.price)].sale_price = Number(e.target.value || 0);
    }
    if (e.target.id === 'p-image' && e.target.files[0]) {
      var url = URL.createObjectURL(e.target.files[0]);
      var box = document.querySelector('.img-pick');
      if (box) box.innerHTML = '<img src="' + url + '" alt="">';
    }
  };
  document.getElementById('sheet').onclick = function (e) {
    var t = e.target.closest('[data-chip],[data-apply-filter],[data-clear-filter],[data-add-p]');
    if (!t) {
      if (e.target.id === 'sheet') closeSheet();
      return;
    }
    if (t.dataset.chip) {
      t.parentNode.querySelectorAll('button').forEach(function (b) { b.classList.remove('on'); });
      t.classList.add('on');
      t.parentNode.setAttribute('data-val', t.dataset.v);
      return;
    }
    if (t.dataset.addP) {
      var p = cache.products.find(function (x) { return String(x.id) === t.dataset.addP; });
      if (!p) return;
      draft.order = draft.order || { name: '', phone: '', note: '', items: [] };
      var ex = draft.order.items.find(function (x) { return x.id === p.id; });
      if (ex) ex.quantity += 1;
      else draft.order.items.push({ id: p.id, name: p.name, listed: p.listed_price, stock: p.stock_qty, sale_price: p.listed_price, quantity: 1 });
      closeSheet();
      return render();
    }
    if (t.dataset.applyFilter) {
      applyFilter(t.dataset.applyFilter, false);
    }
    if (t.dataset.clearFilter) {
      applyFilter(t.dataset.clearFilter, true);
    }
  };
  function applyFilter(key, clear) {
    var f = filters[key];
    if (clear) {
      Object.keys(f).forEach(function (k) { f[k] = ''; });
    } else {
      var st = document.querySelector('[data-chip="f-st"]') && document.querySelector('#sheet .chips [data-chip="f-st"].on');
      var ac = document.querySelector('#sheet .chips [data-chip="f-ac"].on');
      var sk = document.querySelector('#sheet .chips [data-chip="f-sk"].on');
      var from = document.getElementById('f-from');
      var to = document.getElementById('f-to');
      if (st && f.status !== undefined) f.status = st.getAttribute('data-v');
      if (ac && f.active !== undefined) f.active = ac.getAttribute('data-v');
      if (sk && f.stock !== undefined) f.stock = sk.getAttribute('data-v');
      if (from && f.from !== undefined) f.from = from.value;
      if (to && f.to !== undefined) f.to = to.value;
    }
    closeSheet();
    if (key === 'orders') loadOrders(document.getElementById('q-orders') && document.getElementById('q-orders').value).catch(function (e) { toast(e.message); });
    if (key === 'products') loadProducts(document.getElementById('q-products') && document.getElementById('q-products').value).catch(function (e) { toast(e.message); });
    if (key === 'stock') loadStock().catch(function (e) { toast(e.message); });
    if (key === 'returns') loadReturns(document.getElementById('q-returns') && document.getElementById('q-returns').value).catch(function (e) { toast(e.message); });
    render();
  }

  window.addEventListener('hashchange', function () {
    render();
    bootPage(route());
  });
  if (!location.hash || location.hash === '#') location.hash = '/';
  else {
    render();
    bootPage(route());
  }
  loadProducts().catch(function () {});
})();
