@extends('layouts.app')

@section('title', 'Nuovo Evento')

@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a>
    <span>/</span>
    <a href="{{ route('calendario') }}">Calendario</a>
    <span>/</span>
    <span>Nuovo Evento</span>
@endsection

@section('content')
<div class="page-header mb-4">
    <h1>Nuovo Evento</h1>
</div>

<form action="{{ route('eventi.store') }}" method="POST" class="max-w-2xl">
    @csrf
    
    <div class="card">
        <div class="card-body">
            <!-- Titolo -->
            <div class="form-group mb-3">
                <label for="titolo" class="form-label">Titolo *</label>
                <input type="text" class="form-control @error('titolo') is-invalid @enderror" 
                       id="titolo" name="titolo" value="{{ old('titolo') }}" required>
                @error('titolo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <!-- Descrizione -->
            <div class="form-group mb-3">
                <label for="descrizione" class="form-label">Descrizione</label>
                <textarea class="form-control @error('descrizione') is-invalid @enderror" 
                          id="descrizione" name="descrizione" rows="3">{{ old('descrizione') }}</textarea>
                @error('descrizione')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <!-- Data e ora inizio -->
                <div class="col-md-6 mb-3">
                    <label for="data_inizio" class="form-label">Data e ora inizio *</label>
                    <input type="datetime-local" class="form-control @error('data_inizio') is-invalid @enderror" 
                           id="data_inizio" name="data_inizio" value="{{ old('data_inizio') }}" required>
                    @error('data_inizio')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Data e ora fine -->
                <div class="col-md-6 mb-3">
                    <label for="data_fine" class="form-label">Data e ora fine *</label>
                    <input type="datetime-local" class="form-control @error('data_fine') is-invalid @enderror" 
                           id="data_fine" name="data_fine" value="{{ old('data_fine') }}" required>
                    @error('data_fine')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Luogo -->
            <div class="form-group mb-3">
                <label for="luogo" class="form-label">Luogo</label>
                <input type="text" class="form-control @error('luogo') is-invalid @enderror" 
                       id="luogo" name="luogo" value="{{ old('luogo') }}">
                @error('luogo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <!-- Tipo -->
                <div class="col-md-6 mb-3">
                    <label for="tipo" class="form-label">Tipo *</label>
                    <select class="form-control @error('tipo') is-invalid @enderror" 
                            id="tipo" name="tipo" required>
                        <option value="">Seleziona tipo</option>
                        <option value="riunione" {{ old('tipo') == 'riunione' ? 'selected' : '' }}>Riunione</option>
                        <option value="formazione" {{ old('tipo') == 'formazione' ? 'selected' : '' }}>Formazione</option>
                        <option value="scadenza" {{ old('tipo') == 'scadenza' ? 'selected' : '' }}>Scadenza</option>
                        <option value="altro" {{ old('tipo') == 'altro' ? 'selected' : '' }}>Altro</option>
                    </select>
                    @error('tipo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Priorità -->
                <div class="col-md-6 mb-3">
                    <label for="priorita" class="form-label">Priorità *</label>
                    <select class="form-control @error('priorita') is-invalid @enderror" 
                            id="priorita" name="priorita" required>
                        <option value="">Seleziona priorità</option>
                        <option value="bassa" {{ old('priorita') == 'bassa' ? 'selected' : '' }}>Bassa</option>
                        <option value="media" {{ old('priorita') == 'media' ? 'selected' : '' }}>Media</option>
                        <option value="alta" {{ old('priorita') == 'alta' ? 'selected' : '' }}>Alta</option>
                    </select>
                    @error('priorita')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <!-- Invitati -->
            <div class="form-group mb-3">
                <label for="invitati" class="form-label">Invita partecipanti</label>
                <select class="form-control @error('invitati') is-invalid @enderror" 
                        id="invitati" name="invitati[]" multiple>
                    @foreach(\App\Models\User::where('id', '!=', auth()->id())->get() as $user)
                        <option value="{{ $user->id }}" {{ in_array($user->id, old('invitati', [])) ? 'selected' : '' }}>
                            {{ $user->nome }} {{ $user->cognome }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
                @error('invitati')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="form-text text-muted">Tieni premuto Ctrl per selezionare più utenti</small>
            </div>

            <!-- Notifiche -->
            <div class="form-group mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="notifica_email" 
                           name="notifica_email" value="1" {{ old('notifica_email') ? 'checked' : '' }}>
                    <label class="form-check-label" for="notifica_email">
                        Invia notifica email agli invitati
                    </label>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="preavviso_minuti" class="form-label">Preavviso (minuti)</label>
                <input type="number" class="form-control @error('preavviso_minuti') is-invalid @enderror" 
                       id="preavviso_minuti" name="preavviso_minuti" value="{{ old('preavviso_minuti', 30) }}" min="0">
                @error('preavviso_minuti')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">
            <span>➕</span>
            <span>Crea Evento</span>
        </button>
        <a href="{{ route('calendario') }}" class="btn btn-secondary">Annulla</a>
    </div>
</form>
@endsection 