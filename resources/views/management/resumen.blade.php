@php
  $robots = collect($state['robots']);
  $available = $robots->where('status', 'available')->count();
  $activeMissions = collect($state['missions'])->whereIn('status', \App\Services\Robotics::ACTIVE);
  $openAlerts = collect($state['alerts'])->where('status', '!=', 'resolved');
  $sitesById = collect($state['sites'])->keyBy('id');
  $typeSymbol = ['humanoid' => '♙', 'drone' => '✦', 'quadruped' => '⌁', 'amr' => '▰', 'arm' => '⚙', 'service' => '◉', 'cleaner' => '◌'];
@endphp

<section class="summary-dashboard" aria-label="Resumen de la operación">
  <header class="summary-heading">
    <div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Tu operación, en armonía.</h1><p>Todo lo que necesitas para coordinar tu flota.</p></div>
    <div class="summary-actions"><a class="btn primary" href="{{ $url('misiones') }}">＋ Nueva misión</a><button class="btn" data-pulse="{{ route('management.pulse') }}">Actualizar simulación ↻</button></div>
  </header>
  <div class="summary-tabs"><span class="active">Tu espacio</span><span>Todos los clientes</span><a href="{{ $url('robots') }}">Filtrar ⌄</a></div>
  <section class="summary-hero">
    <div><p>UNA FLOTA. UN SOLO ESPACIO.</p><h2>Menos pasos.<br>Más posibilidades.</h2><span>Asigna una misión. Elige tu robot.<br>Sigue cada resultado desde aquí.</span><a href="{{ $url('misiones') }}">Explorar misiones <b>→</b></a></div>
    <div class="summary-hero-art" aria-hidden="true"><i class="orbit orbit-one"></i><i class="orbit orbit-two"></i><div class="summary-bot">●</div><div class="summary-rover"><b></b><i></i><i></i></div><small>● Coordinados desde Tairobots</small></div>
  </section>
  <section class="summary-stats" aria-label="Indicadores">
    <article><span>Robots en tu flota</span><b class="stat-icon">♙</b><strong>{{ str_pad((string) $robots->count(), 2, '0', STR_PAD_LEFT) }}</strong><small>• {{ $robots->count() }} conectados</small></article>
    <article><span>Disponibles</span><b class="stat-icon ok">✓</b><strong>{{ str_pad((string) $available, 2, '0', STR_PAD_LEFT) }}</strong><small>• Listos para una nueva tarea</small></article>
    <article><span>Misiones activas</span><b class="stat-icon info">➤</b><strong>{{ str_pad((string) $activeMissions->count(), 2, '0', STR_PAD_LEFT) }}</strong><small>• {{ $activeMissions->count() }} por iniciar</small></article>
    <article><span>Alertas abiertas</span><b class="stat-icon warn">△</b><strong>{{ str_pad((string) $openAlerts->count(), 2, '0', STR_PAD_LEFT) }}</strong><small>• Tu operación está al día</small></article>
  </section>
  <section class="summary-card summary-map-card">
    <header><div><h2>Vista de operaciones <small>EN VIVO · SIM</small></h2><p>Tu flota, ubicada en su entorno.</p></div><form method="get" action="{{ route('management') }}"><input type="hidden" name="tab" value="resumen"><select name="client" onchange="this.form.submit()">@foreach($clients as $item)<option value="{{ $item->id }}" @selected($client->id===$item->id)>{{ $item->name }}</option>@endforeach</select></form></header>
    <div class="summary-map"><span class="map-label">● Plano de simulación · {{ $state['sites'][0]['floor'] ?? 'Planta baja' }}</span>@foreach($state['zones'] as $zone)<div class="summary-zone {{ $zone['kind'] }}" style="left:{{ $zone['x'] }}%;top:{{ $zone['y'] / 70 * 100 }}%;width:{{ $zone['w'] }}%;height:{{ $zone['h'] / 70 * 100 }}%">{{ $zone['name'] }}</div>@endforeach<div class="summary-route"></div>@foreach($robots as $robot)<div class="summary-map-robot" style="left:{{ $robot['x'] }}%;top:{{ $robot['y'] / 70 * 100 }}%" title="{{ $robot['name'] }}">{{ strtoupper(substr($robot['name'], 0, 1)) }}</div>@endforeach<div class="map-legend">● Robot　 ┄ ┄ Ruta de referencia</div></div>
    <footer><a href="{{ $url('operacion') }}">Abrir centro de operaciones　↗</a></footer>
  </section>
  <section class="summary-card summary-missions">
    <header><h2>Misiones activas <small>{{ $activeMissions->count() }}</small></h2><a href="{{ $url('misiones') }}">↗</a></header>
    @if($activeMissions->isEmpty())<div class="summary-empty"><b>➤</b><h3>Tu siguiente misión empieza aquí</h3><p>Asigna una tarea compatible y observa su ejecución.</p><a class="btn" href="{{ $url('misiones') }}">＋ Crear misión</a></div>@else<div class="summary-mission-list">@foreach($activeMissions as $mission)<a href="{{ $url('misiones') }}">{{ $mission['name'] }} <span>{{ $badge($mission['status']) }}</span> ↗</a>@endforeach</div>@endif
    <footer><a href="{{ $url('misiones') }}">Ver todas las misiones　→</a></footer>
  </section>
  <section class="summary-card summary-fleet">
    <header><h2>Estado de la flota <small>{{ $robots->count() }}</small></h2><a href="{{ $url('robots') }}">Ver todos　→</a></header>
    <div class="summary-fleet-head"><span>Robot</span><span>Estado</span><span>Ubicación</span><span>Batería</span><span>Última señal</span><span></span></div>
    @forelse($robots as $robot)<a class="summary-robot-row" href="{{ $url('robots') }}"><span class="summary-robot-name"><i>{{ $typeSymbol[$robot['type']] ?? '◉' }}</i><b>{{ $robot['name'] }}<small>{{ $labels['typeLabels'][$robot['type']] ?? $robot['type'] }}</small></b></span><span class="summary-status {{ $robot['status'] }}">• {{ $badge($robot['status']) }}</span><span>{{ $sitesById[$robot['siteId']]['name'] ?? '—' }}<small>{{ $robot['area'] ?? $robot['fleet'] }}</small></span><span>▱ {{ round($robot['battery']) }}%</span><span>• 0 s</span><b>↗</b></a>@empty<p class="empty">Aún no hay robots registrados.</p>@endforelse
    <footer>En misión　{{ $robots->whereIn('status', ['busy','running'])->count() }}　 Cargando　{{ $robots->where('status','charging')->count() }}　 Desconectado　{{ $robots->where('status','offline')->count() }}</footer>
  </section>
  <section class="summary-card summary-activity">
    <header><h2>Actividad reciente</h2><span>◷</span></header>
    <div>@forelse(array_slice(array_reverse($state['events']), 0, 5) as $event)<article><b>⌁</b><p>{{ $event['detail'] }}<small>{{ $event['actor'] }} · {{ date('H:i', $event['at'] / 1000) }}</small></p></article>@empty<p class="empty">Aún no hay actividad reciente.</p>@endforelse</div>
    <footer><a href="{{ $url('auditoria') }}">Consultar auditoría　→</a></footer>
  </section>
</section>
