class Document {
  final int id;
  final String nome;
  final String? descrizione;
  final String? filePath;
  final String? mimeType;
  final int? fileSize;
  final int? cartellaId;
  final int? aziendaId;
  final int? creatoDa;
  final DateTime? dataCreazione;
  final DateTime? dataModifica;
  final String stato;
  final String? tipo;

  Document({
    required this.id,
    required this.nome,
    this.descrizione,
    this.filePath,
    this.mimeType,
    this.fileSize,
    this.cartellaId,
    this.aziendaId,
    this.creatoDa,
    this.dataCreazione,
    this.dataModifica,
    required this.stato,
    this.tipo,
  });

  String get fileSizeFormatted {
    if (fileSize == null) return '-';
    if (fileSize! < 1024) return '$fileSize B';
    if (fileSize! < 1024 * 1024) return '${(fileSize! / 1024).toStringAsFixed(1)} KB';
    return '${(fileSize! / (1024 * 1024)).toStringAsFixed(1)} MB';
  }

  String get fileIcon {
    if (mimeType == null) return '📄';
    if (mimeType!.contains('pdf')) return '📕';
    if (mimeType!.contains('word') || mimeType!.contains('document')) return '📘';
    if (mimeType!.contains('sheet') || mimeType!.contains('excel')) return '📗';
    if (mimeType!.contains('presentation')) return '📙';
    if (mimeType!.contains('image')) return '🖼️';
    if (mimeType!.contains('video')) return '🎬';
    if (mimeType!.contains('audio')) return '🎵';
    if (mimeType!.contains('zip') || mimeType!.contains('rar')) return '📦';
    return '📄';
  }

  factory Document.fromJson(Map<String, dynamic> json) {
    return Document(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      nome: json['nome'] ?? 'Senza nome',
      descrizione: json['descrizione'],
      filePath: json['file_path'],
      mimeType: json['mime_type'] ?? json['file_type'],
      fileSize: json['file_size'] != null 
          ? (json['file_size'] is String ? int.tryParse(json['file_size']) : json['file_size'])
          : null,
      cartellaId: json['cartella_id'] != null 
          ? (json['cartella_id'] is String ? int.tryParse(json['cartella_id']) : json['cartella_id'])
          : null,
      aziendaId: json['azienda_id'] != null 
          ? (json['azienda_id'] is String ? int.tryParse(json['azienda_id']) : json['azienda_id'])
          : null,
      creatoDa: json['creato_da'] != null 
          ? (json['creato_da'] is String ? int.tryParse(json['creato_da']) : json['creato_da'])
          : null,
      dataCreazione: json['data_creazione'] != null 
          ? DateTime.parse(json['data_creazione']) 
          : null,
      dataModifica: json['data_modifica'] != null 
          ? DateTime.parse(json['data_modifica']) 
          : null,
      stato: json['stato'] ?? 'attivo',
      tipo: json['tipo'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nome': nome,
      'descrizione': descrizione,
      'file_path': filePath,
      'mime_type': mimeType,
      'file_size': fileSize,
      'cartella_id': cartellaId,
      'azienda_id': aziendaId,
      'creato_da': creatoDa,
      'data_creazione': dataCreazione?.toIso8601String(),
      'data_modifica': dataModifica?.toIso8601String(),
      'stato': stato,
      'tipo': tipo,
    };
  }
}