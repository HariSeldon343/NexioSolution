@extends('layouts.app')

@section('title', $evento->titolo)

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>/</span>
    <a href="{{ route('calendario') }}">Calendario</a>
    <span>/</span>
    <span>{{ $evento->titolo }}</span>
@endsection

@section('content')
<div class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h1>{{ $evento->titolo }}</h1>
        <div>
            @can('update', $evento)
                <a href="{{ route('eventi.edit', $evento) }}" class="btn btn-warning">
                    <span>✏️</span>
                    <span>Modifica</span>
                </a>
            @endcan
            @can('delete', $evento)
                <form action="{{ route('eventi.destroy', $evento) }}" method="POST" class="d-inline" 
                      onsubmit="return confirm('Sei sicuro di voler eliminare questo evento?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <span>🗑️</span>
                        <span>Elimina</span>
                    </button>
                </form>
            @endcan
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Dettagli Evento</h5>
                
                @if($evento->descrizione)
                    <p class="mb-3">{{ $evento->descrizione }}</p>
                @endif
                
                <dl class="row">
                    <dt class="col-sm-3">Data e ora inizio:</dt>
                    <dd class="col-sm-9">{{ $evento->data_inizio->format('d/m/Y H:i') }}</dd>
                    
                    <dt class="col-sm-3">Data e ora fine:</dt>
                    <dd class="col-sm-9">{{ $evento->data_fine->format('d/m/Y H:i') }}</dd>
                    
                    <dt class="col-sm-3">Durata:</dt>
                    <dd class="col-sm-9">{{ $evento->durata_human }}</dd>
                    
                    @if($evento->luogo)
                        <dt class="col-sm-3">Luogo:</dt>
                        <dd class="col-sm-9">{{ $evento->luogo }}</dd>
                    @endif
                    
                    <dt class="col-sm-3">Tipo:</dt>
                    <dd class="col-sm-9">{{ $evento->tipoLabel }}</dd>
                    
                    <dt class="col-sm-3">Priorità:</dt>
                    <dd class="col-sm-9">
                        <span class="badge badge-{{ $evento->prioritaColor }}">
                            {{ ucfirst($evento->priorita) }}
                        </span>
                    </dd>
                    
                    <dt class="col-sm-3">Stato:</dt>
                    <dd class="col-sm-9">
                        <span class="badge badge-{{ $evento->statoColor }}">
                            {{ ucfirst($evento->stato) }}
                        </span>
                    </dd>
                    
                    <dt class="col-sm-3">Creato da:</dt>
                    <dd class="col-sm-9">{{ $evento->creatoDa->nome }} {{ $evento->creatoDa->cognome }}</dd>
                    
                    @if($evento->azienda)
                        <dt class="col-sm-3">Azienda:</dt>
                        <dd class="col-sm-9">{{ $evento->azienda->nome }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Partecipanti ({{ $evento->partecipanti->count() }})</h5>
                
                @if($evento->partecipanti->count() > 0)
                    <ul class="list-unstyled">
                        @foreach($evento->partecipanti as $partecipante)
                            <li class="mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $partecipante->utente->nome }} {{ $partecipante->utente->cognome }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $partecipante->utente->email }}</small>
                                    </div>
                                    <span class="badge badge-{{ 
                                        $partecipante->stato == 'confermato' ? 'success' : 
                                        ($partecipante->stato == 'rifiutato' ? 'danger' : 'secondary') 
                                    }}">
                                        {{ ucfirst($partecipante->stato) }}
                                    </span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-muted">Nessun partecipante invitato</p>
                @endif
                
                @if(!$evento->isPartecipanteUser(auth()->id()) && $evento->isFuturo())
                    <form action="{{ route('eventi.partecipa', $evento) }}" method="POST" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-block">
                            Partecipa all'evento
                        </button>
                    </form>
                @elseif($evento->hasConfermato(auth()->id()) && $evento->isFuturo())
                    <form action="{{ route('eventi.annulla-partecipazione', $evento) }}" method="POST" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block">
                            Annulla partecipazione
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="{{ route('calendario') }}" class="btn btn-secondary">
        <span>←</span>
        <span>Torna al calendario</span>
    </a>
</div>
@endsection 