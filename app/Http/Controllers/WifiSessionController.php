<?php

namespace App\Http\Controllers;

use App\Models\WifiSession;
use Illuminate\Http\Request;

class WifiSessionController extends Controller
{
    public function index(Request $request)
    {
        $sessions = WifiSession::with(['mikrotik', 'wifiZone', 'plan'])
            ->when($request->filled('q'), fn ($query) => $query->where('username', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('mikrotik_id'), fn ($query) => $query->where('mikrotik_id', $request->integer('mikrotik_id')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('sessions.index', ['sessions' => $sessions]);
    }
}
