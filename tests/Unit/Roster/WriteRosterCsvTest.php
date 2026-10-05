<?php

namespace Tests\Unit\Roster;

use App\Actions\Roster\WriteRosterCsv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WriteRosterCsvTest extends TestCase
{
    public function test_starts_with_a_byte_order_mark_and_the_header(): void
    {
        $output = $this->write([]);

        $this->assertSame("\xEF\xBB\xBFName,Email,Phone,Role,Status,\"Date joined\"\n", $output);
    }

    public function test_writes_labels_and_dates_for_members_and_invitations(): void
    {
        $records = $this->records($this->write([
            $this->entry(name: 'Ada Lovelace', email: 'ada@example.org', phone: '0123 456', role: 'administrator', status: 'active', joinedAt: '2026-09-14 10:00:00'),
            $this->entry(name: 'Ivy Invited', email: 'ivy@example.org', phone: null, role: 'volunteer', status: 'invited', joinedAt: null),
            $this->entry(name: 'Pat Pending', email: 'pat@example.org', phone: null, role: 'volunteer', status: 'pending', joinedAt: null),
        ]));

        $this->assertSame([
            ['Ada Lovelace', 'ada@example.org', '0123 456', 'Administrator', 'Active', '2026-09-14'],
            ['Ivy Invited', 'ivy@example.org', '', 'Volunteer', 'Invited', ''],
            ['Pat Pending', 'pat@example.org', '', 'Volunteer', 'Pending approval', ''],
        ], $records);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function formulaPrefixes(): array
    {
        return [
            'equals sign' => ['='],
            'plus sign' => ['+'],
            'minus sign' => ['-'],
            'at sign' => ['@'],
            'tab' => ["\t"],
            'carriage return' => ["\r"],
        ];
    }

    #[DataProvider('formulaPrefixes')]
    public function test_prefixes_cells_that_a_spreadsheet_would_treat_as_a_formula(string $prefix): void
    {
        $records = $this->records($this->write([
            $this->entry(name: $prefix.'SUM(A1:A9)', email: 'ada@example.org', phone: $prefix.'1 555 0100', role: 'volunteer', status: 'active', joinedAt: null),
        ]));

        $this->assertSame("'".$prefix.'SUM(A1:A9)', $records[0][0]);
        $this->assertSame("'".$prefix.'1 555 0100', $records[0][2]);
    }

    public function test_leaves_ordinary_cells_unchanged(): void
    {
        $records = $this->records($this->write([
            $this->entry(name: 'Ada "The Countess" Lovelace, FRS', email: 'ada@example.org', phone: '(0) 123', role: 'volunteer', status: 'left', joinedAt: '2025-01-15 00:00:00'),
        ]));

        $this->assertSame([['Ada "The Countess" Lovelace, FRS', 'ada@example.org', '(0) 123', 'Volunteer', 'Left', '2025-01-15']], $records);
    }

    /**
     * @param  list<object>  $entries
     */
    private function write(array $entries): string
    {
        $handle = fopen('php://memory', 'w+');

        (new WriteRosterCsv)->toStream($handle, $entries);

        rewind($handle);
        $output = stream_get_contents($handle);
        fclose($handle);

        return $output;
    }

    /**
     * Read back the data records, skipping the byte-order mark and header.
     *
     * @return list<list<string>>
     */
    private function records(string $output): array
    {
        $handle = fopen('php://memory', 'w+');
        fwrite($handle, substr($output, 3));
        rewind($handle);

        $records = [];

        while (($record = fgetcsv($handle, escape: '')) !== false) {
            $records[] = $record;
        }

        fclose($handle);

        return array_slice($records, 1);
    }

    private function entry(string $name, string $email, ?string $phone, string $role, string $status, ?string $joinedAt): object
    {
        return (object) [
            'kind' => $status === 'invited' ? 'invitation' : 'membership',
            'key' => 1,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'status' => $status,
            'joined_at' => $joinedAt,
        ];
    }
}
