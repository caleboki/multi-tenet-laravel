<?php

namespace App\Models;

use Database\Factories\DemoEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An email the app sent while in demo mode, kept so the demo inbox can show it.
 */
#[Fillable(['recipients', 'subject', 'text_body', 'html_body'])]
class DemoEmail extends Model
{
    /** @use HasFactory<DemoEmailFactory> */
    use HasFactory;

    /**
     * Get the distinct links in the plain-text body, in the order they appear.
     *
     * @return list<string>
     */
    public function links(): array
    {
        preg_match_all('~https?://[^\s<>"\]\)]+~', (string) $this->text_body, $matches);

        return array_values(array_unique($matches[0]));
    }
}
