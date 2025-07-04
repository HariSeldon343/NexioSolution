<div class="week-view">
    <div class="week-grid">
        @foreach($weekDays as $day)
            <div class="week-day {{ $day['isToday'] ? 'today' : '' }}">
                <div class="week-day-header">
                    <div>{{ $day['dayName'] }}</div>
                    <div>{{ $day['dayNumber'] }}</div>
                </div>
                
                <div class="week-day-events">
                    @foreach($day['eventi'] as $evento)
                        <div class="mini-event {{ $evento->priorita }}" 
                             onclick="viewEvento({{ $evento->id }})" 
                             title="{{ $evento->titolo }} - {{ $evento->data_inizio->format('H:i') }}">
                            <small>{{ $evento->data_inizio->format('H:i') }}</small> {{ Str::limit($evento->titolo, 20) }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div> 