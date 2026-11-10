<section class="clients-dashboard" aria-label="Clientes y sedes">
  <header class="clients-heading">
    <div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Una operación, múltiples entornos.</h1><p>Organiza clientes, sedes y espacios de trabajo.</p></div>
    <div class="clients-actions"><a href="{{ $url('robots') }}">Filtrar　⌄</a></div>
  </header>
  <div class="summary-tabs clients-tabs"><span class="active">Tu espacio</span><span>Todos los clientes</span></div>

  <div class="client-card-list">
    @foreach($clients as $item)
      @php
        $clientState = $item->state;
        $clientRobots = collect($clientState['robots'] ?? []);
        $clientSites = collect($clientState['sites'] ?? []);
        $fleetCount = $clientRobots->pluck('fleet')->filter()->unique()->count();
        $clientUrl = route('management', ['tab' => 'clientes', 'client' => $item->id]);
      @endphp
      <article class="client-summary-card" @if($client->id === $item->id) id="cliente-activo" @endif>
        <header><span class="client-symbol">{{ $loop->first ? '＋' : '♧' }}</span><div><h2>{{ $item->name }}</h2><p>{{ $clientState['industry'] ?? 'Operación conectada' }}</p></div><small>DEMO</small></header>
        <div class="client-summary-metrics"><div><b>{{ $clientSites->count() }}</b><span>sedes</span></div><div><b>{{ $clientRobots->count() }}</b><span>robots</span></div><div><b>{{ $fleetCount }}</b><span>flotas</span></div></div>
        <div class="client-sites">
          @forelse($clientSites as $site)
            @php $siteRobots = $clientRobots->where('siteId', $site['id']); @endphp
            <a href="{{ route('management', ['tab' => 'operacion', 'client' => $item->id]) }}"><i>♙</i><span><b>{{ $site['name'] }}</b><small>{{ $site['building'] }} · {{ $site['floor'] }}</small><em>{{ $site['environment'] }} · {{ $siteRobots->count() }} robots</em></span><strong>↗</strong></a>
          @empty
            <p class="empty">Aún no hay sedes configuradas.</p>
          @endforelse
        </div>
        <footer>▱　Cliente → sede → edificio / piso → flota → robot</footer>
      </article>
    @endforeach
  </div>
</section>

@include('partials.dialog',['dialogId'=>'new-site','title'=>'Nueva sede','operation'=>'site','fields'=>$forms['site']])
