@php
  $templateRows = $templates->map(fn($row) => $row->data)->values();
  $phase = min(3, max(1, (int) request('fase', 1)));
  $phaseUrl = fn($number) => route('management', array_filter(['tab' => 'misiones', 'client' => $client->id, 'fase' => $number]));
  $implementations = collect($state['implementations'] ?? []);
  $missions = collect($state['missions'] ?? []);
  $operatingView = request('vista') === 'tareas' ? 'tareas' : 'misiones';
  $statusFilter = request('estado', 'todas');
  $robotFilter = request('robot', 'todos');
  $operatingUrl = fn(array $changes = []) => route('management', array_filter(array_merge(['tab' => 'misiones', 'client' => $client->id, 'fase' => 3, 'vista' => $operatingView, 'estado' => $statusFilter, 'robot' => $robotFilter], $changes), fn($value) => $value !== 'todas' && $value !== 'todos'));
  $filteredMissions = $missions->filter(function ($mission) use ($statusFilter, $robotFilter) {
      $statusOk = $statusFilter === 'todas' || ($statusFilter === 'activas' ? in_array($mission['status'], ['assigned', 'accepted', 'running', 'paused']) : ($statusFilter === 'pendientes' ? in_array($mission['status'], ['draft', 'scheduled']) : !in_array($mission['status'], ['assigned', 'accepted', 'running', 'paused', 'draft', 'scheduled'])));
      return $statusOk && ($robotFilter === 'todos' || ($mission['robotId'] ?? $mission['preferredRobot'] ?? '') === $robotFilter);
  });
  $taskRows = $missions->flatMap(function ($mission) use ($state) {
      $robotId = $mission['robotId'] ?? $mission['preferredRobot'] ?? null;
      $robot = collect($state['robots'] ?? [])->firstWhere('id', $robotId);
      return collect($mission['tasks'] ?? [])->map(fn($task) => ['task' => $task, 'mission' => $mission, 'robot' => $robot]);
  })->filter(function ($row) use ($statusFilter, $robotFilter) {
      return ($statusFilter === 'todas' || ($row['task']['status'] ?? 'pending') === $statusFilter) && ($robotFilter === 'todos' || ($row['robot']['id'] ?? '') === $robotFilter);
  });
@endphp
<section class="missions-dashboard">
 <header class="missions-heading">
   <div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>De la tarea al resultado.</h1><p>Coordina actividades, recursos y evidencias.</p></div>
   <button class="btn primary" data-open="new-mission">＋ Nueva misión</button>
 </header>
 <div class="summary-tabs"><span class="active">Tu espacio</span><span>{{ $client->name }} · {{ $state['sites'][0]['name'] ?? 'Sin sede' }}</span><a>Filtrar　{{ $missions->count() }}</a></div>
 <nav class="mission-flow" aria-label="Fases de las misiones">
   @foreach ([1 => ['Elegir misión maestra', 'Etapa actual'], 2 => ['Implementar por cliente', 'Por realizar'], 3 => ['Operar la misión', 'Por realizar']] as $number => [$label, $caption])
     @if($number > 1)<i>›</i>@endif
     <a href="{{ $phaseUrl($number) }}" class="{{ $phase === $number ? 'active' : '' }}"><b>{{ $number }}</b><strong>{{ $label }}</strong><small>{{ $phase === $number ? 'Etapa actual' : $caption }}</small></a>
   @endforeach
 </nav>

 @if($phase === 1)
   <div class="catalog-intro"><div><p class="eyebrow">DISEÑAR UNA VEZ · ADAPTAR POR CLIENTE</p><h2>Un catálogo para cada actividad.</h2><p>Instrucciones generales y tareas reutilizables. Cada cliente configura su propia implementación antes de pasar a operaciones.</p></div><button type="button" class="btn primary" data-open="new-master-mission">＋ Nueva misión maestra</button></div>
   <div class="catalog-metrics"><b>{{ $templateRows->count() }}</b> misiones maestras　·　<b>{{ count($categories->categories) }}</b> categorías　·　<b>{{ collect($categories->categories)->sum(fn($c) => count($c['subcategories'])) }}</b> subcategorías <a href="{{ $url('catalogo') }}">⚙　Administrar categorías</a></div>
   <div class="mission-filters"><label>⌕<input type="search" data-filter placeholder="Buscar una misión o actividad…"></label><select><option>Todas las categorías</option></select><select><option>Todas las subcategorías</option></select></div>
   <p class="mission-count">{{ $templateRows->count() }} misiones encontradas</p>
   <div class="mission-template-grid">@forelse($templateRows as $template)<article data-search="{{ strtolower($template['name'].' '.$template['category'].' '.$template['subcategory']) }}"><header><i>▯</i><small>v{{ $template['revision'] ?? 1 }} · {{ $template['subcategory'] ?? $template['category'] }}</small></header><p>{{ $template['category'] }}</p><h3>{{ $template['name'] }}</h3><div class="template-text">{{ str($template['instructions'])->limit(180) }}</div><span>1. {{ $template['steps'][0]['capability'] ?? 'Tarea' }}</span><details><summary>▸ Criterio y tareas generales</summary><p>{{ $template['completion'] }}</p></details><footer><button type="button" class="btn primary" data-open="implement-{{ $template['id'] }}">Implementar　→</button><button type="button" class="btn" data-open="version-{{ $template['id'] }}">Nueva versión</button></footer></article>@empty<p class="empty">No hay misiones maestras disponibles.</p>@endforelse</div>
   @foreach($templateRows as $template)
     @include('partials.dialog',['dialogId'=>'implement-'.$template['id'],'title'=>'Nueva implementación','operation'=>'instantiate','fields'=>[$hidden('templateId',$template['id']),$f('siteId','Sede','select','',$siteOptions)]])
     @include('partials.dialog',['dialogId'=>'version-'.$template['id'],'title'=>'Nueva versión de misión','operation'=>'template','fields'=>[$hidden('parentId',$template['id']),$f('name','Nombre','text',$template['name']),$f('categoryId','Categoría','select',$template['categoryId'],$categoryOptions),$f('subcategoryId','Subcategoría','select',$template['subcategoryId'],$subcategoryOptions),$f('instructions','Instrucciones generales','textarea',$template['instructions']),$f('completion','Criterio general de finalización','textarea',$template['completion'])],'repeater'=>'steps','rows'=>$template['steps']])
   @endforeach
   @include('partials.dialog',['dialogId'=>'new-master-mission','title'=>'Nueva misión maestra','operation'=>'template','fields'=>[$f('name','Nombre'),$f('categoryId','Categoría','select','',$categoryOptions),$f('subcategoryId','Subcategoría','select','',$subcategoryOptions),$f('instructions','Instrucciones generales','textarea'),$f('completion','Criterio general de finalización','textarea')],'repeater'=>'steps','rows'=>[[]]])
 @elseif($phase === 2)
   <div class="catalog-intro mission-phase-intro"><div><p class="eyebrow">CONTEXTO, RECURSOS Y REVISIÓN</p><h2>Una implementación para cada entorno.</h2><p>Configura la sede, adjunta referencias y revisa las condiciones antes de generar una misión.</p></div><a class="btn primary" href="{{ $phaseUrl(1) }}">Elegir misión maestra　→</a></div>
   <div class="implementation-list">@forelse($implementations as $item)
     <article class="implementation-card"><header><i>▧</i><div><span>{{ $siteOptions[$item['siteId']] ?? 'Sede' }}</span><h3>{{ $item['name'] }}</h3><p>Plantilla v{{ $item['template']['revision'] ?? 1 }} · {{ count($item['missions'] ?? []) }} misiones generadas</p></div><b>{{ $badge($item['status']) }}</b></header><p>{{ str($item['instructions'] ?: ($item['template']['instructions'] ?? 'Configura las condiciones de esta misión.'))->limit(180) }}</p><footer><a class="btn primary" href="{{ $url('implementaciones', ['implementation' => $item['id']]) }}">Abrir implementación　→</a></footer></article>
   @empty
     <section class="mission-empty"><i>▧</i><h3>No hay implementaciones en esta selección</h3><p>Selecciona una misión del catálogo maestro para adaptarla a una sede.</p><a class="btn primary" href="{{ $phaseUrl(1) }}">Elegir misión maestra　→</a></section>
   @endforelse</div>
 @else
   <div class="mission-operating-head"><nav><a class="{{ $operatingView === 'misiones' ? 'active' : '' }}" href="{{ $operatingUrl(['vista' => 'misiones']) }}">Misiones operativas</a><a class="{{ $operatingView === 'tareas' ? 'active' : '' }}" href="{{ $operatingUrl(['vista' => 'tareas']) }}">Tareas y robots</a></nav><button class="btn" type="button">⇩　Exportar CSV</button></div>
   @if($operatingView === 'tareas')
     <div class="task-robot-heading"><p class="eyebrow">TAREAS Y EQUIPOS</p><h2>Gestiona el robot desde su trabajo.</h2><p>Consulta las tareas, su secuencia y el equipo asignado. Las acciones de iniciar, pausar y cancelar se aplican a la misión completa.</p></div>
     <form class="task-filters" method="get" action="{{ route('management') }}"><input type="hidden" name="tab" value="misiones"><input type="hidden" name="client" value="{{ $client->id }}"><input type="hidden" name="fase" value="3"><input type="hidden" name="vista" value="tareas"><select name="estado" onchange="this.form.submit()"><option value="todas">Todos los estados</option><option value="pending" @selected($statusFilter === 'pending')>Pendientes</option><option value="running" @selected($statusFilter === 'running')>En progreso</option><option value="completed" @selected($statusFilter === 'completed')>Completadas</option></select><select name="robot" onchange="this.form.submit()"><option value="todos">Todos los robots</option>@foreach($state['robots'] as $robot)<option value="{{ $robot['id'] }}" @selected($robotFilter === $robot['id'])>{{ $robot['name'] }}</option>@endforeach</select></form>
     <section class="task-table"><header><span>Tarea / misión</span><span>Cliente / sede</span><span>Robot</span><span>Estado</span><span>Administrar</span></header>@forelse($taskRows as $row)<article><div><b>{{ $row['task']['name'] }}</b><small>{{ $row['mission']['name'] }}</small></div><div>{{ $client->name }}<small>{{ $siteOptions[$row['mission']['siteId']] ?? 'Sin sede' }}</small></div><div>{{ $row['robot']['name'] ?? 'Sin asignar' }}</div><span class="summary-status {{ $row['task']['status'] ?? 'pending' }}">{{ $badge($row['task']['status'] ?? 'pending') }}</span><a href="{{ $url('misiones', ['mission' => $row['mission']['id']]) }}">Abrir　→</a></article>@empty<div class="task-empty">No hay tareas con estos filtros. Implementa una misión maestra o crea una misión operativa.</div>@endforelse</section>
   @else
   <div class="mission-status-tabs">@foreach(['todas' => 'Todas', 'activas' => 'Activas', 'pendientes' => 'Pendientes', 'historial' => 'Historial'] as $key => $label)<a class="{{ $statusFilter === $key ? 'active' : '' }}" href="{{ $operatingUrl(['estado' => $key]) }}">{{ $label }}</a>@endforeach</div>
   @if($filteredMissions->isEmpty())
     <section class="mission-empty"><i>⚑</i><h3>Dale a tu flota su primera misión</h3><p>Crea una o varias tareas, asigna un robot compatible y sigue su resultado.</p><button class="btn primary" data-open="new-mission">＋ Nueva misión</button></section>
   @else
     <div class="operational-missions">@foreach($filteredMissions as $mission)<article><span class="summary-status {{ $mission['status'] }}">{{ $badge($mission['status']) }}</span><h3>{{ $mission['name'] }}</h3><p>{{ $mission['siteName'] ?? ($state['sites'][0]['name'] ?? 'Sin sede') }} · {{ count($mission['tasks'] ?? []) }} tareas</p><a href="{{ $url('misiones', ['mission' => $mission['id']]) }}">Ver misión　→</a></article>@endforeach</div>
   @endif
   @endif
 @endif
</section>
