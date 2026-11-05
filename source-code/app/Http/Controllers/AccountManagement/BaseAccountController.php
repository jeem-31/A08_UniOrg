<?php

namespace App\Http\Controllers\AccountManagement;

use App\Http\Controllers\Controller;
use App\Services\AccountManagement\AccountManagementFailure;
use App\Services\AccountManagement\RejectedAccountManagementCommand;
use Illuminate\Http\RedirectResponse;

abstract class BaseAccountController extends Controller
{
    protected function handleRejection(RejectedAccountManagementCommand $rejection): RedirectResponse
    {
        if ($rejection->failure === AccountManagementFailure::Unauthorized || $rejection->failure === AccountManagementFailure::NotFound) {
            abort(404);
        }

        if ($rejection->failure === AccountManagementFailure::Stale) {
            return back()->with('error', 'The account was modified by another user. Please review the updated details and try again.');
        }

        if ($rejection->failure === AccountManagementFailure::Persistence) {
            return back()->with('error', 'An unexpected system error occurred while saving. Please try again.');
        }

        if ($rejection->failure === AccountManagementFailure::Confirmation) {
            return back()->with('error', 'The action could not be confirmed or the confirmation expired.');
        }
        
        if ($rejection->failure === AccountManagementFailure::SelfAction) {
            return back()->with('error', 'You cannot perform this action on your own account.');
        }

        if ($rejection->fieldErrors !== []) {
            return back()->withErrors($rejection->fieldErrors)->withInput();
        }

        return back()->with('error', 'The action could not be completed.');
    }
}