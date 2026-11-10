@php
  $activeSite = $state['sites'][0] ?? null;
  $siteZones = $activeSite ? collect($state['zones'])->where('siteId', $activeSite['id'])->values() : collect();
  $siteRobots = $activeSite ? collect($state['robots'])->where('siteId', $activeSite['id'])->values() : collect();
  $kindLabel = ['restricted' => 'Zona restringida', 'work' => 'Área de trabajo', 'charging' => 'Estación de carga', 'poi' => 'Punto de interés'];
@endphp
<section class="maps-dashboard">
  <header class="maps-heading"><div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Un contexto para cada movimiento.</h1><p>Planos locales por sede. Coordenadas en metros.</p></div><button class="btn primary" data-open="new-zone">＋ Nueva zona</button></header>
  <div class="summary-tabs"><span class="active">Tu espacio</span><span>{{ $client->name }} · {{ $activeSite['name'] ?? 'Sin sede' }}</span><a>⌕　Filtrar　{{ $siteZones->count() }}</a></div>
  @if($activeSite)
    <section class="maps-card">
      <header><div><h2>{{ $activeSite['name'] }}</h2><p>{{ $activeSite['building'] }} · {{ $activeSite['floor'] }} · {{ $activeSite['width'] ?? 100 }} × {{ $activeSite['height'] ?? 70 }} m</p></div><small>♧　 Coordenadas locales</small></header>
      <div class="maps-canvas">
        <span class="map-label">●　Plano de simulación　· {{ $activeSite['floor'] }}</span><span class="map-north">N local<br>▲</span>
        @foreach($siteZones as $zone)<div class="maps-zone {{ $zone['kind'] }}" style="left:{{ $zone['x'] }}%;top:{{ $zone['y'] / ($activeSite['height'] ?? 70) * 100 }}%;width:{{ $zone['w'] }}%;height:{{ $zone['h'] / ($activeSite['height'] ?? 70) * 100 }}%"><span>{{ $zone['name'] }}</span></div>@endforeach
        <div class="summary-route"></div>
        @foreach($siteRobots as $robot)<div class="maps-robot" style="left:{{ $robot['x'] }}%;top:{{ $robot['y'] / ($activeSite['height'] ?? 70) * 100 }}%" title="{{ $robot['name'] }}">▲<small>{{ $robot['name'] }}</small></div>@endforeach
        <div class="map-legend">●　Robot　 ┄ ┄　Ruta de referencia</div><small class="map-scale">10 m ───</small><small class="map-frame">{{ $activeSite['frame'] }}</small>
      </div>
    </section>
    <p class="maps-note">◉　 Este plano ilustra un entorno simulado. Las posiciones usan <b>{{ $activeSite['frame'] }}</b> con unidades en metros. No existe una transformación a GPS; cartografía exterior y navegación física quedan para la integración real.</p>
    <div class="map-zone-cards">@forelse($siteZones as $zone)<article class="{{ $zone['kind'] }}"><i>{{ $zone['kind'] === 'charging' ? '⌾' : ($zone['kind'] === 'restricted' ? '⌖' : '◫') }}</i><div><h3>{{ $zone['name'] }}</h3><p>{{ $kindLabel[$zone['kind']] ?? $zone['kind'] }}</p><small>{{ $zone['x'] }} m · {{ $zone['y'] }} m · {{ $zone['w'] }} × {{ $zone['h'] }} m</small>@if(!collect($state['resources'])->contains('id', $zone['id']))@include('partials.action',['operation'=>'zone.delete','data'=>['id'=>$zone['id']],'button'=>'Eliminar zona','confirm'=>'¿Eliminar esta zona?'])@endif</div></article>@empty<p class="empty">Todavía no hay zonas configuradas.</p>@endforelse</div>
  @else
    <section class="mission-empty"><i>⌑</i><h3>No hay una sede seleccionada</h3><p>Crea una sede para visualizar y configurar su plano local.</p></section>
  @endif
</section>
