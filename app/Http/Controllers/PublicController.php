<?php

namespace App\Http\Controllers;

use App\Models\ProjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function index(): View
    {
        return view('home', json_decode(file_get_contents(database_path('data/landing.json')), true, 512, JSON_THROW_ON_ERROR));
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate(['request_key' => 'required|uuid', 'nombre' => 'required|string|min:2|max:100', 'empresa' => 'required|string|min:2|max:160', 'email' => 'required|email|max:254', 'sector' => 'required|in:logistica,energia,biotecnologia,farmaceutica,otro', 'objetivo' => 'required|string|min:20|max:3000', 'consentimiento' => 'accepted', 'website' => 'nullable|max:0']);
        unset($data['website']);
        $data['consentimiento'] = true;
        $data['payload_hash'] = hash('sha256', json_encode(collect($data)->except('request_key')->all()));
        $record = ProjectRequest::firstOrCreate(['request_key' => $data['request_key']], $data + ['id' => (string) Str::uuid()]);
        abort_unless(hash_equals($record->payload_hash, $data['payload_hash']), 409, 'Esta solicitud ya se registró con otros datos.');

        return redirect()->to(route('home').'#contacto')->with('success', 'Tu proyecto quedó registrado. Folio: '.$record->id);
    }
}
