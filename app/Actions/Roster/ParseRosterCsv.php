<?php

namespace App\Actions\Roster;

use RuntimeException;
use SplFileObject;
use UnexpectedValueException;

class ParseRosterCsv
{
    /**
     * The most data rows one import may contain (FR-047).
     */
    public const MAX_ROWS = 1000;

    /**
     * Read an uploaded roster file into rows to invite (contracts/csv-formats.md, R10).
     *
     * The header row must name a `name` and an `email` column, in any order and letter
     * case. Other columns are ignored, and blank lines are skipped without counting
     * towards the limit. Row numbers match a spreadsheet's, so the header is row 1.
     * Values are trimmed but not validated: the import reports bad rows individually.
     *
     * The class has no framework dependency. A file that must be rejected as a whole
     * throws UnexpectedValueException with the message to show the administrator.
     *
     * @return list<array{row: int, name: string, email: string}>
     *
     * @throws UnexpectedValueException
     */
    public function handle(string $path): array
    {
        $file = $this->open($path);

        $header = null;
        $rows = [];

        foreach ($file as $index => $cells) {
            if (! is_array($cells) || $this->isBlank($cells)) {
                continue;
            }

            if ($header === null) {
                $header = $this->columnPositions($cells);

                continue;
            }

            $rows[] = [
                'row' => $index + 1,
                'name' => trim((string) ($cells[$header['name']] ?? '')),
                'email' => trim((string) ($cells[$header['email']] ?? '')),
            ];
        }

        if ($rows === []) {
            throw new UnexpectedValueException('The file has no rows to import.');
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new UnexpectedValueException(sprintf(
                'The file has %s rows. The limit is %s.',
                number_format(count($rows)),
                number_format(self::MAX_ROWS),
            ));
        }

        return $rows;
    }

    /**
     * Open the file for reading as CSV, refusing anything that isn't UTF-8 text.
     *
     * @throws UnexpectedValueException
     */
    private function open(string $path): SplFileObject
    {
        $contents = is_readable($path) ? file_get_contents($path) : false;

        if ($contents === false || str_contains($contents, "\0") || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new UnexpectedValueException('The file could not be read. Upload a CSV file.');
        }

        try {
            $file = new SplFileObject($path, 'r');
        } catch (RuntimeException) {
            throw new UnexpectedValueException('The file could not be read. Upload a CSV file.');
        }

        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(',', '"', '');

        return $file;
    }

    /**
     * Find the name and email columns in the header row.
     *
     * @param  list<?string>  $cells
     * @return array{name: int, email: int}
     *
     * @throws UnexpectedValueException
     */
    private function columnPositions(array $cells): array
    {
        $cells[0] = preg_replace('/^\x{FEFF}/u', '', (string) $cells[0]);
        $columns = array_map(fn (?string $cell): string => mb_strtolower(trim((string) $cell)), $cells);

        $name = array_search('name', $columns, true);
        $email = array_search('email', $columns, true);

        if ($name === false || $email === false) {
            throw new UnexpectedValueException('The file must have a header row with name and email columns.');
        }

        return ['name' => $name, 'email' => $email];
    }

    /**
     * Determine whether a record has no content, such as a blank line or a row of empty cells.
     *
     * @param  list<?string>  $cells
     */
    private function isBlank(array $cells): bool
    {
        return implode('', array_map(fn (?string $cell): string => trim((string) $cell), $cells)) === '';
    }
}
