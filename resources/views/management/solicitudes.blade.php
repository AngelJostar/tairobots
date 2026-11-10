@php
  $roles = [
    ['Superadministrador', 'Registrar y consultar', 'Operar', 'Gestionar', 'Gestionar', 'Permitido'],
    ['Administrador de cliente', 'Registrar y consultar', 'Operar', 'Gestionar', 'Gestionar', 'No permitido'],
    ['Supervisor', 'Consultar', 'Operar', 'Gestionar', 'Consultar', 'No permitido'],
    ['Operador', 'Consultar', 'Operar', 'Consultar', 'Consultar', 'No permitido'],
    ['Técnico de mantenimiento', 'Consultar', 'Consultar', 'Gestionar', 'Consultar', 'No permitido'],
    ['Solo consulta', 'Consultar', 'Consultar', 'Consultar', 'Consultar', 'No permitido'],
  ];
@endphp
<section class="access-dashboard">
  <header class="access-heading"><div><p class="eyebrow">GESTIÓN DE ROBOTS</p><h1>Acceso y permisos.</h1><p>El superadministrador tiene acceso a todos los clientes y sedes.</p></div></header>
  <div class="summary-tabs access-tabs"><span class="active">Tu espacio</span><span>{{ $client->name }} · {{ $state['sites'][0]['name'] ?? 'Sin sede' }}</span><a>⌕　Filtrar　{{ count($state['robots']) }}</a></div>
  <p class="access-profile-note">♢　 Perfil activo: <b>Superadministrador.</b> Acceso a todos los clientes de tu organización. El permiso de superadministrador se valida en el servidor.</p>
  <section class="role-matrix"><header><h2>Alcances por rol</h2><button class="btn" type="button" data-open="my-access">♧　 Mi acceso</button></header><div class="role-table"><div class="role-head"><span>Rol</span><span>Robots</span><span>Misiones y comandos</span><span>Mantenimiento</span><span>Configuración</span><span>Reasignar cliente</span></div>@foreach($roles as $role)<article>@foreach($role as $index => $permission)<span @if($index === 0) class="role-name" @endif>{{ $permission }}</span>@endforeach</article>@endforeach</div><footer>Los demás roles se muestran como referencia. El registro de una cuenta no otorga permisos de administración.</footer></section>
  <dialog class="dialog access-dialog" id="my-access" aria-labelledby="my-access-title">
    <div class="dialog-head"><div><p class="eyebrow">TAIROBOTS · SIMULACIÓN</p><h2 id="my-access-title">Tu perfil de superadministrador</h2></div><button type="button" class="close" data-close aria-label="Cerrar">×</button></div>
    <div class="access-dialog-body"><div class="access-user"><b>{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</b><div><h3>{{ auth()->user()->name }}</h3><p>Superadministrador</p></div></div><p class="access-scope">♙　 Todos los clientes · Todas las sedes autorizadas</p><p class="access-description">Tu acceso está vinculado a tu cuenta de Tairobots. Los registros se guardan en este espacio y permanecen al cerrar sesión.</p><form method="post" action="{{ route('logout') }}">@csrf<button class="btn primary">⇥　 Cerrar sesión</button></form></div>
  </dialog>
</section>
