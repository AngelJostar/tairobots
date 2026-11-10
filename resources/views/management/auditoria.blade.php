@php
  $missions = collect($state['missions'] ?? []);
  $events = collect($state['events'] ?? [])->sortByDesc('at')->values();
  $resultStatuses = ['completed' => 'Completada', 'running' => 'En ejecución', 'paused' => 'Pausada', 'cancelled' => 'Cancelada', 'failed' => 'Fallida'];
  $confirmed = $missions->filter(fn($mission) => collect($mission['tasks'] ?? [])->isNotEmpty() && collect($mission['tasks'])->every(fn($task) => ($task['status'] ?? '') === 'completed'))->count();
@endphp
<section class="reports-dashboard">
  <header class="reports-heading"><div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Tu operación, en datos.</h1><p>Resultados calculados a partir de la actividad registrada.</p></div></header>
  <div class="summary-tabs reports-tabs"><span class="active">Tu espacio</span><span>{{ $client->name }} · {{ $state['sites'][0]['name'] ?? 'Sin sede' }}</span><a>⌕　Filtrar　{{ $events->count() }}</a></div>
  <div class="reports-top">
    <section class="mission-results"><header><h2>Resultados de misiones</h2><i>⚑</i></header><div>@foreach($resultStatuses as $status => $label)<article><span>{{ $label }}</span><b><i style="width:{{ min(100, $missions->where('status', $status)->count() * 20) }}%"></i></b><strong>{{ $missions->where('status', $status)->count() }}</strong></article>@endforeach</div></section>
    <section class="confirmed-results"><i>✓</i><b>{{ $confirmed }}</b><h2>Misiones con resultado<br>confirmado</h2><p>La finalización exige evidencia de cada tarea y, cuando aplica, confirmación autorizada.</p><a class="btn" href="{{ route('management.export', ['report' => 'resultados', 'client' => $client->id]) }}" @if($confirmed === 0) data-empty-export @endif>⇩　Exportar resultados</a></section>
  </div>
  <section class="audit-log"><header><div><h2>Bitácora de auditoría</h2><p>Quién, qué, cuándo y sobre qué equipo.</p></div><a class="btn" href="{{ route('management.export', ['report' => 'auditoria', 'client' => $client->id]) }}" @if($events->isEmpty()) data-empty-export @endif>⇩　Exportar CSV</a></header><div class="audit-table"><div class="audit-table-head"><span>Fecha y hora</span><span>Actor</span><span>Acción</span><span>Detalle</span></div>@forelse($events as $event)<article><span>{{ date('d M Y', $event['at'] / 1000) }}<small>{{ date('H:i', $event['at'] / 1000) }}</small></span><span>{{ $event['actor'] }}</span><span><code>{{ $event['action'] }}</code></span><span>{{ $event['detail'] }}</span></article>@empty<div class="audit-empty">No hay eventos registrados todavía.</div>@endforelse</div></section>
</section>
