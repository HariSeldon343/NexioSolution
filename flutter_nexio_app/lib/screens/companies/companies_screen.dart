import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/company_provider.dart';
import '../../providers/auth_provider.dart';

class CompaniesScreen extends StatefulWidget {
  const CompaniesScreen({super.key});

  @override
  State<CompaniesScreen> createState() => _CompaniesScreenState();
}

class _CompaniesScreenState extends State<CompaniesScreen> {
  @override
  void initState() {
    super.initState();
    // Carica aziende reali all'apertura
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Provider.of<CompanyProvider>(context, listen: false).loadCompanies();
    });
  }

  @override
  Widget build(BuildContext context) {
    final companyProvider = Provider.of<CompanyProvider>(context);
    final authProvider = Provider.of<AuthProvider>(context);
    
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          await companyProvider.loadCompanies();
        },
        child: companyProvider.isLoading
            ? const Center(child: CircularProgressIndicator())
            : companyProvider.error != null
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.error_outline, size: 64, color: Colors.red[300]),
                        const SizedBox(height: 16),
                        Text(
                          'Errore caricamento aziende',
                          style: TextStyle(fontSize: 18, color: Colors.red[700]),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          companyProvider.error!,
                          style: TextStyle(color: Colors.grey[600]),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: () => companyProvider.loadCompanies(),
                          child: const Text('Riprova'),
                        ),
                      ],
                    ),
                  )
                : companyProvider.companies.isEmpty
                    ? const Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.business_outlined, size: 64, color: Colors.grey),
                            SizedBox(height: 16),
                            Text(
                              'Nessuna azienda trovata',
                              style: TextStyle(fontSize: 18, color: Colors.grey),
                            ),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: companyProvider.companies.length,
                        itemBuilder: (context, index) {
                          final company = companyProvider.companies[index];
                          return Card(
                            margin: const EdgeInsets.only(bottom: 8),
                            child: ListTile(
                              leading: CircleAvatar(
                                backgroundColor: AppTheme.primaryColor.withOpacity(0.1),
                                child: Icon(Icons.business, color: AppTheme.primaryColor),
                              ),
                              title: Text(company.nome),
                              subtitle: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  if (company.partitaIva != null && company.partitaIva!.isNotEmpty)
                                    Text('P.IVA: ${company.partitaIva}'),
                                  if (company.citta != null && company.citta!.isNotEmpty)
                                    Text('${company.citta}${company.provincia != null && company.provincia!.isNotEmpty ? ' (${company.provincia})' : ''}'),
                                ],
                              ),
                              isThreeLine: true,
                              trailing: Chip(
                                label: Text(
                                  company.stato == 'attiva' ? 'Attiva' : 
                                  company.stato == 'sospesa' ? 'Sospesa' : 'Cancellata',
                                ),
                                backgroundColor: company.stato == 'attiva' 
                                    ? AppTheme.success.withOpacity(0.1)
                                    : company.stato == 'sospesa'
                                        ? AppTheme.warning.withOpacity(0.1)
                                        : AppTheme.danger.withOpacity(0.1),
                                labelStyle: TextStyle(
                                  color: company.stato == 'attiva' 
                                      ? AppTheme.success
                                      : company.stato == 'sospesa'
                                          ? AppTheme.warning
                                          : AppTheme.danger,
                                ),
                              ),
                              onTap: () {
                                // TODO: Naviga ai dettagli azienda
                              },
                            ),
                          );
                        },
                      ),
      ),
      floatingActionButton: authProvider.isSuperAdmin
          ? FloatingActionButton(
              onPressed: () {
                // TODO: Aggiungi nuova azienda
              },
              backgroundColor: AppTheme.primaryColor,
              child: const Icon(Icons.add),
            )
          : null,
    );
  }
}
