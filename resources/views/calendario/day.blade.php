<div class="day-view">
    <h3 class="mb-4">{{ $currentDate->locale('it')->isoFormat('dddd D MMMM YYYY') }}</h3>
    
    @if($eventi->count() > 0)
        @foreach(range(0, 23) as $hour)
            @php
                $hourEvents = $eventi->filter(function($evento) use ($hour) {
                    return $evento->data_inizio->hour == $hour;
                });
            @endphp
            
            <div class="hour-slot">
                <div class="hour-label">{{ sprintf('%02d:00', $hour) }}</div>
                <div class="hour-events">
                    @foreach($hourEvents as $evento)
                        <div class="mini-event {{ $evento->priorita }}" onclick="viewEvento({{ $evento->id }})" style="cursor: pointer;">
                            <strong>{{ $evento->data_inizio->format('H:i') }}</strong> - {{ $evento->titolo }}
                            @if($evento->luogo)
                                <small>({{ $evento->luogo }})</small>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="empty-state">
            <div class="empty-state-icon">📅</div>
            <h4>Nessun evento per oggi</h4>
            <p>Non ci sono eventi programmati per questa giornata.</p>
        </div>
    @endif
</div> 