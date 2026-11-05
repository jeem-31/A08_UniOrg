<?php

namespace App\Http\Controllers\AccountManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountManagement\FilterAuditRequest;
use App\Services\AccountManagement\AccountManagementAuditQuery;
use App\Services\AccountManagement\ActorAccountId;
use App\Services\AccountManagement\AuditFilterParse;
use Illuminate\Support\Facades\Auth;

class AuditController extends Controller
{
    public function index(FilterAuditRequest $request, AccountManagementAuditQuery $query)
    {
        $result = $query->browse($request->validated());

        if (! $result->accepted()) {
            return redirect()->route('osas.audits.index')->withErrors($result->messages())->withInput();
        }

        return view('osas.accounts.audits', [
            'audits' => $result,
        ]);
    }
}