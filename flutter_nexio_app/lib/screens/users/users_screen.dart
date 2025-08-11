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
