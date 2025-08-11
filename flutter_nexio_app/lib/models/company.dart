class Company {
  final int id;
  final String nome;
  final String? partitaIva;
  final String? codiceFiscale;
  final String? indirizzo;
  final String? citta;
  final String? cap;
  final String? provincia;
  final String? telefono;
  final String? email;
  final String? sitoweb;
  final String? logo;
  final String stato;
  final DateTime? dataCreazione;

  Company({
    required this.id,
    required this.nome,
    this.partitaIva,
    this.codiceFiscale,
    this.indirizzo,
    this.citta,
    this.cap,
    this.provincia,
    this.telefono,
    this.email,
    this.sitoweb,
    this.logo,
    required this.stato,
    this.dataCreazione,
  });

  bool get isActive => stato == 'attiva';

  factory Company.fromJson(Map<String, dynamic> json) {
    return Company(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      nome: json['nome'] ?? '',
      partitaIva: json['partita_iva'],
      codiceFiscale: json['codice_fiscale'],
      indirizzo: json['indirizzo'],
      citta: json['citta'],
      cap: json['cap'],
      provincia: json['provincia'],
      telefono: json['telefono'],
      email: json['email'],
      sitoweb: json['sitoweb'],
      logo: json['logo'],
      stato: json['stato'] ?? 'attiva',
      dataCreazione: json['data_creazione'] != null 
          ? DateTime.parse(json['data_creazione']) 
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nome': nome,
      'partita_iva': partitaIva,
      'codice_fiscale': codiceFiscale,
      'indirizzo': indirizzo,
      'citta': citta,
      'cap': cap,
      'provincia': provincia,
      'telefono': telefono,
      'email': email,
      'sitoweb': sitoweb,
      'logo': logo,
      'stato': stato,
      'data_creazione': dataCreazione?.toIso8601String(),
    };
  }
}