<?php

namespace Tests\Feature;

use App\Http\Controllers\ManagementController;
use App\Models\MissionTemplate;
use App\Models\RobotClient;
use App\Models\User;
use App\Services\Robotics;
use Database\Seeders\TairobotsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TairobotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(TairobotsDemoSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function action(string $operation, array $data = [], string $client = 'salud'): TestResponse
    {
        return $this->post(route('management.action'), array_merge(['operation' => $operation, 'client' => $client, 'version' => RobotClient::findOrFail($client)->version], $data));
    }

    private function missionData(): array
    {
        return ['name' => 'Entrega de prueba', 'siteId' => 'polanco', 'robotId' => 'r2', 'priority' => 'normal', 'minBattery' => 25, 'payload' => 1, 'tasks' => [['name' => 'Entregar material', 'capability' => 'carry', 'x' => 42, 'y' => 33, 'duration' => 2, 'confirm' => false]]];
    }

    public function test_public_pages_and_registration_do_not_grant_administration(): void
    {
        $this->get('/')->assertOk()->assertSee('Ingeniería para');
        $this->get('/ingresar')->assertOk();
        $this->get('/registro')->assertOk();
        $this->get('/gestion')->assertRedirect('/ingresar');
        $this->post('/registro', ['name' => 'Usuario de prueba', 'email' => 'normal@example.com', 'password' => 'UnaClaveLarga123!', 'password_confirmation' => 'UnaClaveLarga123!', 'consentimiento' => 1, 'is_admin' => 1])->assertRedirect(route('account'));
        $user = User::where('email', 'normal@example.com')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('UnaClaveLarga123!', $user->password));
        $this->get('/gestion')->assertForbidden();
        $this->post('/gestion/pulso')->assertForbidden();
    }

    public function test_login_rejects_bad_password_then_authenticates_and_logs_out(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'password' => 'UnaClaveLarga123!']);
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'UnaClaveLarga123!'])->assertRedirect(route('management'));
        $this->assertAuthenticatedAs($user);
        $this->post('/salir')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_every_management_screen_renders(): void
    {
        $this->actingAs($this->admin());
        foreach (array_keys(ManagementController::TABS) as $tab) {
            $this->get(route('management', ['client' => 'salud', 'tab' => $tab]))->assertOk();
        }
    }

    public function test_seed_is_repeatable_without_destroying_state(): void
    {
        $client = RobotClient::findOrFail('salud');
        $s = $client->state;
        $s['name'] = 'Nombre conservado';
        $client->state = $s;
        $client->save();
        $this->seed(TairobotsDemoSeeder::class);
        $this->assertDatabaseCount('robot_clients', 2);
        $this->assertSame('Nombre conservado', $client->fresh()->state['name']);
        $this->assertCount(8, array_merge(...RobotClient::all()->map(fn ($c) => $c->state['robots'])->all()));
    }

    public function test_public_request_is_validated_and_idempotent(): void
    {
        $data = ['request_key' => (string) Str::uuid(), 'nombre' => 'Luis Andrade', 'empresa' => 'Tairobots', 'email' => 'luis@example.com', 'sector' => 'logistica', 'objetivo' => 'Queremos mejorar el traslado interno de materiales.', 'consentimiento' => 1];
        $this->post('/solicitudes', $data)->assertSessionHasNoErrors();
        $this->post('/solicitudes', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('project_requests', 1);
        $data['objetivo'] = 'Una solicitud diferente con la misma clave.';
        $this->post('/solicitudes', $data)->assertStatus(409);
        $this->get('/gestion?tab=solicitudes')->assertRedirect('/ingresar');
    }

    public function test_mission_lifecycle_and_duplicate_command(): void
    {
        $this->actingAs($this->admin());
        $this->action('mission', $this->missionData())->assertSessionHasNoErrors();
        $m = RobotClient::find('salud')->state['missions'][0];
        $this->action('assign', ['id' => $m['id'], 'robotId' => 'r2'])->assertSessionHasNoErrors();
        $key = (string) Str::uuid();
        $command = ['robotId' => 'r2', 'missionId' => $m['id'], 'kind' => 'start', 'key' => $key];
        $this->action('command', $command)->assertSessionHasNoErrors();
        $this->action('command', $command)->assertSessionHasNoErrors();
        $this->assertCount(1, RobotClient::find('salud')->state['commands']);
        for ($n = 0; $n < 7; $n++) {
            $client = RobotClient::find('salud');
            $s = $client->state;
            $s['lastPulse'] = Robotics::now() - 3000;
            $client->state = $s;
            $client->save();
            $this->post('/gestion/pulso')->assertOk();
        }
        $s = RobotClient::find('salud')->state;
        $this->assertSame('completed', $s['missions'][0]['status']);
        $this->assertSame('confirmed', $s['commands'][0]['status']);
        $this->assertSame('available', collect($s['robots'])->firstWhere('id', 'r2')['status']);
        $this->assertNotEmpty($s['missions'][0]['evidence']);
    }

    public function test_incompatible_mission_and_stale_write_are_rejected(): void
    {
        $this->actingAs($this->admin());
        $p = $this->missionData();
        $p['robotId'] = 'r6';
        $this->action('mission', $p)->assertNotFound();
        $this->assertCount(0, RobotClient::find('salud')->state['missions']);
        $p = $this->missionData();
        $p['tasks'][0]['x'] = 80;
        $p['tasks'][0]['y'] = 55;
        $this->action('mission', $p)->assertSessionHasErrors();
        $this->action('settings', ['version' => 0, 'retentionDays' => 120])->assertSessionHasErrors();
        $this->assertSame(90, RobotClient::find('salud')->state['settings']['retentionDays']);
    }

    public function test_robot_reservation_prevents_double_assignment(): void
    {
        $this->actingAs($this->admin());
        $this->action('mission', $this->missionData());
        $this->action('mission', $this->missionData());
        $missions = RobotClient::find('salud')->state['missions'];
        $this->action('assign', ['id' => $missions[0]['id'], 'robotId' => 'r2'])->assertSessionHasNoErrors();
        $this->action('assign', ['id' => $missions[1]['id'], 'robotId' => 'r2'])->assertSessionHasErrors();
        $this->assertSame('draft', RobotClient::find('salud')->state['missions'][1]['status']);
    }

    public function test_fault_alert_and_maintenance_flows(): void
    {
        $this->actingAs($this->admin());
        $this->action('simulation', ['robotId' => 'r1', 'scenario' => 'fault'])->assertSessionHasNoErrors();
        $s = RobotClient::find('salud')->state;
        $this->assertSame('fault', $s['robots'][0]['status']);
        $this->assertNotEmpty($s['alerts']);
        $this->get('/gestion?client=salud&tab=alertas')->assertOk();
        $this->action('alert', ['id' => $s['alerts'][0]['id'], 'status' => 'resolved', 'comment' => 'Falla revisada por el responsable'])->assertSessionHasNoErrors();
        $this->action('maintenance', ['robotId' => 'r1', 'title' => 'Revisión preventiva', 'kind' => 'preventive', 'technician' => 'Técnico de prueba', 'notes' => 'Inspección', 'parts' => 'Ninguno'])->assertSessionHasNoErrors();
        $s = RobotClient::find('salud')->state;
        $this->assertTrue($s['robots'][0]['blocked']);
        $this->get('/gestion?client=salud&tab=mantenimiento')->assertOk();
        $this->action('maintenance.close', ['id' => $s['maintenance'][0]['id'], 'notes' => 'Revisión concluida sin fallas'])->assertSessionHasNoErrors();
        $this->assertFalse(RobotClient::find('salud')->state['robots'][0]['blocked']);
    }

    public function test_implementation_requires_review_and_generates_mission(): void
    {
        $this->actingAs($this->admin());
        $template = MissionTemplate::findOrFail('base-transport-1');
        $this->action('instantiate', ['templateId' => $template->id, 'siteId' => 'polanco'])->assertSessionHasNoErrors();
        $i = RobotClient::find('salud')->state['implementations'][0];
        $this->get(route('management', ['client' => 'salud', 'tab' => 'implementaciones', 'implementation' => $i['id']]))->assertOk();
        $this->action('implementation.generate', ['id' => $i['id']])->assertSessionHasErrors();
        $data = $i;
        unset($data['version']);
        $data['implementationVersion'] = $i['version'];
        $data['workflowStep'] = 6;
        $data['instructions'] = 'Entregar paquete en recepción autorizada.';
        $this->action('implementation.update', $data)->assertSessionHasNoErrors();
        $i = RobotClient::find('salud')->state['implementations'][0];
        $this->action('implementation.approve', ['id' => $i['id'], 'implementationVersion' => $i['version'], 'reviewed' => 1])->assertSessionHasNoErrors();
        $this->action('implementation.generate', ['id' => $i['id']])->assertSessionHasNoErrors();
        $s = RobotClient::find('salud')->state;
        $this->assertCount(1, $s['missions']);
        $this->assertSame($i['id'], $s['missions'][0]['implementation']['id']);
        $this->get('/gestion?client=salud&tab=misiones')->assertOk();
    }

    public function test_files_are_private_and_removal_invalidates_approval(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->action('instantiate', ['templateId' => 'base-transport-1', 'siteId' => 'polanco']);
        $i = RobotClient::find('salud')->state['implementations'][0];
        $this->action('asset', ['id' => $i['id'], 'kind' => 'reference', 'file' => UploadedFile::fake()->createWithContent('referencia.txt', 'Referencia autorizada')])->assertSessionHasNoErrors();
        $i = RobotClient::find('salud')->state['implementations'][0];
        $asset = $i['assets'][0];
        Storage::disk('local')->assertExists($asset['key']);
        $url = route('management.asset', ['salud', $i['id'], $asset['id']]);
        $this->get($url)->assertDownload('referencia.txt');
        $this->actingAs(User::factory()->create());
        $this->get($url)->assertForbidden();
        $this->actingAs($admin);
        $this->action('asset.delete', ['id' => $i['id'], 'assetId' => $asset['id']])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($asset['key']);
        $this->assertSame('draft', RobotClient::find('salud')->state['implementations'][0]['status']);
    }

    public function test_transfer_moves_robot_between_clients(): void
    {
        $this->actingAs($this->admin());
        $this->action('transfer', ['robotId' => 'r1', 'targetClientId' => 'atlas', 'siteId' => 'cedis', 'reason' => 'Reasignación de demostración'])->assertSessionHasNoErrors();
        $this->assertCount(4, RobotClient::find('salud')->state['robots']);
        $this->assertCount(4, RobotClient::find('atlas')->state['robots']);
        $this->assertSame('cedis', collect(RobotClient::find('atlas')->state['robots'])->firstWhere('id', 'r1')['siteId']);
    }

    public function test_login_rate_limit_blocks_even_correct_password_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => 'UnaClaveLarga123!']);
        for ($n = 0; $n < 5; $n++) {
            $this->post('/ingresar', ['email' => $user->email, 'password' => 'incorrecta'])->assertSessionHasErrors('email');
        }
        $this->post('/ingresar', ['email' => $user->email, 'password' => 'UnaClaveLarga123!'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_normalizes_email_before_checking_uniqueness(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $this->post('/registro', ['name' => 'Otra cuenta', 'email' => 'EXISTING@EXAMPLE.COM', 'password' => 'UnaClaveLarga123!', 'password_confirmation' => 'UnaClaveLarga123!', 'consentimiento' => 1])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_csrf_is_required_outside_the_test_environment(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/solicitudes', [])->assertStatus(419);
        $this->app->instance('env', 'testing');
    }

    public function test_submitted_task_state_cannot_bypass_the_mission_lifecycle(): void
    {
        $this->actingAs($this->admin());
        $data = $this->missionData();
        $data['tasks'][0]['status'] = 'completed';
        $data['tasks'][0]['progress'] = 1;
        $data['tasks'][0]['id'] = 'forged';
        $this->action('mission', $data)->assertSessionHasNoErrors();
        $task = RobotClient::find('salud')->state['missions'][0]['tasks'][0];
        $this->assertSame('pending', $task['status']);
        $this->assertEquals(0, $task['progress']);
        $this->assertSame('t1', $task['id']);
    }

    public function test_scheduled_mission_runs_and_creates_a_future_repeat(): void
    {
        $this->actingAs($this->admin());
        $data = $this->missionData();
        $data['scheduledAt'] = now()->subMinute()->format('Y-m-d H:i:s');
        $data['repeatMinutes'] = 30;
        $this->action('mission', $data)->assertSessionHasNoErrors();
        for ($n = 0; $n < 8; $n++) {
            $c = RobotClient::find('salud');
            $s = $c->state;
            $s['lastPulse'] = Robotics::now() - 3000;
            $c->state = $s;
            $c->save();
            $this->post('/gestion/pulso')->assertOk();
        }
        $missions = RobotClient::find('salud')->state['missions'];
        $this->assertCount(2, $missions);
        $this->assertSame('completed', $missions[0]['status']);
        $this->assertSame('scheduled', $missions[1]['status']);
        $this->assertSame($missions[1]['id'], $missions[0]['nextOccurrence']);
        $this->assertGreaterThan(Robotics::now(), $missions[1]['scheduledAt']);
    }

    public function test_pet_template_cannot_be_approved_without_identification_and_authorized_references(): void
    {
        $this->actingAs($this->admin());
        $this->action('instantiate', ['templateId' => 'base-home-4', 'siteId' => 'polanco'])->assertSessionHasNoErrors();
        $i = RobotClient::find('salud')->state['implementations'][0];
        $this->assertSame('follow', $i['perception']['behavior']);
        $this->assertCount(1, $i['perception']['targets']);
        $this->action('implementation.approve', ['id' => $i['id'], 'implementationVersion' => $i['version'], 'reviewed' => 1])->assertSessionHasErrors();
        $this->assertSame('draft', RobotClient::find('salud')->state['implementations'][0]['status']);
    }
}
