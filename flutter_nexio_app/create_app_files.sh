#!/bin/bash

# Crea le directory necessarie
mkdir -p lib/screens/{documents,calendar,companies,users,profile}
mkdir -p lib/widgets
mkdir -p lib/providers

# Crea StatCard widget
cat > lib/widgets/stat_card.dart << 'EOF'
import 'package:flutter/material.dart';

class StatCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;
  final String? trend;

  const StatCard({
    super.key,
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
    this.trend,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Icon(icon, color: color, size: 24),
                if (trend != null)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: trend!.startsWith('+') ? Colors.green.withOpacity(0.1) : Colors.red.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(
                      trend!,
                      style: TextStyle(
                        fontSize: 12,
                        color: trend!.startsWith('+') ? Colors.green : Colors.red,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
              ],
            ),
            const Spacer(),
            Text(
              value,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              title,
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
EOF

# Crea Documents Screen
cat > lib/screens/documents/documents_screen.dart << 'EOF'
import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';
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
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      allowMultiple: true,
    );

    if (result != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${result.files.length} file selezionati per il caricamento'),
          backgroundColor: AppTheme.success,
        ),
      );
    }
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
EOF

# Crea Calendar Screen
cat > lib/screens/calendar/calendar_screen.dart << 'EOF'
import 'package:flutter/material.dart';
import 'package:table_calendar/table_calendar.dart';
import 'package:intl/intl.dart';
import '../../config/theme.dart';

class CalendarScreen extends StatefulWidget {
  const CalendarScreen({super.key});

  @override
  State<CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends State<CalendarScreen> {
  CalendarFormat _calendarFormat = CalendarFormat.month;
  DateTime _focusedDay = DateTime.now();
  DateTime? _selectedDay;
  
  final Map<DateTime, List<Map<String, dynamic>>> _events = {
    DateTime.now(): [
      {'title': 'Riunione Team', 'time': '10:00', 'type': 'meeting'},
      {'title': 'Scadenza Documento', 'time': '15:00', 'type': 'deadline'},
    ],
    DateTime.now().add(const Duration(days: 2)): [
      {'title': 'Presentazione', 'time': '14:00', 'type': 'presentation'},
    ],
  };

  List<Map<String, dynamic>> _getEventsForDay(DateTime day) {
    return _events[DateTime(day.year, day.month, day.day)] ?? [];
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Column(
        children: [
          // Calendario
          Card(
            margin: const EdgeInsets.all(16),
            child: TableCalendar(
              firstDay: DateTime.utc(2020, 1, 1),
              lastDay: DateTime.utc(2030, 12, 31),
              focusedDay: _focusedDay,
              calendarFormat: _calendarFormat,
              locale: 'it_IT',
              selectedDayPredicate: (day) {
                return isSameDay(_selectedDay, day);
              },
              eventLoader: _getEventsForDay,
              startingDayOfWeek: StartingDayOfWeek.monday,
              calendarStyle: CalendarStyle(
                outsideDaysVisible: false,
                weekendTextStyle: const TextStyle(color: Colors.red),
                selectedDecoration: BoxDecoration(
                  color: AppTheme.primaryColor,
                  shape: BoxShape.circle,
                ),
                todayDecoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.5),
                  shape: BoxShape.circle,
                ),
                markerDecoration: BoxDecoration(
                  color: AppTheme.warning,
                  shape: BoxShape.circle,
                ),
              ),
              headerStyle: HeaderStyle(
                formatButtonVisible: true,
                titleCentered: true,
                formatButtonShowsNext: false,
                formatButtonDecoration: BoxDecoration(
                  color: AppTheme.primaryColor.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              onDaySelected: (selectedDay, focusedDay) {
                setState(() {
                  _selectedDay = selectedDay;
                  _focusedDay = focusedDay;
                });
              },
              onFormatChanged: (format) {
                setState(() {
                  _calendarFormat = format;
                });
              },
              onPageChanged: (focusedDay) {
                _focusedDay = focusedDay;
              },
            ),
          ),
          // Lista eventi
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _selectedDay != null
                        ? 'Eventi del ${DateFormat('d MMMM yyyy', 'it_IT').format(_selectedDay!)}'
                        : 'Seleziona un giorno',
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Expanded(
                    child: _selectedDay != null && _getEventsForDay(_selectedDay!).isNotEmpty
                        ? ListView.builder(
                            itemCount: _getEventsForDay(_selectedDay!).length,
                            itemBuilder: (context, index) {
                              final event = _getEventsForDay(_selectedDay!)[index];
                              return Card(
                                margin: const EdgeInsets.only(bottom: 8),
                                child: ListTile(
                                  leading: CircleAvatar(
                                    backgroundColor: _getEventColor(event['type']).withOpacity(0.2),
                                    child: Icon(
                                      _getEventIcon(event['type']),
                                      color: _getEventColor(event['type']),
                                    ),
                                  ),
                                  title: Text(event['title']),
                                  subtitle: Text('Ore ${event['time']}'),
                                  trailing: IconButton(
                                    icon: const Icon(Icons.more_vert),
                                    onPressed: () {},
                                  ),
                                ),
                              );
                            },
                          )
                        : Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(Icons.event_available, size: 64, color: Colors.grey[400]),
                                const SizedBox(height: 16),
                                Text(
                                  'Nessun evento',
                                  style: TextStyle(fontSize: 16, color: Colors.grey[600]),
                                ),
                              ],
                            ),
                          ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {
          _showAddEventDialog();
        },
        backgroundColor: AppTheme.primaryColor,
        child: const Icon(Icons.add),
      ),
    );
  }

  IconData _getEventIcon(String type) {
    switch (type) {
      case 'meeting':
        return Icons.groups;
      case 'deadline':
        return Icons.alarm;
      case 'presentation':
        return Icons.slideshow;
      default:
        return Icons.event;
    }
  }

  Color _getEventColor(String type) {
    switch (type) {
      case 'meeting':
        return AppTheme.info;
      case 'deadline':
        return AppTheme.danger;
      case 'presentation':
        return AppTheme.success;
      default:
        return AppTheme.primaryColor;
    }
  }

  void _showAddEventDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Nuovo Evento'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              decoration: const InputDecoration(
                labelText: 'Titolo',
                prefixIcon: Icon(Icons.title),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              decoration: const InputDecoration(
                labelText: 'Descrizione',
                prefixIcon: Icon(Icons.description),
              ),
              maxLines: 3,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Annulla'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Evento creato con successo'),
                  backgroundColor: AppTheme.success,
                ),
              );
            },
            child: const Text('Crea'),
          ),
        ],
      ),
    );
  }
}
EOF

# Crea Companies Screen
cat > lib/screens/companies/companies_screen.dart << 'EOF'
import 'package:flutter/material.dart';
import '../../config/theme.dart';

class CompaniesScreen extends StatelessWidget {
  const CompaniesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: 5,
        itemBuilder: (context, index) {
          return Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                child: Icon(Icons.business, color: AppTheme.primaryColor),
              ),
              title: Text('Azienda ${index + 1}'),
              subtitle: Text('P.IVA: 0123456789${index}'),
              trailing: Chip(
                label: const Text('Attiva'),
                backgroundColor: AppTheme.success.withOpacity(0.1),
                labelStyle: TextStyle(color: AppTheme.success),
              ),
              onTap: () {},
            ),
          );
        },
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {},
        backgroundColor: AppTheme.primaryColor,
        child: const Icon(Icons.add),
      ),
    );
  }
}
EOF

# Crea Users Screen
cat > lib/screens/users/users_screen.dart << 'EOF'
import 'package:flutter/material.dart';
import '../../config/theme.dart';

class UsersScreen extends StatelessWidget {
  const UsersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: 10,
        itemBuilder: (context, index) {
          return Card(
            margin: const EdgeInsets.only(bottom: 8),
            child: ListTile(
              leading: CircleAvatar(
                backgroundColor: AppTheme.primaryColor,
                child: Text(
                  'U${index + 1}',
                  style: const TextStyle(color: Colors.white),
                ),
              ),
              title: Text('Utente ${index + 1}'),
              subtitle: Text('utente${index + 1}@nexio.com'),
              trailing: Chip(
                label: Text(index == 0 ? 'Admin' : 'Utente'),
                backgroundColor: index == 0 
                    ? AppTheme.danger.withOpacity(0.1)
                    : AppTheme.info.withOpacity(0.1),
                labelStyle: TextStyle(
                  color: index == 0 ? AppTheme.danger : AppTheme.info,
                ),
              ),
              onTap: () {},
            ),
          );
        },
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () {},
        backgroundColor: AppTheme.primaryColor,
        child: const Icon(Icons.person_add),
      ),
    );
  }
}
EOF

# Crea Profile Screen
cat > lib/screens/profile/profile_screen.dart << 'EOF'
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import '../../config/theme.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final user = authProvider.user;
    
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profilo'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // Avatar e info
            Center(
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 50,
                    backgroundColor: AppTheme.primaryColor,
                    child: Text(
                      (user?.nome ?? 'U')[0].toUpperCase(),
                      style: const TextStyle(
                        fontSize: 36,
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    user?.nomeCompleto ?? 'Utente',
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    user?.email ?? '',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.grey[600],
                    ),
                  ),
                  const SizedBox(height: 8),
                  Chip(
                    label: Text(user?.ruolo ?? 'utente'),
                    backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                    labelStyle: TextStyle(color: AppTheme.primaryColor),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),
            
            // Opzioni profilo
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.person),
                    title: const Text('Modifica Profilo'),
                    trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                    onTap: () {},
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.lock),
                    title: const Text('Cambia Password'),
                    trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                    onTap: () {},
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.notifications),
                    title: const Text('Notifiche'),
                    trailing: Switch(
                      value: true,
                      onChanged: (value) {},
                      activeColor: AppTheme.primaryColor,
                    ),
                  ),
                  const Divider(height: 1),
                  ListTile(
                    leading: const Icon(Icons.language),
                    title: const Text('Lingua'),
                    subtitle: const Text('Italiano'),
                    trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                    onTap: () {},
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            
            // Logout
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppTheme.danger,
                ),
                onPressed: () async {
                  await authProvider.logout();
                  if (context.mounted) {
                    Navigator.pop(context);
                  }
                },
                child: const Text('Esci'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
EOF

echo "File creati con successo!"