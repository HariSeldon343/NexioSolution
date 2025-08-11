import 'package:flutter/material.dart';
import '../models/event.dart';
import '../services/api_service.dart';

class EventProvider extends ChangeNotifier {
  final ApiService _api = ApiService.instance;
  
  List<Event> _events = [];
  Map<DateTime, List<Event>> _eventsByDate = {};
  bool _isLoading = false;
  String? _error;

  List<Event> get events => _events;
  Map<DateTime, List<Event>> get eventsByDate => _eventsByDate;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> loadEvents({DateTime? startDate, DateTime? endDate}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final params = <String, dynamic>{
        'action': 'list',
      };
      
      if (startDate != null) {
        params['start_date'] = startDate.toIso8601String().split('T')[0];
      }
      if (endDate != null) {
        params['end_date'] = endDate.toIso8601String().split('T')[0];
      }

      final response = await _api.get('/mobile-events-api.php', params: params);

      if (response != null && response['success'] == true) {
        _events = (response['events'] as List? ?? [])
            .map((e) => Event.fromJson(e))
            .toList();
        
        // Organizza eventi per data
        _eventsByDate = {};
        for (var event in _events) {
          final date = DateTime(
            event.dataInizio.year,
            event.dataInizio.month,
            event.dataInizio.day,
          );
          if (!_eventsByDate.containsKey(date)) {
            _eventsByDate[date] = [];
          }
          _eventsByDate[date]!.add(event);
        }
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> createEvent(Map<String, dynamic> eventData) async {
    try {
      final response = await _api.post('/mobile-events-api.php', data: {
        'action': 'create',
        ...eventData,
      });

      if (response != null && response['success'] == true) {
        await loadEvents();
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> updateEvent(int eventId, Map<String, dynamic> eventData) async {
    try {
      final response = await _api.post('/mobile-events-api.php', data: {
        'action': 'update',
        'event_id': eventId,
        ...eventData,
      });

      if (response != null && response['success'] == true) {
        await loadEvents();
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> deleteEvent(int eventId) async {
    try {
      final response = await _api.post('/mobile-events-api.php', data: {
        'action': 'delete',
        'event_id': eventId,
      });

      if (response != null && response['success'] == true) {
        _events.removeWhere((e) => e.id == eventId);
        notifyListeners();
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  List<Event> getEventsForDay(DateTime day) {
    final date = DateTime(day.year, day.month, day.day);
    return _eventsByDate[date] ?? [];
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}