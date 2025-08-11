import 'package:flutter/material.dart';
// import 'package:file_picker/file_picker.dart';
import '../../config/theme.dart';

class DocumentsScreen extends StatefulWidget {
  const DocumentsScreen({super.key});

  @override
  State<DocumentsScreen> createState() => _DocumentsScreenState();
}

class _DocumentsScreenState extends State<DocumentsScreen> {
  String _searchQuery = '';
  String _selectedFolder = 'root';
  
  final List<Map<String, dynamic>> _documents = [
    {'name': 'Contratto 2025.pdf', 'size': '2.3 MB', 'date': '15/01/2025', 'type': 'pdf'},
    {'name': 'Fattura_001.pdf', 'size': '456 KB', 'date': '14/01/2025', 'type': 'pdf'},
    {'name': 'Report Mensile.docx', 'size': '1.2 MB', 'date': '13/01/2025', 'type': 'doc'},
    {'name': 'Budget.xlsx', 'size': '3.5 MB', 'date': '12/01/2025', 'type': 'xls'},
    {'name': 'Presentazione.pptx', 'size': '5.8 MB', 'date': '11/01/2025', 'type': 'ppt'},
  ];

  Future<void> _uploadFile() async {
    // FilePickerResult? result = await FilePicker.platform.pickFiles(
    //   allowMultiple: true,
    // );

    // if (result != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Upload file temporaneamente disabilitato'),
          backgroundColor: AppTheme.warning,
        ),
      );
    // }
  }

  IconData _getFileIcon(String type) {
    switch (type) {
      case 'pdf':
        return Icons.picture_as_pdf;
      case 'doc':
        return Icons.description;
      case 'xls':
        return Icons.table_chart;
      case 'ppt':
        return Icons.slideshow;
      default:
        return Icons.insert_drive_file;
    }
  }

  Color _getFileColor(String type) {
    switch (type) {
      case 'pdf':
        return Colors.red;
      case 'doc':
        return Colors.blue;
      case 'xls':
        return Colors.green;
      case 'ppt':
        return Colors.orange;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final filteredDocs = _documents.where((doc) {
      return doc['name'].toLowerCase().contains(_searchQuery.toLowerCase());
    }).toList();

    return Scaffold(
      body: Row(
        children: [
          // Sidebar cartelle
          Container(
            width: 250,
            decoration: BoxDecoration(
              color: Colors.grey[50],
              border: Border(
                right: BorderSide(color: AppTheme.borderLight),
              ),
            ),
            child: Column(
              children: [
                // Header
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border(
                      bottom: BorderSide(color: AppTheme.borderLight),
                    ),
                  ),
                  child: const Text(
                    'Cartelle',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                // Lista cartelle
                Expanded(
                  child: ListView(
                    padding: const EdgeInsets.all(8),
                    children: [
                      _buildFolderItem('Documenti', Icons.folder, true),
                      _buildFolderItem('Contratti', Icons.folder_outlined, false),
                      _buildFolderItem('Fatture', Icons.folder_outlined, false),
                      _buildFolderItem('Report', Icons.folder_outlined, false),
                      _buildFolderItem('Archivio', Icons.folder_outlined, false),
                    ],
                  ),
                ),
                // Bottone nuova cartella
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: OutlinedButton.icon(
                    onPressed: () {
                      // Crea nuova cartella
                    },
                    icon: const Icon(Icons.create_new_folder),
                    label: const Text('Nuova Cartella'),
                  ),
                ),
              ],
            ),
          ),
          // Area principale
          Expanded(
            child: Column(
              children: [
                // Toolbar
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border(
                      bottom: BorderSide(color: AppTheme.borderLight),
                    ),
                  ),
                  child: Row(
                    children: [
                      // Ricerca
                      Expanded(
                        child: TextField(
                          decoration: InputDecoration(
                            hintText: 'Cerca documenti...',
                            prefixIcon: const Icon(Icons.search),
                            border: OutlineInputBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                            contentPadding: const EdgeInsets.symmetric(horizontal: 16),
                          ),
                          onChanged: (value) {
                            setState(() {
                              _searchQuery = value;
                            });
                          },
                        ),
                      ),
                      const SizedBox(width: 16),
                      // Bottoni azione
                      ElevatedButton.icon(
                        onPressed: _uploadFile,
                        icon: const Icon(Icons.upload),
                        label: const Text('Carica'),
                      ),
                    ],
                  ),
                ),
                // Lista documenti
                Expanded(
                  child: filteredDocs.isEmpty
                      ? Center(
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.folder_open, size: 80, color: Colors.grey[400]),
                              const SizedBox(height: 16),
                              Text(
                                'Nessun documento trovato',
                                style: TextStyle(fontSize: 18, color: Colors.grey[600]),
                              ),
                            ],
                          ),
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.all(16),
                          itemCount: filteredDocs.length,
                          itemBuilder: (context, index) {
                            final doc = filteredDocs[index];
                            return Card(
                              margin: const EdgeInsets.only(bottom: 8),
                              child: ListTile(
                                leading: Icon(
                                  _getFileIcon(doc['type']),
                                  color: _getFileColor(doc['type']),
                                  size: 32,
                                ),
                                title: Text(doc['name']),
                                subtitle: Text('${doc['size']} • ${doc['date']}'),
                                trailing: PopupMenuButton<String>(
                                  onSelected: (value) {
                                    // Gestisci azioni
                                  },
                                  itemBuilder: (context) => [
                                    const PopupMenuItem(
                                      value: 'download',
                                      child: Row(
                                        children: [
                                          Icon(Icons.download, size: 20),
                                          SizedBox(width: 8),
                                          Text('Scarica'),
                                        ],
                                      ),
                                    ),
                                    const PopupMenuItem(
                                      value: 'share',
                                      child: Row(
                                        children: [
                                          Icon(Icons.share, size: 20),
                                          SizedBox(width: 8),
                                          Text('Condividi'),
                                        ],
                                      ),
                                    ),
                                    const PopupMenuItem(
                                      value: 'delete',
                                      child: Row(
                                        children: [
                                          Icon(Icons.delete, size: 20, color: Colors.red),
                                          SizedBox(width: 8),
                                          Text('Elimina', style: TextStyle(color: Colors.red)),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                                onTap: () {
                                  // Apri documento
                                },
                              ),
                            );
                          },
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _uploadFile,
        backgroundColor: AppTheme.primaryColor,
        child: const Icon(Icons.add),
      ),
    );
  }

  Widget _buildFolderItem(String name, IconData icon, bool isSelected) {
    return ListTile(
      leading: Icon(icon, color: isSelected ? AppTheme.primaryColor : Colors.grey),
      title: Text(
        name,
        style: TextStyle(
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
          color: isSelected ? AppTheme.primaryColor : null,
        ),
      ),
      selected: isSelected,
      selectedTileColor: AppTheme.primaryColor.withOpacity(0.1),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
      ),
      onTap: () {
        setState(() {
          _selectedFolder = name.toLowerCase();
        });
      },
    );
  }
}
