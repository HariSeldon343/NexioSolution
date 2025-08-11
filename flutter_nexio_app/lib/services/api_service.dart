import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/constants.dart';

class ApiService {
  late Dio _dio;
  static ApiService? _instance;
  
  String get baseUrl => AppConstants.baseUrl;
  
  ApiService._() {
    _dio = Dio(BaseOptions(
      baseUrl: AppConstants.apiUrl,
      connectTimeout: AppConstants.connectionTimeout,
      receiveTimeout: AppConstants.receiveTimeout,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    ));
    
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        // Aggiungi token di autenticazione se presente
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString(AppConstants.tokenKey);
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        
        // Aggiungi CSRF token se necessario
        final csrfToken = prefs.getString('csrf_token');
        if (csrfToken != null) {
          options.headers['X-CSRF-Token'] = csrfToken;
        }
        
        handler.next(options);
      },
      onError: (error, handler) {
        print('API Error: ${error.message}');
        handler.next(error);
      },
    ));
  }
  
  static ApiService get instance {
    _instance ??= ApiService._();
    return _instance!;
  }
  
  // GET request
  Future<dynamic> get(String endpoint, {Map<String, dynamic>? params}) async {
    try {
      final response = await _dio.get(endpoint, queryParameters: params);
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }
  
  // POST request
  Future<dynamic> post(String endpoint, {dynamic data}) async {
    try {
      final response = await _dio.post(endpoint, data: data);
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }
  
  // PUT request
  Future<dynamic> put(String endpoint, {dynamic data}) async {
    try {
      final response = await _dio.put(endpoint, data: data);
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }
  
  // DELETE request
  Future<dynamic> delete(String endpoint) async {
    try {
      final response = await _dio.delete(endpoint);
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }
  
  // Upload file
  Future<dynamic> uploadFile(String endpoint, File file, {Map<String, dynamic>? data}) async {
    try {
      String fileName = file.path.split('/').last;
      FormData formData = FormData.fromMap({
        'file': await MultipartFile.fromFile(file.path, filename: fileName),
        ...?data,
      });
      
      final response = await _dio.post(
        endpoint,
        data: formData,
        options: Options(
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        ),
      );
      
      return response.data;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }
  
  // Download file
  Future<void> downloadFile(String url, String savePath) async {
    try {
      await _dio.download(url, savePath);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }
  
  // Error handling
  String _handleError(DioException error) {
    String errorMessage = 'Si è verificato un errore';
    
    if (error.type == DioExceptionType.connectionTimeout) {
      errorMessage = 'Timeout di connessione';
    } else if (error.type == DioExceptionType.sendTimeout) {
      errorMessage = 'Timeout invio richiesta';
    } else if (error.type == DioExceptionType.receiveTimeout) {
      errorMessage = 'Timeout ricezione risposta';
    } else if (error.type == DioExceptionType.badResponse) {
      switch (error.response?.statusCode) {
        case 400:
          errorMessage = 'Richiesta non valida';
          break;
        case 401:
          errorMessage = 'Non autorizzato';
          break;
        case 403:
          errorMessage = 'Accesso negato';
          break;
        case 404:
          errorMessage = 'Risorsa non trovata';
          break;
        case 500:
          errorMessage = 'Errore del server';
          break;
        default:
          errorMessage = error.response?.data['error'] ?? 
                        error.response?.data['message'] ?? 
                        'Errore sconosciuto';
      }
    } else if (error.type == DioExceptionType.cancel) {
      errorMessage = 'Richiesta annullata';
    } else if (error.type == DioExceptionType.unknown) {
      if (error.error is SocketException) {
        errorMessage = 'Nessuna connessione internet';
      } else {
        errorMessage = 'Errore di connessione';
      }
    }
    
    return errorMessage;
  }
}