<?php

namespace App\Support;

/** Helpers for text that goes into an e-mail. */
class MailText
{
    /**
     * Make text safe to put in a Markdown mail line.
     * Names and remarks are typed by users: without this, "[click here](http://evil.example)" in a name
     * would become a real link inside an e-mail that looks like it comes from MyPetakom.
     */
    public static function escape(string $text): string
    {
        // Never let a line start a heading, list or quote, and neutralise links, emphasis, code and HTML.
        return preg_replace('/([\\\\`*_{}\[\]()#+\-.!<>|~&])/', '\\\\$1', $text);
    }
}
