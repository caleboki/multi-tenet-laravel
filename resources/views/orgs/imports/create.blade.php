@extends('layouts.app')

@section('title', 'Import volunteers')

@section('content')
    <a href="{{ route('orgs.members.index', $organization) }}" class="self-start rounded-sm text-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">
        Back to the roster
    </a>

    <div class="flex flex-col gap-1">
        <h1 class="text-2xl font-semibold">Import volunteers</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            Upload a CSV file with a <code>name</code> column and an <code>email</code> column, up to 1,000 rows.
            Everyone in it is invited to {{ $organization->name }} as a volunteer.
            <a href="{{ route('orgs.imports.sample', $organization) }}" class="rounded-sm font-medium text-indigo-700 underline focus-visible:outline-2 focus-visible:outline-indigo-600 dark:text-indigo-300">Download a sample file</a>.
        </p>
    </div>

    @if ($report)
        <section aria-labelledby="report-heading" role="status" class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
            <h2 id="report-heading" class="text-lg font-semibold">Import finished</h2>

            <p class="flex flex-wrap gap-4 text-sm">
                <span><strong>{{ $report['invited'] }}</strong> invited</span>
                <span><strong>{{ count($report['skipped']) }}</strong> skipped</span>
            </p>

            @if ($report['skipped'] !== [])
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-left text-sm dark:divide-zinc-800">
                        <caption class="sr-only">Skipped rows</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="px-3 py-2 font-semibold">Row</th>
                                <th scope="col" class="px-3 py-2 font-semibold">Email</th>
                                <th scope="col" class="px-3 py-2 font-semibold">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                            @foreach ($report['skipped'] as $skippedRow)
                                <tr>
                                    <td class="px-3 py-2">{{ $skippedRow['row'] }}</td>
                                    <td class="px-3 py-2">{{ $skippedRow['email'] ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $skippedRow['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <form method="POST" action="{{ route('orgs.imports.store', $organization) }}" enctype="multipart/form-data" class="flex max-w-md flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900">
        @csrf

        <x-input name="file" label="CSV file" type="file" accept=".csv,text/csv" hint="At most 1 MB. A file with a problem is rejected as a whole, and no one is invited." required />

        <x-button class="self-start">Import and send invitations</x-button>
    </form>
@endsection
