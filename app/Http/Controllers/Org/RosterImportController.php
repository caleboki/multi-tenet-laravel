<?php

namespace App\Http\Controllers\Org;

use App\Actions\Roster\ImportRoster;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportRosterRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RosterImportController extends Controller
{
    /**
     * Show the upload form, and the report of the import that just finished, if any (FR-049).
     */
    public function create(Organization $organization): View
    {
        return view('orgs.imports.create', [
            'organization' => $organization,
            'report' => session('importReport'),
        ]);
    }

    /**
     * Invite everyone in the uploaded file (FR-046 to FR-048). The report is shown once and not stored.
     */
    public function store(ImportRosterRequest $request, Organization $organization, ImportRoster $importRoster): RedirectResponse
    {
        $report = $importRoster->handle($organization, $request->user(), $request->file('file')->getRealPath());

        return redirect()
            ->route('orgs.imports.create', $organization)
            ->with('importReport', $report);
    }

    /**
     * Download a sample file showing the expected columns (FR-050).
     *
     * The organization parameter is unused here, but it makes Laravel bind the route's
     * organization, which the membership middleware needs to check access.
     */
    public function sample(Organization $organization): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            echo "name,email\nAda Lovelace,ada@example.org\nGrace Hopper,grace@example.org\n";
        }, 'roster-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
