import 'package:flutter/material.dart';
import 'dart:io';
import '../models/document.dart';
import '../models/folder.dart';
import '../services/api_service.dart';

class DocumentProvider extends ChangeNotifier {
  final ApiService _api = ApiService.instance;
  
  List<Document> _documents = [];
  List<Folder> _folders = [];
  Folder? _currentFolder;
  bool _isLoading = false;
  String? _error;

  List<Document> get documents => _documents;
  List<Folder> get folders => _folders;
  Folder? get currentFolder => _currentFolder;
  bool get isLoading => _isLoading;
  String? get error => _error;

  Future<void> loadDocuments({int? folderId}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.get('/filesystem-simple-api.php', params: {
        'action': 'list',
        'folder_id': folderId?.toString() ?? 'null',
      });

      if (response != null && response['success'] == true) {
        _documents = (response['files'] as List? ?? [])
            .map((d) => Document.fromJson(d))
            .toList();
        
        _folders = (response['folders'] as List? ?? [])
            .map((f) => Folder.fromJson(f))
            .toList();
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadFolders() async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _api.get('/folders-api.php', params: {
        'action': 'list',
      });

      if (response != null && response['success'] == true) {
        _folders = (response['folders'] as List? ?? [])
            .map((f) => Folder.fromJson(f))
            .toList();
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> createFolder(String name, int? parentId) async {
    try {
      final response = await _api.post('/folders-api.php', data: {
        'action': 'create',
        'nome': name,
        'parent_id': parentId,
      });

      if (response != null && response['success'] == true) {
        await loadDocuments(folderId: parentId);
        return true;
      }
      return false;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return false;
    }
  }

  Future<bool> deleteDocument(int documentId) async {
    try {
      final response = await _api.post('/delete-document.php', data: {
        'document_id': documentId,
      });

      if (response != null && response['success'] == true) {
        _documents.removeWhere((d) => d.id == documentId);
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

  Future<String?> downloadDocument(int documentId, String savePath) async {
    try {
      final url = '${ApiService.instance.baseUrl}/backend/api/download-file.php?id=$documentId';
      await _api.downloadFile(url, savePath);
      return savePath;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return null;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}