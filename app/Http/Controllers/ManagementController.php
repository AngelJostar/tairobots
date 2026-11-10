<?php

namespace App\Http\Controllers;

use App\Models\CatalogSetting;
use App\Models\MissionTemplate;
use App\Models\ProjectRequest;
use App\Models\RobotClient;
use App\Services\MissionCatalog;
use App\Services\Robotics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagementController extends Controller
{
    public const TABS = ['resumen' => 'Resumen', 'clientes' => 'Clientes y sedes', 'robots' => 'Robots', 'operacion' => 'Operación', 'misiones' => 'Misiones', 'alertas' => 'Alertas', 'catalogo' => 'Catálogo de misiones', 'implementaciones' => 'Implementaciones', 'mantenimiento' => 'Mantenimiento', 'zonas' => 'Mapas', 'reglas' => 'Integraciones', 'auditoria' => 'Reportes', 'solicitudes' => 'Acceso y permisos', 'configuracion' => 'Configuración'];

    public function __construct(public Robotics $engine, public MissionCatalog $catalog) {}

    public function index(Request $request): View
    {
        $tab = $request->string('tab', 'resumen')->toString();
        abort_unless(isset(self::TABS[$tab]), 404);
        $clients = RobotClient::orderBy('name')->get();
        $client = $request->filled('client') ? $clients->firstWhere('id', $request->string('client')->toString()) : $clients->first();
        abort_unless($client, 404, 'Ejecuta php artisan migrate --seed para cargar los datos de demostración.');
        $state = $client->state;
        $categories = CatalogSetting::findOrFail(1);
        $templates = MissionTemplate::orderByDesc('created_at')->get();
        $implementation = null;
        if ($request->filled('implementation')) {
            $implementation = collect($state['implementations'] ?? [])->firstWhere('id', $request->string('implementation')->toString());
            abort_unless($implementation, 404);
        }

        return view('management', compact('tab', 'clients', 'client', 'state', 'categories', 'templates', 'implementation') + ['tabs' => self::TABS, 'labels' => Robotics::labels(), 'catalog' => $this->catalog, 'requests' => $tab === 'solicitudes' ? ProjectRequest::latest()->paginate(20) : null]);
    }

    public function pulse(Request $request): JsonResponse
    {
        DB::transaction(function (): void {
            foreach (RobotClient::orderBy('id')->lockForUpdate()->get() as $client) {
                $s = $client->state;
                $before = $s['lastPulse'];
                $this->engine->pulse($s);
                if ($s['lastPulse'] !== $before) {
                    $client->state = $s;
                    $client->version++;
                    $client->save();
                }
            }
        });

        return response()->json(['ok' => true]);
    }

    public function export(Request $request, string $report): StreamedResponse
    {
        abort_unless(in_array($report, ['resultados', 'auditoria'], true), 404);
        $client = RobotClient::findOrFail($request->string('client')->toString());
        $state = $client->state;
        $filename = $report === 'resultados' ? 'resultados-misiones.csv' : 'bitacora-auditoria.csv';

        return response()->streamDownload(function () use ($report, $state): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            if ($report === 'resultados') {
                fputcsv($output, ['Misión', 'Estado', 'Sede', 'Robot', 'Tareas', 'Tareas completadas']);
                $sites = collect($state['sites'] ?? [])->pluck('name', 'id');
                $robots = collect($state['robots'] ?? [])->pluck('name', 'id');
                foreach ($state['missions'] ?? [] as $mission) {
                    $tasks = $mission['tasks'] ?? [];
                    fputcsv($output, [$mission['name'], $mission['status'], $sites[$mission['siteId']] ?? '', $robots[$mission['robotId'] ?? $mission['preferredRobot'] ?? ''] ?? '', count($tasks), collect($tasks)->where('status', 'completed')->count()]);
                }
            } else {
                fputcsv($output, ['Fecha y hora', 'Actor', 'Acción', 'Detalle']);
                foreach (array_reverse($state['events'] ?? []) as $event) {
                    fputcsv($output, [date('Y-m-d H:i:s', (int) ($event['at'] ?? 0) / 1000), $event['actor'] ?? '', $event['action'] ?? '', $event['detail'] ?? '']);
                }
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function action(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['operation' => 'required|string|max:60', 'client' => 'nullable|string|max:100', 'version' => 'nullable|integer']);
        $action = $request->string('operation')->toString();
        $actor = $request->user()->name;
        $stored = null;
        $delete = null;
        try {
            DB::transaction(function () use ($request, $action, $actor, &$stored, &$delete): void {
                if ($action === 'client') {
                    $p = $this->engine->validate($request->all(), $this->engine->rules('client'));
                    $id = (string) Str::uuid();
                    $empty = ['id' => $id, 'name' => $p['name'], 'industry' => $p['industry'], 'sites' => [], 'robots' => [], 'missions' => [], 'commands' => [], 'alerts' => [], 'maintenance' => [], 'events' => [], 'zones' => [], 'resources' => [], 'rules' => [], 'implementations' => [], 'lastPulse' => Robotics::now(), 'settings' => ['retentionDays' => 90]];
                    RobotClient::create(['id' => $id, 'name' => $p['name'], 'state' => $empty]);

                    return;
                }
                if ($action === 'template') {
                    $this->catalog->template($request->all(), $actor);

                    return;
                }
                if ($action === 'category') {
                    $p = $request->validate(['categoryId' => 'nullable|string', 'name' => 'required|string|min:2|max:100', 'subcategory' => 'required|string|min:2|max:100', 'taxonomyVersion' => 'required|integer']);
                    $setting = CatalogSetting::lockForUpdate()->findOrFail(1);
                    $this->engine->ensure($setting->version === $p['taxonomyVersion'], 'La clasificación cambió. Actualiza la pantalla.');
                    $categories = $setting->categories;
                    if (empty($p['categoryId'])) {
                        $this->engine->ensure(! collect($categories)->contains(fn ($c) => mb_strtolower($c['name']) === mb_strtolower($p['name'])), 'Ya existe esa categoría.');
                        $categories[] = ['id' => (string) Str::uuid(), 'name' => $p['name'], 'description' => '', 'subcategories' => [['id' => (string) Str::uuid(), 'name' => $p['subcategory']]]];
                    } else {
                        $n = $this->engine->index($categories, $p['categoryId']);
                        $this->engine->ensure(! collect($categories[$n]['subcategories'])->contains(fn ($c) => mb_strtolower($c['name']) === mb_strtolower($p['subcategory'])), 'Ya existe esa subcategoría.');
                        $categories[$n]['name'] = $p['name'];
                        $categories[$n]['subcategories'][] = ['id' => (string) Str::uuid(), 'name' => $p['subcategory']];
                    }
                    $setting->categories = $categories;
                    $setting->version++;
                    $setting->save();

                    return;
                }
                $client = RobotClient::lockForUpdate()->findOrFail($request->input('client'));
                $this->engine->ensure((int) $request->input('version') === $client->version, 'Los datos cambiaron. Recarga la página antes de guardar.');
                $s = $client->state;
                $this->engine->pulse($s);
                switch ($action) {
                    case 'instantiate':$this->catalog->instantiate($s, $request->all(), $actor);
                        break;
                    case 'implementation.update':$this->catalog->update($s, $request->all(), $actor);
                        break;
                    case 'implementation.approve':$this->catalog->approve($s, $request->all(), $actor);
                        break;
                    case 'implementation.generate':$this->catalog->generate($s, $request->all(), $actor);
                        break;
                    case 'asset':$request->validate(['id' => 'required|string', 'kind' => 'required|in:plan,model,image,video,reference', 'file' => 'required|file|max:20480|mimes:pdf,png,jpg,jpeg,webp,mp4,txt,csv,obj,stl,glb,gltf']);
                        $n = $this->engine->index($s['implementations'] ?? [], $request->input('id'));
                        $i = &$s['implementations'][$n];
                        $file = $request->file('file');
                        $kind = $request->input('kind');
                        $mime = $file->getMimeType();
                        $this->engine->ensure($kind !== 'image' || str_starts_with($mime, 'image/'), 'Selecciona un archivo de imagen.');
                        $this->engine->ensure($kind !== 'video' || str_starts_with($mime, 'video/'), 'Selecciona un archivo de video.');
                        $stored = $file->store('implementations/'.$client->id, 'local');
                        $i['assets'][] = ['id' => (string) Str::uuid(), 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'kind' => $kind, 'size' => $file->getSize(), 'mime' => $mime, 'key' => $stored, 'createdAt' => Robotics::now()];
                        $this->catalog->change($i);
                        unset($i);
                        break;
                    case 'asset.delete':$n = $this->engine->index($s['implementations'] ?? [], $request->input('id', ''));
                        $i = &$s['implementations'][$n];
                        $ai = $this->engine->index($i['assets'], $request->input('assetId', ''));
                        $delete = $i['assets'][$ai]['key'];
                        array_splice($i['assets'], $ai, 1);
                        foreach ($i['perception']['targets'] as &$target) {
                            $target['assetIds'] = array_values(array_diff($target['assetIds'] ?? [], [$request->input('assetId')]));
                        }
                        unset($target);
                        $this->catalog->change($i);
                        unset($i);
                        break;
                    case 'transfer':$p = $this->engine->validate($request->all(), $this->engine->rules('transfer'));
                        $this->engine->ensure($client->id !== $p['targetClientId'], 'Selecciona otro cliente.');
                        $target = RobotClient::lockForUpdate()->findOrFail($p['targetClientId']);
                        $t = $target->state;
                        $site = $this->engine->site($t, $p['siteId']);
                        $ri = $this->engine->index($s['robots'], $p['robotId']);
                        $r = $s['robots'][$ri];
                        $this->engine->ensure(! $r['blocked'] && $r['status'] !== 'charging', 'Robot bloqueado o en carga.');
                        $this->engine->ensure(! array_filter($s['missions'], fn ($m) => ($m['robotId'] === $r['id'] && in_array($m['status'], Robotics::ACTIVE)) || ($m['preferredRobot'] === $r['id'] && in_array($m['status'], ['draft', 'scheduled']))), 'Hay misiones activas o pendientes para este robot.');
                        $this->engine->ensure(! array_filter($s['commands'], fn ($c) => $c['robotId'] === $r['id'] && in_array($c['status'], ['requested', 'sent', 'received', 'executing'])), 'Hay un comando pendiente.');
                        $this->engine->ensure(! array_filter($t['robots'], fn ($other) => $other['serial'] === $r['serial']), 'Serie duplicada en destino.');
                        array_splice($s['robots'], $ri, 1);
                        $r = array_replace($r, ['siteId' => $site['id'], 'frame' => $site['frame'], 'owner' => $t['name'], 'fleet' => 'Sin flota', 'area' => 'Recepción', 'x' => 15, 'y' => 33]);
                        $t['robots'][] = $r;
                        $this->engine->audit($s, $actor, 'robot.transferred.out', $r['name'].' → '.$t['name'].': '.$p['reason']);
                        $this->engine->audit($t, $actor, 'robot.transferred.in', $r['name'].' ← '.$s['name'].': '.$p['reason']);
                        $target->state = $t;
                        $target->version++;
                        $target->save();
                        break;
                    default:$this->engine->mutate($s, $action, $request->all(), $actor);
                }
                $client->state = $s;
                $client->version++;
                $client->save();
            }, 3);
        } catch (\Throwable $e) {
            if ($stored) {
                Storage::disk('local')->delete($stored);
            }
            throw $e;
        }
        if ($delete) {
            Storage::disk('local')->delete($delete);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Escenario aplicado. Revisa el centro de operaciones.']);
        }

        return back()->with('success', 'Cambios guardados.');
    }

    public function download(string $client, string $implementation, string $asset): StreamedResponse
    {
        $state = RobotClient::findOrFail($client)->state;
        $i = ($state['implementations'] ?? [])[$this->engine->index($state['implementations'] ?? [], $implementation)];
        $file = $i['assets'][$this->engine->index($i['assets'], $asset)];
        abort_unless(Storage::disk('local')->exists($file['key']), 404);

        return Storage::disk('local')->download($file['key'], $file['name'], ['X-Content-Type-Options' => 'nosniff']);
    }
}
