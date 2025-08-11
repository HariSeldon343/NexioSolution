class Folder {
  final int id;
  final String nome;
  final int? parentId;
  final int? aziendaId;
  final int? creatoDa;
  final DateTime? dataCreazione;
  final String? percorso;
  final int livello;
  final String tipo;
  List<Folder> children;
  List<dynamic> files;

  Folder({
    required this.id,
    required this.nome,
    this.parentId,
    this.aziendaId,
    this.creatoDa,
    this.dataCreazione,
    this.percorso,
    this.livello = 0,
    this.tipo = 'standard',
    this.children = const [],
    this.files = const [],
  });

  factory Folder.fromJson(Map<String, dynamic> json) {
    return Folder(
      id: json['id'] is String ? int.parse(json['id']) : json['id'],
      nome: json['nome'] ?? 'Cartella',
      parentId: json['parent_id'] != null 
          ? (json['parent_id'] is String ? int.tryParse(json['parent_id']) : json['parent_id'])
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
      percorso: json['percorso'],
      livello: json['livello'] ?? 0,
      tipo: json['tipo'] ?? 'standard',
      children: json['children'] != null 
          ? (json['children'] as List).map((e) => Folder.fromJson(e)).toList()
          : [],
      files: json['files'] ?? [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nome': nome,
      'parent_id': parentId,
      'azienda_id': aziendaId,
      'creato_da': creatoDa,
      'data_creazione': dataCreazione?.toIso8601String(),
      'percorso': percorso,
      'livello': livello,
      'tipo': tipo,
      'children': children.map((e) => e.toJson()).toList(),
      'files': files,
    };
  }
}