<?php

namespace Tests\Unit\Roster;

use App\Actions\Roster\ParseRosterCsv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class ParseRosterCsvTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_matches_header_columns_ignoring_case_spaces_order_and_a_byte_order_mark(): void
    {
        $path = $this->csv("\xEF\xBB\xBF Email , NAME \nada@example.org,Ada Lovelace\n");

        $rows = (new ParseRosterCsv)->handle($path);

        $this->assertSame([['row' => 2, 'name' => 'Ada Lovelace', 'email' => 'ada@example.org']], $rows);
    }

    public function test_ignores_extra_columns_and_blank_lines_and_numbers_rows_like_a_spreadsheet(): void
    {
        $path = $this->csv("name,email,phone\nAda Lovelace,ada@example.org,123\n\n,,\nGrace Hopper,grace@example.org,456\n");

        $rows = (new ParseRosterCsv)->handle($path);

        $this->assertSame([
            ['row' => 2, 'name' => 'Ada Lovelace', 'email' => 'ada@example.org'],
            ['row' => 5, 'name' => 'Grace Hopper', 'email' => 'grace@example.org'],
        ], $rows);
    }

    public function test_trims_values_and_reads_quoted_fields(): void
    {
        $path = $this->csv("name,email\n\"  Lovelace, Ada \",  ada@example.org  \n");

        $rows = (new ParseRosterCsv)->handle($path);

        $this->assertSame([['row' => 2, 'name' => 'Lovelace, Ada', 'email' => 'ada@example.org']], $rows);
    }

    public function test_keeps_rows_with_a_missing_value_for_the_import_to_report(): void
    {
        $path = $this->csv("name,email\n,ada@example.org\nGrace Hopper\n");

        $rows = (new ParseRosterCsv)->handle($path);

        $this->assertSame([
            ['row' => 2, 'name' => '', 'email' => 'ada@example.org'],
            ['row' => 3, 'name' => 'Grace Hopper', 'email' => ''],
        ], $rows);
    }

    public function test_accepts_exactly_1000_rows(): void
    {
        $path = $this->csv("name,email\n".str_repeat("Ada,ada@example.org\n", 1000));

        $rows = (new ParseRosterCsv)->handle($path);

        $this->assertCount(1000, $rows);
        $this->assertSame(1001, $rows[999]['row']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function filesRejectedAsAWhole(): array
    {
        return [
            'no name column' => ["full name,email\nAda,ada@example.org\n", 'The file must have a header row with name and email columns.'],
            'no email column' => ["name,mail\nAda,ada@example.org\n", 'The file must have a header row with name and email columns.'],
            'more than 1,000 rows' => ["name,email\n".str_repeat("Ada,ada@example.org\n", 1001), 'The file has 1,001 rows. The limit is 1,000.'],
            'header only' => ["name,email\n", 'The file has no rows to import.'],
            'header and blank lines only' => ["name,email\n\n\n", 'The file has no rows to import.'],
            'empty file' => ['', 'The file has no rows to import.'],
            'binary content' => ["name,email\n\x00\x01\x02", 'The file could not be read. Upload a CSV file.'],
            'not UTF-8' => ["name,email\nAda,\xff\xfe@example.org\n", 'The file could not be read. Upload a CSV file.'],
        ];
    }

    #[DataProvider('filesRejectedAsAWhole')]
    public function test_rejects_the_whole_file(string $contents, string $message): void
    {
        $path = $this->csv($contents);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        (new ParseRosterCsv)->handle($path);
    }

    public function test_rejects_a_file_that_does_not_exist(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('The file could not be read. Upload a CSV file.');

        (new ParseRosterCsv)->handle(sys_get_temp_dir().'/missing-roster-'.uniqid().'.csv');
    }

    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'roster');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
