import 'package:flutter/material.dart';
import '../models/company.dart';
import '../services/api_service.dart';

class CompanyProvider extends ChangeNotifier {
  List<Company> _companies = [];
  bool _isLoading = false;
  String? _error;
  
  List<Company> get companies => _companies;
  bool get isLoading => _isLoading;
  String? get error => _error;
  
  final ApiService _api = ApiService.instance;
  
  // Carica lista aziende
  Future<void> loadCompanies() async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    
    try {
      final response = await _api.get('/mobile-companies-api.php', params: {
        'action': 'list'
      });
      
      if (response['success'] == true && response['data'] != null) {
        _companies = (response['data'] as List)
            .map((json) => Company.fromJson(json))
            .toList();
      } else {
        _companies = [];
      }
    } catch (e) {
      _error = e.toString();
      _companies = [];
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
  
  // Carica dettagli azienda
  Future<Map<String, dynamic>?> getCompanyDetails(int companyId) async {
    try {
      final response = await _api.get('/mobile-companies-api.php', params: {
        'action': 'detail',
        'id': companyId.toString()
      });
      
      if (response['success'] == true) {
        return response['data'];
      }
      return null;
    } catch (e) {
      print('Errore caricamento dettagli azienda: $e');
      return null;
    }
  }
  
  // Crea nuova azienda
  Future<bool> createCompany(Map<String, dynamic> data) async {
    try {
      final response = await _api.post('/mobile-companies-api.php', data: {
        'action': 'create',
        ...data
      });
      
      if (response['success'] == true) {
        await loadCompanies(); // Ricarica lista
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }
  
  // Aggiorna azienda
  Future<bool> updateCompany(int companyId, Map<String, dynamic> data) async {
    try {
      final response = await _api.post('/mobile-companies-api.php', data: {
        'action': 'update',
        'id': companyId,
        ...data
      });
      
      if (response['success'] == true) {
        await loadCompanies(); // Ricarica lista
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }
  
  // Cambia stato azienda
  Future<bool> toggleCompanyStatus(int companyId) async {
    try {
      final response = await _api.post('/mobile-companies-api.php', data: {
        'action': 'toggle-status',
        'id': companyId
      });
      
      if (response['success'] == true) {
        await loadCompanies(); // Ricarica lista
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }
  
  // Pulisci errore
  void clearError() {
    _error = null;
    notifyListeners();
  }
}