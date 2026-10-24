import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';
import '../models/models.dart';
import 'auth_session.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});
  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient._();
  static final ApiClient instance = ApiClient._();

  final http.Client _client = http.Client();

  Map<String, String> get _headers {
    final h = <String, String>{'Accept': 'application/json'};
    final token = AuthSession.instance.token;
    if (token != null && token.isNotEmpty) {
      h['Authorization'] = 'Bearer $token';
    }
    return h;
  }

  Future<Map<String, dynamic>> get(
    String path, {
    Map<String, String>? query,
  }) async {
    final uri = ApiConfig.uri(path).replace(queryParameters: query);
    final res = await _client.get(uri, headers: _headers).timeout(
      const Duration(seconds: 30),
    );
    return _decode(res);
  }

  Future<Map<String, dynamic>> postJson(
    String path,
    Map<String, dynamic> body,
  ) async {
    final res = await _client
        .post(
          ApiConfig.uri(path),
          headers: {..._headers, 'Content-Type': 'application/json'},
          body: jsonEncode(body),
        )
        .timeout(const Duration(seconds: 30));
    return _decode(res);
  }

  Future<Map<String, dynamic>> putJson(
    String path,
    Map<String, dynamic> body,
  ) async {
    final res = await _client
        .put(
          ApiConfig.uri(path),
          headers: {..._headers, 'Content-Type': 'application/json'},
          body: jsonEncode(body),
        )
        .timeout(const Duration(seconds: 30));
    return _decode(res);
  }

  Future<Map<String, dynamic>> delete(String path) async {
    final res = await _client
        .delete(ApiConfig.uri(path), headers: _headers)
        .timeout(const Duration(seconds: 30));
    return _decode(res);
  }

  Future<Map<String, dynamic>> postMultipart(
    String path, {
    required Map<String, String> fields,
    List<int>? fileBytes,
    String fileField = 'image',
    String filename = 'image.jpg',
  }) async {
    final req = http.MultipartRequest('POST', ApiConfig.uri(path));
    req.headers.addAll(_headers);
    req.fields.addAll(fields);
    if (fileBytes != null) {
      req.files.add(
        http.MultipartFile.fromBytes(
          fileField,
          fileBytes,
          filename: filename,
        ),
      );
    }
    final streamed = await _client.send(req).timeout(const Duration(seconds: 60));
    final res = await http.Response.fromStream(streamed);
    return _decode(res);
  }

  Map<String, dynamic> _decode(http.Response res) {
    Map<String, dynamic> json;
    try {
      final body = res.body.isEmpty ? '{}' : res.body;
      final decoded = jsonDecode(body);
      json = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{'data': decoded};
    } catch (_) {
      throw ApiException(
        'Server trả về dữ liệu không hợp lệ (${res.statusCode})',
        statusCode: res.statusCode,
      );
    }

    if (res.statusCode >= 200 && res.statusCode < 300) {
      if (json['success'] == false) {
        throw ApiException(
          (json['message'] ?? 'Yêu cầu thất bại').toString(),
          statusCode: res.statusCode,
        );
      }
      return json;
    }

    throw ApiException(
      (json['message'] ?? 'Lỗi ${res.statusCode}').toString(),
      statusCode: res.statusCode,
    );
  }

  Paged<T> paged<T>(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) map,
  ) {
    final raw = json['data'];
    final items = raw is List
        ? raw
            .whereType<Map>()
            .map((e) => map(Map<String, dynamic>.from(e)))
            .toList()
        : <T>[];
    final meta = json['meta'] is Map
        ? Map<String, dynamic>.from(json['meta'] as Map)
        : const <String, dynamic>{};
    return Paged(
      items: items,
      page: asInt(meta['current_page'] ?? 1),
      lastPage: asInt(meta['last_page'] ?? 1),
      total: asInt(meta['total'] ?? items.length),
    );
  }
}
