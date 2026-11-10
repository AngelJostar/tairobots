@php
  $orders = collect($state['maintenance'] ?? [])->sortByDesc('createdAt')->values();
  $now = (int) floor(microtime(true) * 1000);
  $openOrders = $orders->where('status', 'open');
  $completedOrders = $orders->where('status', 'completed');
  $overdueRobots = collect($state['robots'] ?? [])->filter(fn($robot) => ($robot['maintenanceDue'] ?? PHP_INT_MAX) < $now);
@endphp
<section class="maintenance-dashboard">
  <header class="maintenance-heading"><div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Preparados para la siguiente misión.</h1><p>Cuida la disponibilidad y el historial de tus equipos.</p></div><button class="btn primary" data-open="new-maintenance">＋ Nueva orden</button></header>
  <div class="summary-tabs maintenance-tabs"><span class="active">Tu espacio</span><span>{{ $client->name }} · {{ $state['sites'][0]['name'] ?? 'Sin sede' }}</span><a>⌕　Filtrar　{{ $orders->count() }}</a></div>
  <div class="maintenance-stats"><article><span>Órdenes abiertas</span><b>{{ $openOrders->count() }}</b><small>Equipos bloqueados para asignación</small></article><article><span>Intervenciones completadas</span><b>{{ $completedOrders->count() }}</b><small>Con notas y resultado registrados</small></article><article><span>Equipos con revisión vencida</span><b>{{ $overdueRobots->count() }}</b><small>Asignación bloqueada por fecha</small></article></div>
  <section class="work-orders">
    <header><h2>Órdenes de trabajo</h2><i>♢</i></header>
    @forelse($orders as $order)
      <article><i>{{ $order['kind'] === 'preventive' ? '⌾' : '⚠' }}</i><div><span class="summary-status {{ $order['status'] }}">{{ $badge($order['status']) }}</span><h3>{{ $order['title'] }}</h3><p>{{ $robotOptions[$order['robotId']] ?? $order['robotId'] }} · {{ $order['kind'] === 'preventive' ? 'Preventivo' : 'Correctivo' }} · {{ $order['technician'] }}</p><small>{{ $order['notes'] ?: 'Sin notas registradas' }}</small></div>@if($order['status'] === 'open')<button class="btn" data-open="close-{{ $order['id'] }}">Cerrar orden　→</button>@include('partials.dialog',['dialogId'=>'close-'.$order['id'],'title'=>'Cerrar mantenimiento','operation'=>'maintenance.close','fields'=>[$hidden('id',$order['id']),$f('notes','Resultado de la intervención','textarea')]])@endif</article>
    @empty
      <div class="work-orders-empty"><i>♢</i><h3>La flota está lista</h3><p>Registra una orden preventiva o correctiva para bloquear el equipo y documentar la intervención.</p></div>
    @endforelse
  </section>
  <dialog class="dialog maintenance-dialog" id="new-maintenance" aria-labelledby="new-maintenance-title">
    <div class="dialog-head"><div><p class="eyebrow">TAIROBOTS · SIMULACIÓN</p><h2 id="new-maintenance-title">Nueva orden de mantenimiento</h2></div><button type="button" class="close" data-close aria-label="Cerrar">×</button></div>
    <form method="post" action="{{ route('management.action') }}">@csrf
      <input type="hidden" name="operation" value="maintenance"><input type="hidden" name="client" value="{{ $client->id }}"><input type="hidden" name="version" value="{{ $client->version }}">
      <div class="maintenance-form">
        <label class="field wide"><span>Robot</span><select name="robotId">@foreach($state['robots'] as $robot)<option value="{{ $robot['id'] }}">{{ $robot['name'] }} · {{ $client->name }}</option>@endforeach</select></label>
        <label class="field wide"><span>Intervención</span><input name="title" placeholder="Ej. Inspección preventiva de ruedas" required></label>
        <label class="field"><span>Tipo</span><select name="kind"><option value="preventive">Preventivo</option><option value="corrective">Correctivo</option></select></label>
        <label class="field"><span>Fecha prevista</span><input type="date" name="scheduledDate" value="{{ now()->format('Y-m-d') }}"></label>
        <label class="field wide"><span>Repuestos</span><input name="parts" placeholder="Opcional"></label>
        <label class="field wide"><span>Observaciones</span><textarea name="notes"></textarea></label>
      </div>
      <p class="maintenance-form-note">♢　 El equipo quedará bloqueado hasta cerrar todas sus órdenes. Debe estar libre de misiones activas.</p>
      <footer class="maintenance-form-footer"><button class="btn primary">Guardar</button></footer>
    </form>
  </dialog>
</section>
