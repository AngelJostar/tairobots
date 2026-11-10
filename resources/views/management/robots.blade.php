@php
  $siteNames = collect($state['sites'])->pluck('name', 'id');
  $robotSymbols = ['humanoid' => '♙', 'drone' => '✦', 'quadruped' => '⌁', 'amr' => '▰', 'arm' => '⚙', 'service' => '◉', 'cleaner' => '◌'];
  $fleets = collect($state['robots'])->pluck('fleet')->filter()->unique()->values();
@endphp
<section class="robots-dashboard" aria-label="Robots y flotas">
  <header class="robots-heading"><div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Cada robot, en su lugar.</h1><p>Conoce las capacidades y el estado de todos tus equipos.</p></div><a class="btn primary" data-open="new-robot">＋ Nuevo robot</a></header>
  <div class="summary-tabs robots-tabs"><span class="active">Tu espacio</span><span>Todos los clientes</span><a href="#robots-grid">Filtrar　⌄</a></div>
  <div class="robots-toolbar"><label class="robots-search">⌕<input type="search" data-filter placeholder="Buscar por nombre, modelo o serie…" aria-label="Buscar robots"></label><select data-robot-fleet><option value="">Todas las flotas</option>@foreach($fleets as $fleet)<option value="{{ $fleet }}">{{ $fleet }}</option>@endforeach</select><span>{{ count($state['robots']) }} robots</span><b>▦　☷　♧</b></div>
  <div class="robots-showcase-grid" id="robots-grid">
    @forelse($state['robots'] as $robot)
      <article class="robot-showcase" data-open="robot-detail-{{ $robot['id'] }}" data-search="{{ strtolower($robot['name'].' '.$robot['serial'].' '.$robot['fleet'].' '.($labels['typeLabels'][$robot['type']] ?? '')) }}" data-fleet="{{ $robot['fleet'] }}" tabindex="0" role="button" aria-label="Ver detalle de {{ $robot['name'] }}">
        <header><span class="summary-status {{ $robot['status'] }}">• {{ $badge($robot['status']) }}</span><b>↗</b></header>
        <div class="robot-portrait type-{{ $robot['type'] }}"><i>{{ $robotSymbols[$robot['type']] ?? '◉' }}</i><small>SIM</small></div>
        <div class="robot-showcase-name"><h2>{{ $robot['name'] }}</h2><span>{{ $labels['typeLabels'][$robot['type']] ?? $robot['type'] }}</span></div>
        <p>⌾　{{ $siteNames[$robot['siteId']] ?? 'Sin sede' }}</p>
        <footer><span>▱ {{ round($robot['battery']) }}%</span><span>{{ count($robot['capabilities']) }} capacidades</span></footer>
      </article>
    @empty
      <p class="empty">Todavía no hay robots. Registra uno para comenzar.</p>
    @endforelse
  </div>
  @foreach($state['robots'] as $robot)
    @php $robotSite = $siteNames[$robot['siteId']] ?? 'Sin sede'; @endphp
    <dialog class="dialog robot-detail-dialog" id="robot-detail-{{ $robot['id'] }}" aria-labelledby="robot-detail-title-{{ $robot['id'] }}">
      <header class="dialog-head"><div><p class="eyebrow">TAIROBOTS · SIMULACIÓN</p><h2 id="robot-detail-title-{{ $robot['id'] }}">{{ $robot['name'] }}</h2></div><button type="button" class="close" data-close aria-label="Cerrar">×</button></header>
      <div class="robot-detail-body">
        <section class="robot-detail-intro"><div class="robot-detail-portrait type-{{ $robot['type'] }}">{{ $robotSymbols[$robot['type']] ?? '◉' }}</div><div><span class="summary-status {{ $robot['status'] }}">• {{ $badge($robot['status']) }}</span><h3>{{ $robot['name'] }}</h3><p>{{ $labels['typeLabels'][$robot['type']] ?? $robot['type'] }} · {{ $robot['manufacturer'] }}</p><small>DATOS SIMULADOS</small></div></section>
        <dl class="robot-detail-grid">
          <div><dt>Serie</dt><dd>{{ $robot['serial'] }}</dd></div><div><dt>Modelo</dt><dd>{{ $robot['model'] }}</dd></div><div><dt>Software</dt><dd>{{ $robot['software'] ?? 'sim/1.0' }}</dd></div>
          <div><dt>Cliente</dt><dd>{{ $client->name }}</dd></div><div><dt>Sede</dt><dd>{{ $robotSite }}</dd></div><div><dt>Flota</dt><dd>{{ $robot['fleet'] }}</dd></div>
          <div><dt>Responsable</dt><dd>{{ $robot['responsible'] }}</dd></div><div><dt>Propietario</dt><dd>{{ $client->name }}</dd></div><div><dt>Batería</dt><dd>{{ round($robot['battery']) }}% · simulada</dd></div>
          <div><dt>Velocidad de referencia</dt><dd>{{ $robot['limits']['speed'] }} m/s · declarada</dd></div><div><dt>Próximo mantenimiento</dt><dd>{{ date('d M Y', $robot['maintenanceDue'] / 1000) }}</dd></div><div><dt>Referencia</dt><dd>{{ $robotSite }}</dd></div>
          <div><dt>Integración</dt><dd>NEXO Simulator · sim/1.0</dd></div>
        </dl>
        <section class="robot-capabilities"><h3>Capacidades declaradas</h3><div>@foreach($robot['capabilities'] as $capability)<span>{{ $labels['capabilityLabels'][$capability] ?? $capability }}</span>@endforeach</div></section>
        <footer class="robot-detail-actions"><a class="btn primary" href="{{ $url('operacion') }}">◉　Centro de operaciones</a><a class="btn" href="{{ $url('mantenimiento') }}">⌕　Mantenimiento</a><a class="btn" href="{{ $url('clientes') }}">Reasignar cliente</a></footer>
      </div>
    </dialog>
  @endforeach
</section>
@include('partials.dialog',['dialogId'=>'new-robot','title'=>'Nuevo robot','operation'=>'robot','fields'=>$forms['robot']])
