<?php

namespace App\Http\Controllers\Org;

use App\Actions\Roster\BuildRosterQuery;
use App\Actions\Roster\WriteRosterCsv;
use App\Enums\MembershipRole;
use App\Enums\RosterStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RosterFilterRequest;
use App\Models\Organization;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController extends Controller
{
    /**
     * Download the roster entries matching the current search and filters as CSV (FR-051, R11).
     *
     * Rows are read in chunks while the file streams, so memory stays flat for large rosters.
     */
    public function __invoke(RosterFilterRequest $request, Organization $organization, BuildRosterQuery $buildRosterQuery, WriteRosterCsv $writeRosterCsv): StreamedResponse
    {
        $entries = $buildRosterQuery->handle(
            $organization,
            $request->validated('q'),
            $request->enum('role', MembershipRole::class),
            $request->enum('status', RosterStatus::class),
        )->lazy();

        return response()->streamDownload(function () use ($writeRosterCsv, $entries): void {
            $handle = fopen('php://output', 'w');
            $writeRosterCsv->toStream($handle, $entries);
            fclose($handle);
        }, "{$organization->slug}-roster-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
