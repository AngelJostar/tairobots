<?php

namespace App\Services;

use App\Models\CatalogSetting;
use App\Models\MissionTemplate;
use Illuminate\Support\Str;

class MissionCatalog
{
    public function __construct(public Robotics $engine) {}

    public function template(array $input, string $actor): void
    {
        $p = $this->engine->validate($input, ['name' => 'required|string|min:3|max:100', 'categoryId' => 'required|string', 'subcategoryId' => 'required|string', 'instructions' => 'required|string|min:3|max:5000', 'completion' => 'required|string|min:3|max:5000', 'steps' => 'required|array|min:1|max:20', 'steps.*.name' => 'required|string|min:2|max:100', 'steps.*.capability' => 'required|in:move,fly,manipulate,carry,camera,audio,sensors,clean,guide,dock', 'parentId' => 'nullable|string']);
        $categories = CatalogSetting::findOrFail(1)->categories;
        $category = $categories[$this->engine->index($categories, $p['categoryId'])];
        $sub = $category['subcategories'][$this->engine->index($category['subcategories'], $p['subcategoryId'])];
        $parent = empty($p['parentId']) ? null : MissionTemplate::findOrFail($p['parentId'])->data;
        $id = (string) Str::uuid();
        MissionTemplate::create(['id' => $id, 'data' => $p + ['id' => $id, 'category' => $category['name'], 'subcategory' => $sub['name'], 'revision' => ($parent['revision'] ?? 0) + 1, 'parentId' => $parent['id'] ?? null, 'createdAt' => Robotics::now(), 'author' => $actor]]);
    }

    public function instantiate(array &$s, array $p, string $actor): void
    {
        $this->engine->validate($p, ['templateId' => 'required|string', 'siteId' => 'required|string']);
        $t = MissionTemplate::findOrFail($p['templateId'])->data;
        $site = $this->engine->site($s, $p['siteId']);
        $tasks = array_map(fn ($task) => $task + ['targetIds' => [], 'x' => 65, 'y' => 33, 'duration' => 8, 'confirm' => true], $t['steps']);
        $s['implementations'][] = ['id' => (string) Str::uuid(), 'siteId' => $site['id'], 'name' => $t['name'], 'template' => $t, 'version' => 1, 'status' => 'draft', 'instructions' => '', 'completion' => $t['completion'], 'frame' => $site['frame'], 'units' => 'm', 'building' => $site['building'], 'floor' => $site['floor'], 'mapNotes' => 'Plano de simulación de la sede.', 'additional' => '', 'planRequired' => false, 'integrations' => [], 'tasks' => $tasks, 'resources' => [], 'payload' => 0, 'minBattery' => 25, 'assets' => [], 'createdAt' => Robotics::now(), 'updatedAt' => Robotics::now(), 'approvedBy' => null, 'approvedAt' => null, 'missions' => [], 'workflow' => ['step' => 0, 'savedSteps' => []], 'perception' => ['objective' => '', 'behavior' => 'observe', 'targets' => [], 'allowedArea' => '', 'conditions' => '', 'distanceMeters' => 2, 'onLost' => 'pause_and_alert', 'onAmbiguous' => 'pause_and_confirm', 'connectorRef' => '']];
        if ($t['name'] === 'Custodia y seguimiento de mascota') {
            $n = array_key_last($s['implementations']);
            $target = (string) Str::uuid();
            $s['implementations'][$n]['perception']['behavior'] = 'follow';
            $s['implementations'][$n]['perception']['objective'] = 'Seguir a la mascota dentro del área autorizada.';
            $s['implementations'][$n]['perception']['targets'][] = ['id' => $target, 'kind' => 'pet', 'name' => 'Mi mascota', 'description' => '', 'distinguishing' => '', 'method' => 'visual_reference', 'marker' => '', 'assetIds' => [], 'authorized' => true, 'required' => true];
            foreach ($s['implementations'][$n]['tasks'] as &$task) {
                $task['targetIds'] = [$target];
            }
            unset($task);
        }
        $this->engine->audit($s, $actor, 'implementation.created', $t['name'], $site['id']);
    }

    public function change(array &$i): void
    {
        $i['version']++;
        $i['updatedAt'] = Robotics::now();
        $i['status'] = 'draft';
        $i['approvedBy'] = null;
        $i['approvedAt'] = null;
    }

    public function update(array &$s, array $p, string $actor): void
    {
        $n = $this->engine->index($s['implementations'] ?? [], $p['id'] ?? '');
        $i = &$s['implementations'][$n];
        $this->engine->ensure((int) ($p['implementationVersion'] ?? 0) === $i['version'], 'La implementación cambió. Actualiza la pantalla.');
        $data = $this->engine->validate($p, ['name' => 'required|string|min:3|max:100', 'instructions' => 'nullable|string|max:5000', 'completion' => 'nullable|string|max:5000', 'frame' => 'required|string|max:100', 'building' => 'nullable|string|max:100', 'floor' => 'nullable|string|max:100', 'mapNotes' => 'required|string|min:3|max:5000', 'additional' => 'nullable|string|max:5000', 'planRequired' => 'nullable|boolean', 'resources' => 'nullable|array|max:20', 'resources.*' => 'string|max:100', 'payload' => 'required|numeric|min:0|max:500', 'minBattery' => 'required|numeric|min:20|max:100', 'workflowStep' => 'required|integer|min:0|max:6', ...$this->engine->taskRules(),
            'integrations' => 'nullable|array|max:12', 'integrations.*.name' => 'required|string|max:100', 'integrations.*.url' => 'required|url:https|max:500', 'integrations.*.purpose' => 'required|string|max:1000', 'integrations.*.credentialRef' => 'nullable|string|max:100', 'integrations.*.required' => 'nullable|boolean',
            'perception.objective' => 'nullable|string|max:3000', 'perception.behavior' => 'required|in:observe,follow,deliver,inspect,guide', 'perception.allowedArea' => 'nullable|string|max:3000', 'perception.conditions' => 'nullable|string|max:3000', 'perception.distanceMeters' => 'required|numeric|min:0.5|max:20', 'perception.onLost' => 'required|in:pause_and_alert,request_help', 'perception.connectorRef' => 'nullable|string|max:200',
            'perception.targets' => 'nullable|array|max:20', 'perception.targets.*.id' => 'required|string|max:100', 'perception.targets.*.kind' => 'required|in:pet,person,object,context', 'perception.targets.*.name' => 'required|string|max:100', 'perception.targets.*.description' => 'nullable|string|max:3000', 'perception.targets.*.distinguishing' => 'nullable|string|max:3000', 'perception.targets.*.method' => 'required|in:visual_reference,marker,manual_confirmation,sensor', 'perception.targets.*.marker' => 'nullable|string|max:300', 'perception.targets.*.assetIds' => 'nullable|array|max:40', 'perception.targets.*.assetIds.*' => 'string|max:100', 'perception.targets.*.authorized' => 'nullable|boolean', 'perception.targets.*.required' => 'nullable|boolean']);
        $data['resources'] = $data['resources'] ?? [];
        $data['integrations'] = $data['integrations'] ?? [];
        $data['planRequired'] = (bool) ($data['planRequired'] ?? false);
        $data['perception']['targets'] = $data['perception']['targets'] ?? [];
        $data['perception']['onAmbiguous'] = 'pause_and_confirm';
        foreach ($data['resources'] as $id) {
            $r = $s['resources'][$this->engine->index($s['resources'], $id)];
            $this->engine->ensure($r['siteId'] === $i['siteId'], 'Recurso de otra sede.');
        }
        $targets = $data['perception']['targets'];
        $ids = array_column($targets, 'id');
        $this->engine->ensure(count($ids) === count(array_unique($ids)), 'Identificadores de objetivos duplicados.');
        foreach ($targets as $target) {
            $this->engine->ensure(! array_diff($target['assetIds'] ?? [], array_column($i['assets'], 'id')), 'Archivo ajeno a la implementación.');
        }
        foreach ($data['tasks'] as $task) {
            $this->engine->ensure(! array_diff($task['targetIds'] ?? [], $ids), 'Objetivo de tarea inexistente.');
        }
        foreach ($data['integrations'] as $integration) {
            $url = parse_url($integration['url']);
            $this->engine->ensure(! isset($url['user'],$url['pass']) && ! isset($url['query']) && ! isset($url['fragment']) && ! isset($url['user']) && ! isset($url['pass']), 'La URL no debe contener credenciales, parámetros ni fragmentos.');
        }
        $data['workflow'] = ['step' => (int) $data['workflowStep'], 'savedSteps' => array_values(array_unique([...($i['workflow']['savedSteps'] ?? []), (int) $data['workflowStep']]))];
        unset($data['workflowStep']);
        $i = array_replace($i, $data);
        $this->change($i);
        $this->engine->audit($s, $actor, 'implementation.updated', $i['name'], $i['siteId']);
    }

    public function issues(array $i, array $s): array
    {
        $errors = [];
        if (strlen(trim($i['instructions'] ?? '')) < 3) {
            $errors[] = 'Completa las instrucciones específicas.';
        }
        if (strlen(trim($i['completion'] ?? '')) < 3) {
            $errors[] = 'Define la evidencia de finalización.';
        }
        if ($i['frame'] !== $this->engine->site($s, $i['siteId'])['frame']) {
            $errors[] = 'El marco debe coincidir con el plano de la sede.';
        }
        if ($i['planRequired'] && ! array_filter($i['assets'], fn ($a) => $a['kind'] === 'plan')) {
            $errors[] = 'Carga el plano requerido.';
        }
        if (array_filter($i['integrations'], fn ($a) => ! empty($a['required']))) {
            $errors[] = 'Las integraciones no están verificadas y no pueden ser obligatorias en esta simulación.';
        }
        $p = $i['perception'];
        $targets = $p['targets'] ?? [];
        if ($p['behavior'] === 'follow' && ! array_filter($targets, fn ($t) => ! empty($t['required']) && $t['kind'] !== 'context')) {
            $errors[] = 'El seguimiento requiere un objetivo obligatorio.';
        }
        if ($targets) {
            foreach (['objective' => 'objetivo', 'allowedArea' => 'área autorizada', 'conditions' => 'condiciones'] as $field => $label) {
                if (strlen(trim($p[$field] ?? '')) < 3) {
                    $errors[] = 'Completa '.$label.' de percepción.';
                }
            }
        }
        foreach ($targets as $t) {
            if (empty($t['required'])) {
                continue;
            }
            if (strlen(trim($t['description'] ?? '')) < 3 || strlen(trim($t['distinguishing'] ?? '')) < 3) {
                $errors[] = 'Completa identidad y rasgos de '.$t['name'].'.';
            }
            if (empty($t['authorized'])) {
                $errors[] = 'Autoriza las referencias de '.$t['name'].'.';
            }
            if ($t['method'] === 'visual_reference' && ! array_filter($i['assets'], fn ($a) => in_array($a['id'], $t['assetIds'] ?? []) && in_array($a['kind'], ['image', 'video']))) {
                $errors[] = 'Agrega imagen o video de '.$t['name'].'.';
            }
            if (in_array($t['method'], ['marker', 'sensor']) && empty($t['marker'])) {
                $errors[] = 'Define el marcador o sensor de '.$t['name'].'.';
            }
            if (! array_filter($i['tasks'], fn ($task) => in_array($t['id'], $task['targetIds'] ?? []))) {
                $errors[] = 'Vincula '.$t['name'].' con una tarea.';
            }
        }
        if (array_filter($targets, fn ($t) => ! empty($t['required']) && $t['method'] === 'visual_reference') && ! in_array('camera', array_column($i['tasks'], 'capability'))) {
            $errors[] = 'Agrega una tarea con cámara.';
        }
        if ($p['behavior'] === 'follow' && ! in_array('move', array_column($i['tasks'], 'capability'))) {
            $errors[] = 'Agrega una tarea de desplazamiento.';
        }

        return $errors;
    }

    public function approve(array &$s, array $p, string $actor): void
    {
        $n = $this->engine->index($s['implementations'] ?? [], $p['id'] ?? '');
        $i = &$s['implementations'][$n];
        $this->engine->ensure((int) ($p['implementationVersion'] ?? 0) === $i['version'], 'La implementación cambió.');
        $this->engine->ensure(! empty($p['reviewed']), 'Confirma la revisión operativa.');
        $errors = $this->issues($i, $s);
        $this->engine->ensure(! $errors, implode(' ', $errors));
        $i['status'] = 'approved';
        $i['approvedBy'] = $actor;
        $i['approvedAt'] = Robotics::now();
        $i['version']++;
        $this->engine->audit($s, $actor, 'implementation.approved', $i['name'], $i['siteId']);
    }

    public function generate(array &$s, array $p, string $actor): void
    {
        $n = $this->engine->index($s['implementations'] ?? [], $p['id'] ?? '');
        $i = $s['implementations'][$n];
        $this->engine->ensure($i['status'] === 'approved' && ! $this->issues($i, $s), 'Revisa y aprueba la implementación antes de generar una misión.');
        $m = $this->engine->createMission($s, $i, $actor);
        $mi = $this->engine->index($s['missions'], $m['id']);
        $s['missions'][$mi]['implementation'] = ['id' => $i['id'], 'version' => $i['version'], 'templateId' => $i['template']['id'], 'instructions' => $i['instructions'], 'completion' => $i['completion'], 'perception' => $i['perception'], 'assetIds' => array_column($i['assets'], 'id')];
        $s['implementations'][$n]['missions'][] = $m['id'];
    }
}
