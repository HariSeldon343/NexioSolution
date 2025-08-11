import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../config/theme.dart';
import 'dashboard/dashboard_screen.dart';
import 'documents/documents_screen.dart';
import 'calendar/calendar_screen.dart';
import 'companies/companies_screen.dart';
import 'users/users_screen.dart';
import 'profile/profile_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentIndex = 0;
  
  final List<Widget> _pages = [
    const DashboardPage(),
    const DocumentsScreen(),
    const CalendarScreen(),
    const CompaniesScreen(),
    const UsersScreen(),
  ];

  final List<String> _titles = [
    'Dashboard',
    'Documenti',
    'Calendario',
    'Aziende',
    'Utenti',
  ];

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final user = authProvider.user;
    final company = authProvider.currentCompany;
    final hasElevatedPrivileges = authProvider.hasElevatedPrivileges;
    
    // Rimuovi pagine non accessibili per utenti normali
    final pages = hasElevatedPrivileges 
        ? _pages 
        : [_pages[0], _pages[1], _pages[2]];
    
    final titles = hasElevatedPrivileges 
        ? _titles 
        : [_titles[0], _titles[1], _titles[2]];
    
    return Scaffold(
      appBar: AppBar(
        title: Text(titles[_currentIndex]),
        actions: [
          // Selettore azienda (solo per super admin)
          if (hasElevatedPrivileges && authProvider.companies.length > 1)
            Container(
              margin: const EdgeInsets.only(right: 8),
              padding: const EdgeInsets.symmetric(horizontal: 12),
              decoration: BoxDecoration(
                color: Colors.white.withOpacity(0.1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: DropdownButton<int>(
                value: company?.id,
                dropdownColor: AppTheme.sidebarBg,
                style: const TextStyle(color: Colors.white),
                underline: const SizedBox(),
                icon: const Icon(Icons.arrow_drop_down, color: Colors.white),
                items: authProvider.companies.map((c) {
                  return DropdownMenuItem(
                    value: c.id,
                    child: Text(c.nome),
                  );
                }).toList(),
                onChanged: (value) {
                  if (value != null) {
                    final selectedCompany = authProvider.companies
                        .firstWhere((c) => c.id == value);
                    authProvider.selectCompany(selectedCompany);
                  }
                },
              ),
            ),
          
          // Menu profilo
          PopupMenuButton<String>(
            icon: CircleAvatar(
              backgroundColor: Colors.white.withOpacity(0.2),
              child: Text(
                (user?.nome ?? 'U')[0].toUpperCase(),
                style: const TextStyle(color: Colors.white),
              ),
            ),
            onSelected: (value) async {
              if (value == 'profile') {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ProfileScreen()),
                );
              } else if (value == 'logout') {
                await authProvider.logout();
              }
            },
            itemBuilder: (BuildContext context) => [
              PopupMenuItem(
                value: 'profile',
                child: Row(
                  children: [
                    const Icon(Icons.person, size: 20),
                    const SizedBox(width: 8),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(user?.nomeCompleto ?? 'Utente'),
                        Text(
                          user?.email ?? '',
                          style: const TextStyle(fontSize: 12),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const PopupMenuDivider(),
              const PopupMenuItem(
                value: 'profile',
                child: Row(
                  children: [
                    Icon(Icons.settings, size: 20),
                    SizedBox(width: 8),
                    Text('Profilo'),
                  ],
                ),
              ),
              const PopupMenuItem(
                value: 'logout',
                child: Row(
                  children: [
                    Icon(Icons.logout, size: 20),
                    SizedBox(width: 8),
                    Text('Esci'),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
      body: IndexedStack(
        index: _currentIndex,
        children: pages,
      ),
      bottomNavigationBar: BottomNavigationBar(
        items: [
          const BottomNavigationBarItem(
            icon: Icon(Icons.dashboard_outlined),
            activeIcon: Icon(Icons.dashboard),
            label: 'Dashboard',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.folder_outlined),
            activeIcon: Icon(Icons.folder),
            label: 'Documenti',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.calendar_today_outlined),
            activeIcon: Icon(Icons.calendar_today),
            label: 'Calendario',
          ),
          if (hasElevatedPrivileges) ...[
            const BottomNavigationBarItem(
              icon: Icon(Icons.business_outlined),
              activeIcon: Icon(Icons.business),
              label: 'Aziende',
            ),
            const BottomNavigationBarItem(
              icon: Icon(Icons.people_outline),
              activeIcon: Icon(Icons.people),
              label: 'Utenti',
            ),
          ],
        ],
        currentIndex: _currentIndex,
        onTap: (index) {
          setState(() {
            _currentIndex = index;
          });
        },
      ),
    );
  }
}