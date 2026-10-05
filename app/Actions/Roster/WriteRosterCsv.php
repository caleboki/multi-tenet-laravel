<?php

namespace App\Actions\Roster;

use App\Enums\MembershipRole;
use App\Enums\RosterStatus;
use DateTimeImmutable;

class WriteRosterCsv
{
    /**
     * Characters that make a spreadsheet treat a cell as a formula (R11).
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Write roster entries to an open stream as the export file (contracts/csv-formats.md).
     *
     * The file starts with a UTF-8 byte-order mark so Excel detects the encoding. Names
     * and phone numbers are user-supplied, so any cell that a spreadsheet would treat as a
     * formula is prefixed with a single quote.
     *
     * @param  resource  $handle
     * @param  iterable<object{name: string, email: string, phone: ?string, role: string, status: string, joined_at: ?string}>  $entries
     */
    public function toStream($handle, iterable $entries): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        $this->writeRow($handle, ['Name', 'Email', 'Phone', 'Role', 'Status', 'Date joined']);

        foreach ($entries as $entry) {
            $this->writeRow($handle, array_map($this->escapeFormula(...), [
                $entry->name,
                $entry->email,
                $entry->phone ?? '',
                MembershipRole::from($entry->role)->label(),
                RosterStatus::from($entry->status)->label(),
                $entry->joined_at === null ? '' : (new DateTimeImmutable($entry->joined_at))->format('Y-m-d'),
            ]));
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<string>  $cells
     */
    private function writeRow($handle, array $cells): void
    {
        fputcsv($handle, $cells, ',', '"', '');
    }

    /**
     * Prefix a cell with a single quote when a spreadsheet would read it as a formula.
     */
    private function escapeFormula(string $cell): string
    {
        return $cell !== '' && in_array($cell[0], self::FORMULA_PREFIXES, true) ? "'".$cell : $cell;
    }
}
