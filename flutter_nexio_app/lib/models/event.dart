class Event {
  final int id;
  final String titolo;
  final String? descrizione;
  final DateTime dataInizio;
  final DateTime? dataFine;
  final String? luogo;
  final String tipo;
  final String stato;
  final int? aziendaId;
  final int? creatoDa;
  final DateTime? dataCreazione;
  final String? colore;
  final bool tuttoIlGiorno;
  final String? ricorrenza;
  final List<String> partecipanti;

  Event({
    required this.id,
    required this.titolo,
    this.descrizione,
    required this.dataInizio,
    this.dataFine,
    this.luogo,
    required this.tipo,
    required this.stato,
    this.aziendaId,
    this.creatoDa,
    this.dataCreazione,
    this.colore,
    this.tuttoIlGiorno = false,
    this.ricorrenza,
    this.partecipanti = const [],
  });

  String get eventColor {
    if (colore != null) return colore!;
    switch (tipo) {
      case 'riunione':
        return '#2196F3';
      case 'appuntamento':
        return '#4CAF50';
      case 'scadenza':
        return '#FF9800';
      case 'promemoria':
        return '#9C27B0';
      default:
        return '#607D8B';
    }
  }

  factory Event.fromJson(Map<String, dynamic> json) {
    return Event(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      titolo: json['titolo'] ?? 'Evento',
      descrizione: json['descrizione'],
      dataInizio: DateTime.parse(json['data_inizio']),
      dataFine: json['data_fine'] != null ? DateTime.parse(json['data_fine']) : null,
      luogo: json['luogo'],
      tipo: json['tipo'] ?? 'evento',
      stato: json['stato'] ?? 'attivo',
      aziendaId: json['azienda_id'] != null 
          ? (json['azienda_id'] is String ? int.tryParse(json['azienda_id']) : json['azienda_id'])
          : null,
      creatoDa: json['creato_da'] != null 
          ? (json['creato_da'] is String ? int.tryParse(json['creato_da']) : json['creato_da'])
          : null,
      dataCreazione: json['data_creazione'] != null 
          ? DateTime.parse(json['data_creazione']) 
          : null,
      colore: json['colore'],
      tuttoIlGiorno: json['tutto_il_giorno'] == 1 || json['tutto_il_giorno'] == true,
      ricorrenza: json['ricorrenza'],
      partecipanti: json['partecipanti'] != null 
          ? List<String>.from(json['partecipanti']) 
          : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'titolo': titolo,
      'descrizione': descrizione,
      'data_inizio': dataInizio.toIso8601String(),
      'data_fine': dataFine?.toIso8601String(),
      'luogo': luogo,
      'tipo': tipo,
      'stato': stato,
      'azienda_id': aziendaId,
      'creato_da': creatoDa,
      'data_creazione': dataCreazione?.toIso8601String(),
      'colore': colore,
      'tutto_il_giorno': tuttoIlGiorno ? 1 : 0,
      'ricorrenza': ricorrenza,
      'partecipanti': partecipanti,
    };
  }
}