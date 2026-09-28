<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        return view('audit.index', [
            'logs' => AuditLog::with('user')->latest('created_at')->paginate(30),
        ]);
    }
}
