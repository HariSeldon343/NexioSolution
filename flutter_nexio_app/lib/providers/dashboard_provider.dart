import 'package:flutter/material.dart';
import '../services/api_service.dart';

class DashboardProvider extends ChangeNotifier {
  final ApiService _api = ApiService.instance;
  
  Map<String, dynamic> _stats = {};
  List<dynamic> _recentActivities = [];
  List<dynamic> _upcomingEvents = [];
  List<dynamic> _tasks = [];
  bool _isLoading = false;
  String? _error;

  Map<String, dynamic> get stats => _stats;
  List<dynamic> get recentActivities => _recentActivities;
  List<dynamic> get upcomingEvents => _upcomingEvents;
  List<dynamic> get tasks => _tasks;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> loadDashboardData() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      // Carica statistiche dashboard
      final response = await _api.get('/mobile-api.php', params: {
        'action': 'dashboard',
      });

      if (response != null && response['success'] == true) {
        _stats = response['stats'] ?? {};
        _recentActivities = response['activities'] ?? [];
        _upcomingEvents = response['events'] ?? [];
        _tasks = response['tasks'] ?? [];
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void clearData() {
    _stats = {};
    _recentActivities = [];
    _upcomingEvents = [];
    _tasks = [];
    _error = null;
    notifyListeners();
  }
}