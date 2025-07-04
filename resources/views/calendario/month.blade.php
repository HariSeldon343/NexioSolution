<div class="month-view">
    <h3 class="mb-4">{{ $monthData['monthName'] }} {{ $monthData['year'] }}</h3>
    
    <div class="month-header">
        <div class="month-header-day">Lunedì</div>
        <div class="month-header-day">Martedì</div>
        <div class="month-header-day">Mercoledì</div>
        <div class="month-header-day">Giovedì</div>
        <div class="month-header-day">Venerdì</div>
        <div class="month-header-day">Sabato</div>
        <div class="month-header-day">Domenica</div>
    </div>
    
    <div class="month-grid">
        @foreach($monthData['weeks'] as $week)
            @foreach($week as $day)
                <div class="month-day {{ $day['isCurrentMonth'] ? '' : 'other-month' }} {{ $day['isToday'] ? 'today' : '' }}">
                    <div class="month-day-number">{{ $day['dayNumber'] }}</div>
                    
                    @if($day['eventi']->count() > 0)
                        @foreach($day['eventi']->take(3) as $evento)
                            <div class="mini-event {{ $evento->priorita }}" 
                                 onclick="viewEvento({{ $evento->id }})"
                                 title="{{ $evento->titolo }} - {{ $evento->data_inizio->format('H:i') }}">
                                {{ Str::limit($evento->titolo, 15) }}
                            </div>
                        @endforeach
                        
                        @if($day['eventi']->count() > 3)
                            <div class="text-muted" style="font-size: 11px;">
                                +{{ $day['eventi']->count() - 3 }} altri
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach
        @endforeach
    </div>
</div> 