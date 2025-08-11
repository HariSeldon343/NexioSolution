class AppConstants {
  // API Configuration
  static const String baseUrl = 'https://app.nexiosolution.it/piattaforma-collaborativa';
  static const String apiUrl = '$baseUrl/backend/api';
  
  // App Info
  static const String appName = 'Nexio Platform';
  static const String appVersion = '1.0.0';
  
  // Storage Keys
  static const String tokenKey = 'auth_token';
  static const String userKey = 'user_data';
  static const String companyKey = 'company_data';
  static const String languageKey = 'app_language';
  
  // Timeouts
  static const Duration connectionTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);
  
  // Pagination
  static const int pageSize = 20;
  
  // File limits
  static const int maxFileSize = 10 * 1024 * 1024; // 10MB
  static const List<String> allowedExtensions = [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 
    'ppt', 'pptx', 'txt', 'csv', 'jpg', 
    'jpeg', 'png', 'gif', 'zip', 'rar'
  ];
}