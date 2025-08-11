import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../providers/auth_provider.dart';
import '../../providers/dashboard_provider.dart';
import '../../config/theme.dart';
import '../../widgets/stat_card.dart';

class DashboardPage extends StatefulWidget {
  const DashboardPage({super.key});

  @override
  State<DashboardPage> createState() => _DashboardPageState();
}

class _DashboardPageState extends State<DashboardPage> {
  @override
  void initState() {
    super.initState();
    // Carica dati dashboard all'apertura
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<DashboardProvider>(context, listen: false).loadDashboardData();
    });
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final dashboardProvider = Provider.of<DashboardProvider>(context);
    final user = authProvider.user;
    final company = authProvider.currentCompany;
    
    return RefreshIndicator(
      onRefresh: () async {
        await dashboardProvider.loadDashboardData();
      },
      child: dashboardProvider.isLoading
          ? const Center(child: CircularProgressIndicator())
          : dashboardProvider.error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
                      const SizedBox(height: 16),
                      Text(
                        'Errore caricamento dati',
                        style: TextStyle(fontSize: 18, color: Colors.red[700]),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        dashboardProvider.error!,
                        style: TextStyle(color: Colors.grey[600]),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 16),
                      ElevatedButton(
                        onPressed: () => dashboardProvider.loadDashboardData(),
                        child: const Text('Riprova'),
                      ),
                    ],
                  ),
                )
              : SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Messaggio di benvenuto
                      Card(
                        child: Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(20),
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(12),
                            gradient: LinearGradient(
                              colors: [
                                AppTheme.primaryColor,
                                AppTheme.primaryDark,
                              ],
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Benvenuto,',
                                style: TextStyle(
                                  color: Colors.white.withOpacity(0.9),
                                  fontSize: 16,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                user?.nomeCompleto ?? 'Utente',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 24,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              if (company != null) ...[
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    const Icon(Icons.business, color: Colors.white70, size: 16),
                                    const SizedBox(width: 4),
                                    Text(
                                      company.nome,
                                      style: const TextStyle(
                                        color: Colors.white70,
                                        fontSize: 14,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                              if (dashboardProvider.tasks.isNotEmpty) ...[
                                const SizedBox(height: 12),
                                Text(
                                  'Hai ${dashboardProvider.tasks.length} attività in sospeso',
                                  style: TextStyle(
                                    color: Colors.white.withOpacity(0.9),
                                    fontSize: 14,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),
                      
                      // Statistiche REALI
                      if (dashboardProvider.stats.isNotEmpty) ...[
                        const Text(
                          'Panoramica',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        GridView.count(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          crossAxisCount: 2,
                          mainAxisSpacing: 16,
                          crossAxisSpacing: 16,
                          childAspectRatio: 1.5,
                          children: [
                            if (dashboardProvider.stats['documenti'] != null)
                              StatCard(
                                title: 'Documenti',
                                value: dashboardProvider.stats['documenti'].toString(),
                                icon: Icons.description,
                                color: AppTheme.info,
                              ),
                            if (dashboardProvider.stats['eventi'] != null)
                              StatCard(
                                title: 'Eventi',
                                value: dashboardProvider.stats['eventi'].toString(),
                                icon: Icons.event,
                                color: AppTheme.success,
                              ),
                            if (dashboardProvider.stats['tasks'] != null)
                              StatCard(
                                title: 'Attività',
                                value: dashboardProvider.stats['tasks'].toString(),
                                icon: Icons.task_alt,
                                color: AppTheme.warning,
                              ),
                            if (dashboardProvider.stats['utenti'] != null)
                              StatCard(
                                title: 'Utenti Attivi',
                                value: dashboardProvider.stats['utenti'].toString(),
                                icon: Icons.people,
                                color: AppTheme.primaryColor,
                              ),
                          ],
                        ),
                        const SizedBox(height: 24),
                      ],
                      
                      // Attività recenti REALI
                      if (dashboardProvider.recentActivities.isNotEmpty) ...[
                        const Text(
                          'Attività Recenti',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Card(
                          child: ListView.separated(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: dashboardProvider.recentActivities.length > 10 
                                ? 10 
                                : dashboardProvider.recentActivities.length,
                            separatorBuilder: (context, index) => const Divider(height: 1),
                            itemBuilder: (context, index) {
                              final activity = dashboardProvider.recentActivities[index];
                              return ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: _getActivityColor(activity['tipo']).withOpacity(0.1),
                                  child: Icon(
                                    _getActivityIcon(activity['tipo']),
                                    color: _getActivityColor(activity['tipo']),
                                    size: 20,
                                  ),
                                ),
                                title: Text(activity['descrizione'] ?? ''),
                                subtitle: Text(activity['data'] ?? ''),
                                trailing: Text(
                                  activity['utente'] ?? '',
                                  style: const TextStyle(fontSize: 12),
                                ),
                              );
                            },
                          ),
                        ),
                      ],
                      
                      // Eventi prossimi REALI
                      if (dashboardProvider.upcomingEvents.isNotEmpty) ...[
                        const SizedBox(height: 24),
                        const Text(
                          'Prossimi Eventi',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Card(
                          child: ListView.separated(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: dashboardProvider.upcomingEvents.length > 5 
                                ? 5 
                                : dashboardProvider.upcomingEvents.length,
                            separatorBuilder: (context, index) => const Divider(height: 1),
                            itemBuilder: (context, index) {
                              final event = dashboardProvider.upcomingEvents[index];
                              return ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                                  child: Icon(
                                    Icons.event,
                                    color: AppTheme.primaryColor,
                                    size: 20,
                                  ),
                                ),
                                title: Text(event['titolo'] ?? ''),
                                subtitle: Text(event['data_inizio'] ?? ''),
                                trailing: const Icon(Icons.arrow_forward_ios, size: 16),
                              );
                            },
                          ),
                        ),
                      ],
                      
                      // Attività/Task REALI
                      if (dashboardProvider.tasks.isNotEmpty) ...[
                        const SizedBox(height: 24),
                        const Text(
                          'Attività da Completare',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        Card(
                          child: ListView.separated(
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemCount: dashboardProvider.tasks.length > 5 
                                ? 5 
                                : dashboardProvider.tasks.length,
                            separatorBuilder: (context, index) => const Divider(height: 1),
                            itemBuilder: (context, index) {
                              final task = dashboardProvider.tasks[index];
                              return ListTile(
                                leading: Checkbox(
                                  value: task['stato'] == 'completato',
                                  onChanged: (value) {
                                    // TODO: Implementa completamento task
                                  },
                                ),
                                title: Text(task['titolo'] ?? ''),
                                subtitle: Text(task['descrizione'] ?? ''),
                                trailing: task['priorita'] == 'alta'
                                    ? const Icon(Icons.priority_high, color: Colors.red)
                                    : null,
                              );
                            },
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
    );
  }

  IconData _getActivityIcon(String? tipo) {
    switch (tipo) {
      case 'documento':
        return Icons.description;
      case 'evento':
        return Icons.event;
      case 'utente':
        return Icons.person;
      case 'task':
        return Icons.task_alt;
      default:
        return Icons.info;
    }
  }

  Color _getActivityColor(String? tipo) {
    switch (tipo) {
      case 'documento':
        return AppTheme.info;
      case 'evento':
        return AppTheme.success;
      case 'utente':
        return AppTheme.primaryColor;
      case 'task':
        return AppTheme.warning;
      default:
        return AppTheme.textSecondary;
    }
  }
}