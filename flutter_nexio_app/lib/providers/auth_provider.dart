import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'dart:convert';
import '../models/user.dart';
import '../models/company.dart';
import '../services/api_service.dart';
import '../config/constants.dart';

class AuthProvider extends ChangeNotifier {
  User? _user;
  Company? _currentCompany;
  List<Company> _companies = [];
  bool _isAuthenticated = false;
  bool _isLoading = false;
  String? _error;

  User? get user => _user;
  Company? get currentCompany => _currentCompany;
  List<Company> get companies => _companies;
  bool get isAuthenticated => _isAuthenticated;
  bool get isLoading => _isLoading;
  String? get error => _error;
  
  bool get isSuperAdmin => _user?.isSuperAdmin ?? false;
  bool get hasElevatedPrivileges => _user?.hasElevatedPrivileges ?? false;

  final ApiService _api = ApiService.instance;

  // Costruttore
  AuthProvider() {
    checkAuthStatus();
  }

  // Controlla lo stato di autenticazione all'avvio
  Future<void> checkAuthStatus() async {
    _isLoading = true;
    notifyListeners();

    try {
      final prefs = await SharedPreferences.getInstance();
      final userJson = prefs.getString(AppConstants.userKey);
      
      if (userJson != null) {
        _user = User.fromJson(json.decode(userJson));
        _isAuthenticated = true;
        
        // Carica azienda corrente
        final companyJson = prefs.getString(AppConstants.companyKey);
        if (companyJson != null) {
          _currentCompany = Company.fromJson(json.decode(companyJson));
        }
        
        // Carica lista aziende se necessario
        await loadUserCompanies();
      }
    } catch (e) {
      print('Errore controllo autenticazione: $e');
      _isAuthenticated = false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // Login
  Future<bool> login(String username, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.post('/mobile-auth-api.php', data: {
        'action': 'login',
        'username': username,
        'password': password,
      });

      if (response['success'] == true) {
        _user = User.fromJson(response['user']);
        _isAuthenticated = true;
        
        // Salva dati utente
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(AppConstants.userKey, json.encode(_user!.toJson()));
        
        // Salva token se presente
        if (response['token'] != null) {
          await prefs.setString(AppConstants.tokenKey, response['token']);
        }
        
        // Salva CSRF token se presente
        if (response['csrf_token'] != null) {
          await prefs.setString('csrf_token', response['csrf_token']);
        }
        
        // Carica aziende dell'utente
        await loadUserCompanies();
        
        // Se c'è un'azienda, seleziona la prima
        if (_companies.isNotEmpty) {
          await selectCompany(_companies.first);
        }
        
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = response['message'] ?? 'Credenziali non valide';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  // Carica aziende dell'utente
  Future<void> loadUserCompanies() async {
    try {
      final response = await _api.get('/get-user-companies.php');
      
      if (response['success'] == true) {
        _companies = (response['companies'] as List)
            .map((c) => Company.fromJson(c))
            .toList();
        notifyListeners();
      }
    } catch (e) {
      print('Errore caricamento aziende: $e');
    }
  }

  // Seleziona azienda
  Future<void> selectCompany(Company company) async {
    try {
      final response = await _api.post('/switch-azienda.php', data: {
        'azienda_id': company.id,
      });
      
      if (response['success'] == true) {
        _currentCompany = company;
        
        // Salva azienda corrente
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(AppConstants.companyKey, json.encode(company.toJson()));
        
        notifyListeners();
      }
    } catch (e) {
      print('Errore cambio azienda: $e');
    }
  }

  // Logout
  Future<void> logout() async {
    _isLoading = true;
    notifyListeners();

    try {
      // Chiamata API di logout
      await _api.post('/logout.php');
    } catch (e) {
      print('Errore logout: $e');
    }

    // Pulisci dati locali
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(AppConstants.userKey);
    await prefs.remove(AppConstants.tokenKey);
    await prefs.remove(AppConstants.companyKey);
    await prefs.remove('csrf_token');

    _user = null;
    _currentCompany = null;
    _companies = [];
    _isAuthenticated = false;
    _isLoading = false;
    _error = null;
    
    notifyListeners();
  }

  // Aggiorna profilo utente
  Future<bool> updateProfile(Map<String, dynamic> data) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.put('/update-profile.php', data: data);
      
      if (response['success'] == true) {
        _user = User.fromJson(response['user']);
        
        // Aggiorna dati salvati
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(AppConstants.userKey, json.encode(_user!.toJson()));
        
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = response['message'] ?? 'Errore aggiornamento profilo';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  // Cambia password
  Future<bool> changePassword(String oldPassword, String newPassword) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.post('/change-password.php', data: {
        'old_password': oldPassword,
        'new_password': newPassword,
      });
      
      if (response['success'] == true) {
        _isLoading = false;
        notifyListeners();
        return true;
      } else {
        _error = response['message'] ?? 'Errore cambio password';
        _isLoading = false;
        notifyListeners();
        return false;
      }
    } catch (e) {
      _error = e.toString();
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  // Clear error
  void clearError() {
    _error = null;
    notifyListeners();
  }
}