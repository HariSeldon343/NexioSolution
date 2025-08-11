class User {
  final int id;
  final String nome;
  final String cognome;
  final String email;
  final String? username;
  final String? telefono;
  final String ruolo;
  final bool attivo;
  final DateTime? dataCreazione;
  final String? foto;

  User({
    required this.id,
    required this.nome,
    required this.cognome,
    required this.email,
    this.username,
    this.telefono,
    required this.ruolo,
    required this.attivo,
    this.dataCreazione,
    this.foto,
  });

  String get nomeCompleto => '$nome $cognome';

  bool get isSuperAdmin => ruolo == 'super_admin';
  bool get isUtenteSpeciale => ruolo == 'utente_speciale';
  bool get hasElevatedPrivileges => isSuperAdmin || isUtenteSpeciale;

  factory User.fromJson(Map<String, dynamic> json) {
    return User(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      nome: json['nome'] ?? '',
      cognome: json['cognome'] ?? '',
      email: json['email'] ?? '',
      username: json['username'],
      telefono: json['telefono'],
      ruolo: json['ruolo'] ?? 'utente',
      attivo: json['attivo'] == 1 || json['attivo'] == true,
      dataCreazione: json['data_creazione'] != null 
          ? DateTime.parse(json['data_creazione']) 
          : null,
      foto: json['foto'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nome': nome,
      'cognome': cognome,
      'email': email,
      'username': username,
      'telefono': telefono,
      'ruolo': ruolo,
      'attivo': attivo ? 1 : 0,
      'data_creazione': dataCreazione?.toIso8601String(),
      'foto': foto,
    };
  }
}