<?php

namespace App\Console\Commands;

use App\Models\RobotClient;
use App\Services\Robotics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PulseSimulation extends Command
{
    protected $signature = 'tairobots:pulse';

    protected $description = 'Avanza un paso de la simulación';

    public function handle(Robotics $engine): int
    {
        DB::transaction(function () use ($engine): void {
            foreach (RobotClient::orderBy('id')->lockForUpdate()->get() as $client) {
                $s = $client->state;
                $engine->pulse($s);
                $client->state = $s;
                $client->version++;
                $client->save();
            }
        });

        return self::SUCCESS;
    }
}
