@php
  $alerts = collect($state['alerts'] ?? [])->sortByDesc('createdAt')->values();
  $openAlerts = $alerts->where('status', 'open')->count();
@endphp
<section class="alerts-dashboard">
  <header class="alerts-heading"><div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Atención donde hace falta.</h1><p>Revisa, asigna y documenta cada incidente.</p></div></header>
  <div class="summary-tabs alerts-tabs"><span class="active">Tu espacio</span><span>{{ $client->name }} · {{ $state['sites'][0]['name'] ?? 'Sin sede' }}</span><a>⌕　Filtrar　{{ $alerts->count() }}</a></div>
  <section class="incident-panel">
    <header><h2>Incidentes <small>{{ $openAlerts }}</small></h2><nav><a>Abiertas</a><a>En atención</a><a>Resueltas</a></nav></header>
    @forelse($alerts as $alert)
      <article class="incident-row">
        <i>!</i><div><span class="summary-status {{ $alert['severity'] }}">{{ $badge($alert['severity']) }}</span><h3>{{ $alert['title'] }}</h3><p>{{ $robotOptions[$alert['robotId']] ?? '—' }} · {{ $siteOptions[$alert['siteId']] ?? '—' }} · Responsable: {{ $alert['owner'] }}</p></div><button class="btn" data-open="alert-{{ $alert['id'] }}">Atender　→</button>
      </article>
      @include('partials.dialog',['dialogId'=>'alert-'.$alert['id'],'title'=>'Atender alerta','operation'=>'alert','fields'=>[$hidden('id',$alert['id']),$f('status','Estado','select',$alert['status'],['open'=>'Abierta','acknowledged'=>'En atención','resolved'=>'Resuelta']),$f('comment','Comentario / resolución','textarea')]])
    @empty
      <div class="incident-empty"><i>♢</i><h3>Todo en orden</h3><p>No hay incidentes registrados. Prueba una desconexión, una falla o batería baja desde los escenarios.</p><button class="btn" data-open="simulation-scenario">Probar escenario</button></div>
    @endforelse
  </section>
</section>
