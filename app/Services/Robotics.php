<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Robotics
{
    public const ACTIVE = ['assigned', 'accepted', 'running', 'paused'];

    public static function now(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public static function labels(): array
    {
        return json_decode(file_get_contents(database_path('data/labels.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function ensure(bool $ok, string $message): void
    {
        if (! $ok) {
            throw ValidationException::withMessages(['operation' => $message]);
        }
    }

    public function index(array $items, string $id): int
    {
        foreach ($items as $key => $item) {
            if ($item['id'] === $id) {
                return $key;
            }
        }abort(404, 'Registro no encontrado.');
    }

    public function audit(array &$s, string $actor, string $action, string $detail, ?string $site = null, ?string $robot = null): void
    {
        $s['events'][] = ['id' => (string) Str::uuid(), 'at' => self::now(), 'actor' => $actor, 'action' => $action, 'detail' => $detail, 'siteId' => $site, 'robotId' => $robot];
    }

    public function validate(array $data, array $rules): array
    {
        return Validator::make($data, $rules)->validate();
    }

    public function site(array $s, string $id): array
    {
        return $s['sites'][$this->index($s['sites'], $id)];
    }

    public function rules(string $action): array
    {
        $name = 'required|string|min:2|max:100';
        $id = 'required|string|max:100';

        return match ($action) {
            'client' => ['name' => $name, 'industry' => 'required|string|max:160'],
            'site' => ['name' => $name, 'environment' => $name, 'building' => $name, 'floor' => $name, 'frame' => $name],
            'robot' => ['name' => $name, 'serial' => $name, 'siteId' => $id, 'type' => 'required|in:humanoid,drone,quadruped,amr,arm,service,cleaner', 'fleet' => $name, 'manufacturer' => $name, 'model' => $name, 'responsible' => $name, 'speed' => 'required|numeric|min:0.1|max:5', 'payload' => 'required|numeric|min:0|max:500', 'capabilities' => 'required|array|min:1|max:10', 'capabilities.*' => 'in:move,fly,manipulate,carry,camera,audio,sensors,clean,guide,dock'],
            'mission' => ['name' => $name, 'siteId' => $id, 'robotId' => 'nullable|string|max:100', 'priority' => 'required|in:low,normal,high', 'scheduledAt' => 'nullable|date', 'repeatMinutes' => 'nullable|integer|min:1|max:525600', 'minBattery' => 'required|numeric|min:20|max:100', 'payload' => 'required|numeric|min:0|max:500', 'resources' => 'nullable|array|max:5', 'resources.*' => 'string|max:100', ...$this->taskRules()],
            'assign' => ['id' => $id, 'robotId' => 'nullable|string|max:100'],
            'command' => ['robotId' => $id, 'missionId' => 'nullable|string|max:100', 'kind' => 'required|in:start,pause,resume,cancel,return_base,land,charge', 'key' => 'required|uuid'],
            'simulation' => ['robotId' => $id, 'scenario' => 'required|in:disconnect,low_battery,fault,recover'],
            'maintenance' => ['robotId' => $id, 'title' => $name, 'kind' => 'required|in:preventive,corrective', 'technician' => 'nullable|string|max:100', 'scheduledDate' => 'nullable|date', 'notes' => 'nullable|string|max:3000', 'parts' => 'nullable|string|max:1000'],
            'maintenance.close' => ['id' => $id, 'notes' => 'required|string|min:3|max:3000'],
            'alert' => ['id' => $id, 'status' => 'required|in:open,acknowledged,resolved', 'comment' => 'required|string|min:3|max:1000'],
            'zone' => ['siteId' => $id, 'name' => $name, 'kind' => 'required|in:restricted,work,charging,poi', 'x' => 'required|numeric|min:0|max:99', 'y' => 'required|numeric|min:0|max:69', 'w' => 'required|numeric|min:1|max:100', 'h' => 'required|numeric|min:1|max:70'],
            'rule' => ['siteId' => $id, 'name' => $name, 'event' => 'required|in:low_battery,fault,offline', 'action' => 'required|in:alert,pause'],
            'settings' => ['retentionDays' => 'required|integer|min:7|max:3650'],
            'transfer' => ['robotId' => $id, 'targetClientId' => $id, 'siteId' => $id, 'reason' => 'required|string|min:5|max:500'],
            'confirm','cancel','zone.delete','rule.toggle' => ['id' => $id],
            default => []
        };
    }

    public function taskRules(): array
    {
        return ['tasks' => 'required|array|min:1|max:20', 'tasks.*.name' => 'required|string|min:2|max:100', 'tasks.*.capability' => 'required|in:move,fly,manipulate,carry,camera,audio,sensors,clean,guide,dock', 'tasks.*.x' => 'required|numeric|min:0|max:100', 'tasks.*.y' => 'required|numeric|min:0|max:70', 'tasks.*.duration' => 'required|numeric|min:2|max:3600', 'tasks.*.confirm' => 'nullable|boolean', 'tasks.*.targetIds' => 'nullable|array|max:20', 'tasks.*.targetIds.*' => 'string|max:100'];
    }

    public function createMission(array &$s, array $p, string $actor): array
    {
        $this->site($s, $p['siteId']);
        $resources = $p['resources'] ?? [];
        foreach ($resources as $id) {
            $resource = $s['resources'][$this->index($s['resources'], $id)];
            $this->ensure($resource['siteId'] === $p['siteId'], 'Recurso de otra sede.');
        }
        $tasks = [];
        foreach ($p['tasks'] as $n => $t) {
            foreach ($s['zones'] as $z) {
                $this->ensure(! ($z['siteId'] === $p['siteId'] && $z['kind'] === 'restricted' && $this->inside($t['x'], $t['y'], $z)), 'Destino dentro de una zona restringida.');
            }
            if ($t['capability'] === 'dock') {
                $docks = array_filter($s['zones'], fn ($z) => $z['siteId'] === $p['siteId'] && $z['kind'] === 'charging' && in_array($z['id'], $resources) && $this->inside($t['x'], $t['y'], $z));
                $this->ensure(count($docks) > 0, 'La carga requiere reservar una estación y un destino dentro de ella.');
            }
            $tasks[] = ['name' => $t['name'], 'capability' => $t['capability'], 'x' => (float) $t['x'], 'y' => (float) $t['y'], 'duration' => (float) $t['duration'], 'confirm' => (bool) ($t['confirm'] ?? false), 'targetIds' => $t['targetIds'] ?? [], 'id' => 't'.($n + 1), 'status' => 'pending', 'progress' => 0, 'dependsOn' => $n ? ['t'.$n] : [], 'evidence' => null];
        }
        $now = self::now();
        $scheduled = empty($p['scheduledAt']) ? null : (is_numeric($p['scheduledAt']) ? (int) $p['scheduledAt'] : strtotime($p['scheduledAt']) * 1000);
        $m = ['id' => (string) Str::uuid(), 'name' => $p['name'], 'siteId' => $p['siteId'], 'robotId' => null, 'preferredRobot' => $p['robotId'] ?? null, 'priority' => $p['priority'] ?? 'normal', 'status' => $scheduled ? 'scheduled' : 'draft', 'tasks' => $tasks, 'resources' => $resources, 'minBattery' => (float) ($p['minBattery'] ?? 25), 'payload' => (float) ($p['payload'] ?? 0), 'scheduledAt' => $scheduled, 'repeatMinutes' => empty($p['repeatMinutes']) ? null : (int) $p['repeatMinutes'], 'createdAt' => $now, 'updatedAt' => $now, 'lastTick' => $now, 'requestedBy' => $actor, 'reason' => null, 'evidence' => [], 'nextOccurrence' => null];
        if ($m['preferredRobot']) {
            $r = $s['robots'][$this->index($s['robots'], $m['preferredRobot'])];
            $this->ensure($r['siteId'] === $m['siteId'] && count(array_diff(array_column($tasks, 'capability'), $r['capabilities'])) === 0 && $m['payload'] <= $r['limits']['payload'], 'Robot incompatible con sede, capacidades o carga.');
        }
        $s['missions'][] = $m;
        $this->audit($s, $actor, 'mission.created', $m['name'], $m['siteId']);

        return $m;
    }

    public function inside(float $x, float $y, array $z): bool
    {
        return $x >= $z['x'] && $x <= $z['x'] + $z['w'] && $y >= $z['y'] && $y <= $z['y'] + $z['h'];
    }

    public function intersects(float $x1, float $y1, float $x2, float $y2, array $z): bool
    {
        $low = 0;
        $high = 1;
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        foreach ([[-$dx, $x1 - $z['x']], [$dx, $z['x'] + $z['w'] - $x1], [-$dy, $y1 - $z['y']], [$dy, $z['y'] + $z['h'] - $y1]] as [$p,$q]) {
            if ($p == 0) {
                if ($q < 0) {
                    return false;
                }
            } else {
                $t = $q / $p;
                if ($p < 0) {
                    $low = max($low, $t);
                } else {
                    $high = min($high, $t);
                }
                if ($low > $high) {
                    return false;
                }
            }
        }

        return true;
    }

    public function compatibility(array $s, array $r, array $m): ?string
    {
        if ($r['siteId'] !== $m['siteId'] || $r['frame'] !== $this->site($s, $m['siteId'])['frame']) {
            return 'Sede o marco de referencia incompatible.';
        }
        if ($r['blocked'] || $r['maintenanceDue'] < self::now() || in_array($r['status'], ['offline', 'fault', 'maintenance', 'charging'])) {
            return 'Robot no disponible o bloqueado por mantenimiento.';
        }
        if (self::now() - $r['lastSeen'] > 15000) {
            return 'Telemetría vencida.';
        }
        if ($r['battery'] < $m['minBattery'] || $m['payload'] > $r['limits']['payload']) {
            return 'Batería insuficiente o carga superior al límite.';
        }
        if (array_diff(array_column($m['tasks'], 'capability'), $r['capabilities'])) {
            return 'Capacidades incompatibles.';
        }
        foreach ($s['missions'] as $other) {
            if ($other['id'] !== $m['id'] && in_array($other['status'], self::ACTIVE) && ($other['robotId'] === $r['id'] || array_intersect($m['resources'], $other['resources']))) {
                return 'Robot o recurso reservado por otra misión.';
            }
        }
        foreach ($s['resources'] as $resource) {
            if (in_array($resource['id'], $m['resources']) && ! empty($resource['occupiedBy'])) {
                return 'Estación de carga ocupada.';
            }
        }
        foreach ($m['tasks'] as $t) {
            foreach ($s['zones'] as $z) {
                if ($z['siteId'] === $m['siteId'] && $z['kind'] === 'restricted' && $this->inside($t['x'], $t['y'], $z)) {
                    return 'Destino restringido.';
                }
            }
        }

        return null;
    }

    public function assign(array &$s, string $id, ?string $robotId, string $actor): void
    {
        $mi = $this->index($s['missions'], $id);
        $m = &$s['missions'][$mi];
        $this->ensure(in_array($m['status'], ['draft', 'scheduled']), 'La misión ya fue asignada o finalizada.');
        $this->ensure(! $m['scheduledAt'] || $m['scheduledAt'] <= self::now(), 'La misión todavía no alcanza su horario.');
        $selected = $robotId ?: $m['preferredRobot'];
        $candidates = array_filter($s['robots'], fn ($r) => ! $this->compatibility($s, $r, $m));
        usort($candidates, fn ($a, $b) => hypot($a['x'] - $m['tasks'][0]['x'], $a['y'] - $m['tasks'][0]['y']) <=> hypot($b['x'] - $m['tasks'][0]['x'], $b['y'] - $m['tasks'][0]['y']));
        $selected ??= $candidates[0]['id'] ?? null;
        $this->ensure((bool) $selected, 'No hay robots compatibles disponibles.');
        $ri = $this->index($s['robots'], $selected);
        $reason = $this->compatibility($s, $s['robots'][$ri], $m);
        $this->ensure(! $reason, $reason ?? '');
        $m['robotId'] = $selected;
        $m['status'] = 'assigned';
        $m['updatedAt'] = self::now();
        $m['reason'] = null;
        $s['robots'][$ri]['status'] = 'busy';
        $this->audit($s, $actor, 'mission.assigned', $m['name'], $m['siteId'], $selected);
    }

    public function command(array &$s, array $p, string $actor): void
    {
        $fingerprint = json_encode([$p['robotId'], $p['missionId'] ?? null, $p['kind']]);
        foreach ($s['commands'] as $c) {
            if ($c['key'] === $p['key']) {
                $this->ensure($c['fingerprint'] === $fingerprint, 'La clave de comando ya tiene otros parámetros.');

                return;
            }
        }
        $r = $s['robots'][$this->index($s['robots'], $p['robotId'])];
        $kind = $p['kind'];
        $this->ensure(in_array($kind, $r['commands']), 'Comando no soportado.');
        $this->ensure(! $r['blocked'] && ! in_array($r['status'], ['offline', 'fault', 'maintenance']) && self::now() - $r['lastSeen'] <= 15000, 'Robot desconectado, bloqueado o con telemetría vencida.');
        $this->ensure(! array_filter($s['commands'], fn ($c) => $c['robotId'] === $r['id'] && in_array($c['status'], ['requested', 'sent', 'received', 'executing'])), 'Ya hay un comando pendiente.');
        if (in_array($kind, ['start', 'pause', 'resume', 'cancel'])) {
            $m = $s['missions'][$this->index($s['missions'], $p['missionId'] ?? '')];
            $allowed = ['start' => ['assigned', 'accepted'], 'pause' => ['running'], 'resume' => ['paused'], 'cancel' => self::ACTIVE];
            $this->ensure($m['robotId'] === $r['id'] && in_array($m['status'], $allowed[$kind]), 'El estado de la misión no admite este comando.');
            if (in_array($kind, ['start', 'resume'])) {
                $reason = $this->compatibility($s, $r, $m);
                $this->ensure(! $reason, $reason ?? '');
            }
        } else {
            $this->ensure(! array_filter($s['missions'], fn ($m) => $m['robotId'] === $r['id'] && in_array($m['status'], self::ACTIVE)), 'Finaliza la misión actual primero.');
            $cap = $kind === 'land' ? 'fly' : ($kind === 'charge' ? 'dock' : (in_array('fly', $r['capabilities']) ? 'fly' : 'move'));
            $m = $this->createMission($s, ['name' => self::labels()['commandLabels'][$kind], 'siteId' => $r['siteId'], 'robotId' => $r['id'], 'tasks' => [['name' => self::labels()['commandLabels'][$kind], 'capability' => $cap, 'x' => $kind === 'charge' ? 16 : 15, 'y' => $kind === 'charge' ? 57 : 33, 'duration' => 5]], 'minBattery' => 20, 'resources' => $kind === 'charge' ? [$r['siteId'].'-dock'] : []], $actor);
            $this->assign($s, $m['id'], $r['id'], $actor);
        }
        $now = self::now();
        $s['commands'][] = ['id' => (string) Str::uuid(), 'key' => $p['key'], 'fingerprint' => $fingerprint, 'robotId' => $r['id'], 'siteId' => $r['siteId'], 'missionId' => $m['id'], 'kind' => $kind, 'status' => 'requested', 'requestedAt' => $now, 'expiresAt' => $now + 60000, 'updatedAt' => $now, 'actor' => $actor, 'timeline' => [['status' => 'requested', 'at' => $now, 'detail' => 'Solicitud guardada en la cola del simulador.']]];
        $this->audit($s, $actor, 'command.requested', $kind, $r['siteId'], $r['id']);
    }

    public function alert(array &$s, array $r, string $kind, string $title): void
    {
        foreach ($s['alerts'] as $alert) {
            if ($alert['robotId'] === $r['id'] && $alert['kind'] === $kind && $alert['status'] !== 'resolved') {
                return;
            }
        }
        $s['alerts'][] = ['id' => (string) Str::uuid(), 'siteId' => $r['siteId'], 'robotId' => $r['id'], 'kind' => $kind, 'title' => $title, 'severity' => $kind === 'fault' ? 'critical' : 'warning', 'status' => 'open', 'owner' => $r['responsible'], 'createdAt' => self::now(), 'comments' => [], 'resolution' => ''];
        foreach ($s['rules'] as $rule) {
            if ($rule['enabled'] && $rule['siteId'] === $r['siteId'] && $rule['event'] === $kind) {
                if ($rule['action'] === 'pause') {
                    foreach ($s['missions'] as &$m) {
                        if ($m['robotId'] === $r['id'] && in_array($m['status'], self::ACTIVE)) {
                            $m['status'] = 'paused';
                            $m['reason'] = 'Regla: '.$rule['name'];
                        }
                    }
                    unset($m);
                }
                $this->audit($s, 'Reglas', 'rule.triggered', $rule['name'], $r['siteId'], $r['id']);
            }
        }
        $this->audit($s, 'Simulador', 'alert.raised', $title, $r['siteId'], $r['id']);
    }

    public function mutate(array &$s, string $action, array $input, string $actor): void
    {
        $p = $this->validate($input, $this->rules($action));
        $now = self::now();
        switch ($action) {
            case 'site':$id = (string) Str::uuid();
                $s['sites'][] = $p + ['id' => $id, 'units' => 'm', 'width' => 100, 'height' => 70];
                $s['zones'][] = ['id' => $id.'-dock', 'siteId' => $id, 'name' => 'Estación de carga', 'kind' => 'charging', 'x' => 8, 'y' => 52, 'w' => 16, 'h' => 10];
                $s['resources'][] = ['id' => $id.'-dock', 'siteId' => $id, 'name' => 'Estación de carga', 'kind' => 'charger', 'occupiedBy' => null];
                break;
            case 'robot':$site = $this->site($s, $p['siteId']);
                $this->ensure(! array_filter($s['robots'], fn ($r) => $r['serial'] === $p['serial']), 'El número de serie ya está registrado.');
                $caps = $p['capabilities'];
                $commands = ['start', 'pause', 'resume', 'cancel'];
                if (array_intersect($caps, ['move', 'fly'])) {
                    $commands[] = 'return_base';
                }
                if (in_array('fly', $caps)) {
                    $commands[] = 'land';
                }
                if (in_array('dock', $caps)) {
                    $commands[] = 'charge';
                }
                $s['robots'][] = $p + ['id' => (string) Str::uuid(), 'limits' => ['speed' => (float) $p['speed'], 'payload' => (float) $p['payload']], 'owner' => $s['name'], 'battery' => 100, 'status' => 'available', 'lastSeen' => $now, 'x' => 15, 'y' => 33, 'frame' => $site['frame'], 'maintenanceDue' => $now + 30 * 86400000, 'blocked' => false, 'connection' => 'simulator', 'source' => 'simulated', 'commands' => $commands, 'documents' => [], 'photo' => '', 'software' => 'sim/1.0.0', 'area' => 'Área de operación'];
                break;
            case 'mission':$this->createMission($s, $p, $actor);

                return;
            case 'assign':$this->assign($s, $p['id'], $p['robotId'] ?? null, $actor);

                return;
            case 'command':$this->command($s, $p, $actor);

                return;
            case 'confirm':$mi = $this->index($s['missions'], $p['id']);
                $this->ensure($s['missions'][$mi]['status'] === 'running', 'La misión no está en ejecución.');
                $found = false;
                foreach ($s['missions'][$mi]['tasks'] as &$t) {
                    if ($t['status'] === 'awaiting_confirmation') {
                        $t['status'] = 'completed';
                        $found = true;
                        break;
                    }
                }
                unset($t);
                $this->ensure($found, 'No hay una tarea pendiente de confirmación.');
                break;
            case 'cancel':$mi = $this->index($s['missions'], $p['id']);
                $this->ensure(in_array($s['missions'][$mi]['status'], ['draft', 'scheduled']), 'Usa el comando de cancelación para una misión activa.');
                $s['missions'][$mi]['status'] = 'cancelled';
                break;
            case 'simulation':$ri = $this->index($s['robots'], $p['robotId']);
                $r = &$s['robots'][$ri];
                $scenario = $p['scenario'];
                if ($scenario === 'recover') {
                    $this->ensure(! $r['blocked'], 'Cierra el mantenimiento antes de recuperar el robot.');
                    foreach ($s['resources'] as &$resource) {
                        if (($resource['occupiedBy'] ?? null) === $r['id']) {
                            $resource['occupiedBy'] = null;
                        }
                    }
                    unset($resource);
                    $r['status'] = array_filter($s['missions'], fn ($m) => $m['robotId'] === $r['id'] && in_array($m['status'], self::ACTIVE)) ? 'busy' : 'available';
                    $r['battery'] = max(80, $r['battery']);
                    $r['lastSeen'] = $now;
                } else {
                    if ($scenario === 'low_battery') {
                        $r['battery'] = 12;
                    } else {
                        $r['status'] = $scenario === 'disconnect' ? 'offline' : 'fault';
                    }
                    $this->alert($s, $r, $scenario === 'disconnect' ? 'offline' : $scenario, 'Simulación: '.$scenario);
                }
                unset($r);
                break;
            case 'maintenance':$ri = $this->index($s['robots'], $p['robotId']);
                $r = &$s['robots'][$ri];
                $this->ensure(! array_filter($s['missions'], fn ($m) => $m['robotId'] === $r['id'] && in_array($m['status'], self::ACTIVE)), 'Cancela o finaliza la misión antes del mantenimiento.');
                $this->ensure(! $r['blocked'] && $r['status'] !== 'charging', 'Robot bloqueado o conectado a una estación.');
                $r['blocked'] = true;
                $r['status'] = 'maintenance';
                $p['notes'] = $p['notes'] ?? '';
                $p['parts'] = $p['parts'] ?? '';
                $p['technician'] = $p['technician'] ?? $actor;
                $dueAt = empty($p['scheduledDate']) ? $now + 86400000 : strtotime($p['scheduledDate']) * 1000;
                $s['maintenance'][] = $p + ['id' => (string) Str::uuid(), 'siteId' => $r['siteId'], 'status' => 'open', 'createdAt' => $now, 'dueAt' => $dueAt, 'completedAt' => null];
                unset($r);
                break;
            case 'maintenance.close':$oi = $this->index($s['maintenance'], $p['id']);
                $o = &$s['maintenance'][$oi];
                $this->ensure($o['status'] === 'open', 'La orden ya fue cerrada.');
                $o['status'] = 'completed';
                $o['completedAt'] = $now;
                $o['notes'] = $p['notes'];
                $ri = $this->index($s['robots'], $o['robotId']);
                $s['robots'][$ri]['blocked'] = false;
                $s['robots'][$ri]['status'] = 'available';
                $s['robots'][$ri]['maintenanceDue'] = $now + 30 * 86400000;
                unset($o);
                break;
            case 'alert':$ai = $this->index($s['alerts'], $p['id']);
                $s['alerts'][$ai]['status'] = $p['status'];
                $s['alerts'][$ai]['comments'][] = ['author' => $actor, 'text' => $p['comment'], 'at' => $now];
                if ($p['status'] === 'resolved') {
                    $s['alerts'][$ai]['resolution'] = $p['comment'];
                }
                break;
            case 'zone':$this->site($s, $p['siteId']);
                $this->ensure($p['x'] + $p['w'] <= 100 && $p['y'] + $p['h'] <= 70, 'Zona fuera del plano.');
                $s['zones'][] = $p + ['id' => (string) Str::uuid()];
                break;
            case 'zone.delete':$zi = $this->index($s['zones'], $p['id']);
                $this->ensure(! array_filter($s['resources'], fn ($r) => $r['id'] === $p['id']), 'La estación está asociada a un recurso de carga.');
                array_splice($s['zones'], $zi, 1);
                break;
            case 'rule':$this->site($s, $p['siteId']);
                $s['rules'][] = $p + ['id' => (string) Str::uuid(), 'enabled' => true];
                break;
            case 'rule.toggle':$ri = $this->index($s['rules'], $p['id']);
                $s['rules'][$ri]['enabled'] = ! $s['rules'][$ri]['enabled'];
                break;
            case 'settings':$s['settings'] = ['retentionDays' => (int) $p['retentionDays']];
                break;
            default:abort(404);
        }
        $this->audit($s, $actor, $action, $p['name'] ?? $p['title'] ?? $p['id'] ?? $p['robotId'] ?? 'Configuración actualizada');
    }

    public function pulse(array &$s): void
    {
        $now = self::now();
        $delta = min(3, max(0, ($now - $s['lastPulse']) / 1000));
        if ($delta < 0.9) {
            return;
        }
        $s['lastPulse'] = $now;
        foreach ($s['robots'] as &$r) {
            if ($r['status'] !== 'offline') {
                $r['lastSeen'] = $now;
            }
            if ($r['status'] === 'charging') {
                $r['battery'] = min(100, $r['battery'] + $delta * .3);
                if ($r['battery'] >= 100) {
                    $r['status'] = 'available';
                    foreach ($s['resources'] as &$resource) {
                        if (($resource['occupiedBy'] ?? null) === $r['id']) {
                            $resource['occupiedBy'] = null;
                        }
                    }
                    unset($resource);
                }
            }
            if ($r['battery'] < 20) {
                $this->alert($s, $r, 'low_battery', 'Batería por debajo del umbral');
            }
            if ($r['maintenanceDue'] < $now) {
                $this->alert($s, $r, 'maintenance', 'Mantenimiento preventivo vencido');
            }
        }
        unset($r);
        $scheduled = array_filter($s['missions'], fn ($m) => $m['status'] === 'scheduled' && $m['scheduledAt'] <= $now);
        usort($scheduled, fn ($a, $b) => (['high' => 0, 'normal' => 1, 'low' => 2][$a['priority']]) <=> (['high' => 0, 'normal' => 1, 'low' => 2][$b['priority']]));
        foreach ($scheduled as $m) {
            $snapshot = $s;
            try {
                $this->assign($s, $m['id'], $m['preferredRobot'], 'Programador');
                $current = $s['missions'][$this->index($s['missions'], $m['id'])];
                $this->command($s, ['robotId' => $current['robotId'], 'missionId' => $m['id'], 'kind' => 'start', 'key' => 'scheduled-'.$m['id']], 'Programador');
            } catch (ValidationException $e) {
                $s = $snapshot;
                $mi = $this->index($s['missions'], $m['id']);
                $s['missions'][$mi]['reason'] = collect($e->errors())->flatten()->first();
            }
        }
        foreach ($s['commands'] as &$c) {
            if (in_array($c['status'], ['confirmed', 'rejected', 'expired', 'unknown'])) {
                continue;
            }
            $ri = $this->index($s['robots'], $c['robotId']);
            $mi = $this->index($s['missions'], $c['missionId']);
            $r = &$s['robots'][$ri];
            $m = &$s['missions'][$mi];
            $detail = 'Adaptador simulador';
            if ($c['expiresAt'] <= $now && in_array($c['status'], ['requested', 'sent', 'received'])) {
                $next = 'expired';
            } elseif (in_array($r['status'], ['offline', 'fault', 'maintenance']) || $r['blocked']) {
                $next = $c['status'] === 'requested' ? 'rejected' : 'unknown';
            } elseif ($c['status'] === 'requested') {
                $next = 'sent';
            } elseif ($c['status'] === 'sent') {
                $next = 'received';
                if ($m['status'] === 'assigned' && ! in_array($c['kind'], ['pause', 'cancel'])) {
                    $m['status'] = 'accepted';
                }
            } elseif ($c['status'] === 'received') {
                $allowed = ['start' => ['assigned', 'accepted'], 'resume' => ['paused'], 'pause' => ['running'], 'cancel' => self::ACTIVE, 'charge' => ['assigned', 'accepted'], 'land' => ['assigned', 'accepted'], 'return_base' => ['assigned', 'accepted']];
                $reason = in_array($m['status'], $allowed[$c['kind']]) ? null : 'El estado de la misión cambió.';
                if (! $reason && ! in_array($c['kind'], ['pause', 'cancel'])) {
                    $reason = $this->compatibility($s, $r, $m);
                }
                if ($reason) {
                    $next = 'rejected';
                    $detail = $reason;
                } else {
                    $next = 'executing';
                    $m['status'] = match ($c['kind']) {
                        'pause' => 'paused','cancel' => 'cancelled',default => 'running'
                    };
                    $m['updatedAt'] = $now;
                    if ($m['status'] === 'cancelled') {
                        $r['status'] = 'available';
                    } else {
                        $r['status'] = 'busy';
                    }
                }
            } else {
                $next = 'confirmed';
            }
            $c['status'] = $next;
            $c['updatedAt'] = $now;
            $c['timeline'][] = ['status' => $next, 'at' => $now, 'detail' => $detail];
            unset($r,$m);
        }
        unset($c);
        $repeat = [];
        foreach ($s['missions'] as &$m) {
            if ($m['status'] !== 'running') {
                continue;
            }
            $ri = $this->index($s['robots'], $m['robotId']);
            $r = &$s['robots'][$ri];
            if (in_array($r['status'], ['offline', 'fault', 'maintenance']) || $r['blocked'] || $r['battery'] < 15) {
                $m['status'] = 'paused';
                $m['reason'] = 'Protección local simulada: misión pausada.';

                continue;
            }
            foreach ($m['tasks'] as &$t) {
                if ($t['status'] === 'completed') {
                    continue;
                }
                if ($t['status'] === 'awaiting_confirmation') {
                    break;
                }
                $t['status'] = 'running';
                $distance = hypot($t['x'] - $r['x'], $t['y'] - $r['y']);
                $mobile = count(array_intersect($r['capabilities'], ['move', 'fly'])) > 0;
                if ($mobile && $distance > .2) {
                    $step = min($distance, $delta * max(.1, $r['limits']['speed']) * 6);
                    $nx = $r['x'] + ($t['x'] - $r['x']) / $distance * $step;
                    $ny = $r['y'] + ($t['y'] - $r['y']) / $distance * $step;
                    $blocked = false;
                    foreach ($s['zones'] as $z) {
                        if ($z['siteId'] === $r['siteId'] && $z['kind'] === 'restricted' && $this->intersects($r['x'], $r['y'], $nx, $ny, $z)) {
                            $blocked = true;
                            break;
                        }
                    }
                    if ($blocked) {
                        $m['status'] = 'paused';
                        $m['reason'] = 'Trayecto bloqueado por zona restringida.';
                    } else {
                        $r['x'] = $nx;
                        $r['y'] = $ny;
                    }
                } else {
                    $t['progress'] = min(1, $t['progress'] + $delta / $t['duration']);
                }
                $r['battery'] = max(0, $r['battery'] - $delta * ($r['type'] === 'drone' ? .08 : .018));
                if ($t['progress'] >= 1) {
                    $t['evidence'] = 'SIM · '.$t['name'].' · '.$r['frame'].' · '.date(DATE_ATOM);
                    $t['status'] = ! empty($t['confirm']) ? 'awaiting_confirmation' : 'completed';
                    $m['evidence'][] = $t['evidence'];
                }
                break;
            }
            unset($t);
            if (! array_filter($m['tasks'], fn ($t) => $t['status'] !== 'completed')) {
                $m['status'] = 'completed';
                $m['updatedAt'] = $now;
                $this->audit($s, 'Simulador', 'mission.completed', $m['name'], $m['siteId'], $r['id']);
                $r['status'] = end($m['tasks'])['capability'] === 'dock' ? 'charging' : 'available';
                if ($r['status'] === 'charging') {
                    foreach ($s['resources'] as &$resource) {
                        if ($resource['kind'] === 'charger' && in_array($resource['id'], $m['resources'])) {
                            $resource['occupiedBy'] = $r['id'];
                        }
                    }
                    unset($resource);
                }
                if ($m['repeatMinutes'] && ! $m['nextOccurrence']) {
                    $next = $m;
                    $next['id'] = (string) Str::uuid();
                    $m['nextOccurrence'] = $next['id'];
                    $next['nextOccurrence'] = null;
                    $next['status'] = 'scheduled';
                    $next['robotId'] = null;
                    $next['scheduledAt'] = $now + $m['repeatMinutes'] * 60000;
                    $next['createdAt'] = $now;
                    $next['evidence'] = [];
                    foreach ($next['tasks'] as &$task) {
                        $task['status'] = 'pending';
                        $task['progress'] = 0;
                        $task['evidence'] = null;
                    }
                    unset($task);
                    $repeat[] = $next;
                }
            }
            unset($r);
        }
        unset($m);
        $s['missions'] = array_merge($s['missions'], $repeat);
    }
}
