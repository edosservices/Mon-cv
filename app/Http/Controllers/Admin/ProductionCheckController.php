<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Services\Production\ProductionCheck;
use App\Services\Production\RouterExamination;
use App\Services\Production\TestUserTrial;
use Illuminate\Http\Request;

class ProductionCheckController extends Controller
{
    public function show(Request $request, ProductionCheck $check)
    {
        return view('admin.production-check', [
            'report' => $check->report($request),
        ]);
    }

    public function test(Mikrotik $mikrotik, RouterExamination $examination)
    {
        $result = $examination->run($mikrotik);

        return back()->with('status', $result['banner'] ?: 'Lecture API terminée.')->with('probe_id', $result['correlation_id']);
    }

    public function createTestUser(Request $request, Mikrotik $mikrotik, TestUserTrial $trial)
    {
        $data = $request->validate([
            'profile' => ['required', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
        ]);
        $result = $trial->create($mikrotik, $data['profile']);

        return back()->with('test_user', $result);
    }

    public function deleteTestUser(Request $request, Mikrotik $mikrotik, TestUserTrial $trial)
    {
        $data = $request->validate([
            'username' => ['required', 'regex:/^LIMETE_TEST_[0-9]{8,20}$/'],
        ]);
        $result = $trial->delete($mikrotik, $data['username']);

        return back()->with('test_user', $result);
    }
}
