<?php

namespace App\Http\Controllers;

use App\Models\DemoEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DemoInboxController extends Controller
{
    /**
     * List the captured emails, newest first, optionally for one recipient.
     */
    public function index(Request $request): View
    {
        $recipient = Str::lower(trim((string) $request->query('to', '')));

        return view('demo.inbox.index', [
            'recipient' => $recipient,
            'emails' => DemoEmail::query()
                ->when($recipient !== '', fn ($query) => $query->whereLike('recipients', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $recipient).'%'))
                ->latest('id')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    /**
     * Show one captured email, with its links as buttons.
     */
    public function show(DemoEmail $demoEmail): View
    {
        return view('demo.inbox.show', [
            'email' => $demoEmail,
        ]);
    }
}
