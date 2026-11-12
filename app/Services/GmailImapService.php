<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\TicketMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GmailImapService
{
    public function syncCustomerReplies(): array
    {
        $startedAt = microtime(true);

        $stats = [
            'checked' => 0,
            'saved' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        if (!function_exists('imap_open')) {
            throw new RuntimeException(
                'PHP IMAP extension is not enabled.'
            );
        }

        $host = env('IMAP_HOST', 'imap.gmail.com');
        $port = (int) env('IMAP_PORT', 993);

        $username = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');

        if (!$username || !$password) {
            throw new RuntimeException(
                'Gmail username or password is missing.'
            );
        }

        // Prevent excessively long IMAP network operations.
        imap_timeout(IMAP_OPENTIMEOUT, 20);
        imap_timeout(IMAP_READTIMEOUT, 20);
        imap_timeout(IMAP_WRITETIMEOUT, 20);
        imap_timeout(IMAP_CLOSETIMEOUT, 10);

        $mailbox = "{{$host}:{$port}/imap/ssl}INBOX";

        imap_errors();
        imap_alerts();

        $connectionStarted = microtime(true);

        $imap = @imap_open(
            $mailbox,
            $username,
            $password,
            OP_READONLY,
            1,
            ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
        );

        if (!$imap) {
            throw new RuntimeException(
                'Gmail connection failed: ' .
                    (imap_last_error() ?: 'Unknown IMAP error')
            );
        }

        Log::info('Gmail IMAP connection established', [
            'duration_seconds' => round(
                microtime(true) - $connectionStarted,
                2
            ),
        ]);

        try {
            $searchStarted = microtime(true);

            // Keep the original 30-day search window.
            $since = now()->subDays(30)->format('d-M-Y');

            $emailNumbers = @imap_search(
                $imap,
                'SUBJECT "TKT-" SINCE "' . $since . '"'
            );

            Log::info('Gmail IMAP search completed', [
                'duration_seconds' => round(
                    microtime(true) - $searchStarted,
                    2
                ),
                'matches' => is_array($emailNumbers)
                    ? count($emailNumbers)
                    : 0,
            ]);

            if (!$emailNumbers) {
                return $stats;
            }

            // Fetch only the latest 100 matching emails.
            rsort($emailNumbers, SORT_NUMERIC);

            $emailNumbers = array_slice(
                $emailNumbers,
                0,
                100
            );

            // Process oldest first.
            sort($emailNumbers, SORT_NUMERIC);

            /*
            |--------------------------------------------------------------------------
            | Batch Fetch Email Headers
            |--------------------------------------------------------------------------
            */

            $headersStarted = microtime(true);

            $sequence = implode(',', $emailNumbers);

            $overviews = @imap_fetch_overview(
                $imap,
                $sequence,
                0
            );

            if (!is_array($overviews)) {
                throw new RuntimeException(
                    'Unable to fetch Gmail message headers.'
                );
            }

            Log::info('Gmail headers fetched', [
                'count' => count($overviews),
                'duration_seconds' => round(
                    microtime(true) - $headersStarted,
                    2
                ),
            ]);

            $supportEmail = strtolower(trim($username));

            // Preserve message sequence ordering.
            usort($overviews, function ($a, $b) {
                return ($a->msgno ?? 0) <=> ($b->msgno ?? 0);
            });

            /*
            |--------------------------------------------------------------------------
            | Process Incoming Customer Replies
            |--------------------------------------------------------------------------
            */

            foreach ($overviews as $header) {
                $emailNumber = (int) ($header->msgno ?? 0);

                $stats['checked']++;

                try {
                    if ($emailNumber <= 0) {
                        $stats['skipped']++;
                        continue;
                    }

                    $subject = $this->decodeText(
                        (string) ($header->subject ?? '')
                    );

                    if (!preg_match(
                        '/TKT-[A-Z0-9]+/i',
                        $subject,
                        $matches
                    )) {
                        $stats['skipped']++;
                        continue;
                    }

                    $ticketNumber = strtoupper($matches[0]);

                    /*
                    |--------------------------------------------------------------------------
                    | Extract Sender Email
                    |--------------------------------------------------------------------------
                    */

                    $from = (string) ($header->from ?? '');

                    $senderEmail = $this->extractEmail($from);

                    if (
                        !$senderEmail ||
                        $senderEmail === $supportEmail
                    ) {
                        $stats['skipped']++;
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Match Support Ticket
                    |--------------------------------------------------------------------------
                    */

                    $ticket = SupportTicket::query()
                        ->where('ticket_number', $ticketNumber)
                        ->first();

                    if (!$ticket) {
                        $stats['skipped']++;
                        continue;
                    }

                    $customerEmail = strtolower(
                        trim((string) $ticket->customer_email)
                    );

                    if ($senderEmail !== $customerEmail) {
                        $stats['skipped']++;
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Email Message ID
                    |--------------------------------------------------------------------------
                    */

                    $messageId = trim(
                        (string) ($header->message_id ?? '')
                    );

                    if (!$messageId) {
                        $uid = @imap_uid(
                            $imap,
                            $emailNumber
                        );

                        if (!$uid) {
                            throw new RuntimeException(
                                'Unable to identify incoming email.'
                            );
                        }

                        $messageId = hash(
                            'sha256',
                            $supportEmail . '|' . $uid
                        );
                    }

                    if (strlen($messageId) > 255) {
                        $messageId = hash(
                            'sha256',
                            $messageId
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Duplicate Check Before Downloading Body
                    |--------------------------------------------------------------------------
                    */

                    $exists = DB::table('ticket_incoming_emails')
                        ->where('message_id', $messageId)
                        ->exists();

                    if ($exists) {
                        $stats['duplicates']++;
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Fetch New Message Body
                    |--------------------------------------------------------------------------
                    */

                    $body = $this->getEmailBody(
                        $imap,
                        $emailNumber
                    );

                    $body = $this->cleanReply($body);

                    if ($body === '') {
                        $stats['skipped']++;
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Received Date
                    |--------------------------------------------------------------------------
                    */

                    $receivedAt = null;

                    if (!empty($header->date)) {
                        try {
                            $receivedAt = Carbon::parse(
                                $header->date
                            );
                        } catch (Throwable $e) {
                            $receivedAt = null;
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Save Customer Reply
                    |--------------------------------------------------------------------------
                    */

                    $result = DB::transaction(
                        function () use (
                            $ticket,
                            $messageId,
                            $senderEmail,
                            $receivedAt,
                            $body
                        ) {
                            $inserted = DB::table(
                                'ticket_incoming_emails'
                            )->insertOrIgnore([
                                'ticket_id' => $ticket->id,
                                'message_id' => $messageId,
                                'sender_email' => $senderEmail,
                                'received_at' => $receivedAt,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            if (!$inserted) {
                                return 'duplicate';
                            }

                            TicketMessage::create([
                                'ticket_id' => $ticket->id,
                                'sender_type' => 'customer',
                                'sender_id' => null,
                                'message' => $body,
                                'is_ai_generated' => false,
                                'email_status' => 'not_applicable',
                            ]);

                            $ticket->touch();

                            return 'saved';
                        }
                    );

                    if ($result === 'saved') {
                        $stats['saved']++;

                        Log::info('Gmail customer reply saved', [
                            'ticket_id' => $ticket->id,
                            'ticket_number' => $ticketNumber,
                        ]);
                    } else {
                        $stats['duplicates']++;
                    }
                } catch (Throwable $e) {
                    $stats['errors']++;

                    Log::error('Gmail reply processing failed', [
                        'email_number' => $emailNumber,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $closeStarted = microtime(true);

            @imap_close($imap);

            Log::info('Gmail IMAP connection closed', [
                'duration_seconds' => round(
                    microtime(true) - $closeStarted,
                    2
                ),
                'total_duration_seconds' => round(
                    microtime(true) - $startedAt,
                    2
                ),
                'stats' => $stats,
            ]);

            @imap_errors();
            @imap_alerts();
        }

        return $stats;
    }

    /*
    |--------------------------------------------------------------------------
    | Extract Email Address
    |--------------------------------------------------------------------------
    */

    private function extractEmail(string $from): string
    {
        if (preg_match(
            '/<([^<>\s]+@[^<>\s]+)>/',
            $from,
            $matches
        )) {
            return strtolower(trim($matches[1]));
        }

        if (preg_match(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            $from,
            $matches
        )) {
            return strtolower(trim($matches[0]));
        }

        return '';
    }

    /*
    |--------------------------------------------------------------------------
    | Decode Email Subject
    |--------------------------------------------------------------------------
    */

    private function decodeText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $parts = imap_mime_header_decode($text);

        $result = '';

        foreach ($parts as $part) {
            $value = $part->text ?? '';
            $charset = $part->charset ?? 'default';

            if (
                strcasecmp($charset, 'default') !== 0 &&
                strcasecmp($charset, 'UTF-8') !== 0
            ) {
                $converted = @iconv(
                    $charset,
                    'UTF-8//IGNORE',
                    $value
                );

                if ($converted !== false) {
                    $value = $converted;
                }
            }

            $result .= $value;
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch Email Body
    |--------------------------------------------------------------------------
    */

    private function getEmailBody(
        $imap,
        int $emailNumber
    ): string {
        $structure = @imap_fetchstructure(
            $imap,
            $emailNumber
        );

        if (!$structure) {
            return '';
        }

        $plain = $this->findBodyPart(
            $imap,
            $emailNumber,
            $structure,
            'PLAIN'
        );

        if ($plain !== null) {
            return $plain;
        }

        $html = $this->findBodyPart(
            $imap,
            $emailNumber,
            $structure,
            'HTML'
        );

        if ($html !== null) {
            $html = preg_replace(
                '/<br\s*\/?>/i',
"\n",
$html
);

$html = preg_replace(
'/<\ /(?:p|div|li)>/i',
    "\n",
    $html
    );

    return html_entity_decode(
    strip_tags($html),
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
    );
    }

    return '';
    }

    /*
    |--------------------------------------------------------------------------
    | Find Plain Text / HTML Part
    |--------------------------------------------------------------------------
    */

    private function findBodyPart(
    $imap,
    int $emailNumber,
    $structure,
    string $wantedSubtype,
    string $partNumber = ''
    ): ?string {
    $subtype = strtoupper(
    $structure->subtype ?? ''
    );

    $disposition = strtoupper(
    $structure->disposition ?? ''
    );

    if (
    $disposition === 'ATTACHMENT' ||
    !empty($structure->ifid)
    ) {
    return null;
    }

    if (
    (int) ($structure->type ?? -1) === 0 &&
    $subtype === $wantedSubtype
    ) {
    $raw = $partNumber !== ''
    ? @imap_fetchbody(
    $imap,
    $emailNumber,
    $partNumber,
    FT_PEEK
    )
    : @imap_body(
    $imap,
    $emailNumber,
    FT_PEEK
    );

    if ($raw === false) {
    return null;
    }

    $encoding = (int) (
    $structure->encoding ?? 0
    );

    if ($encoding === 3) {
    $decoded = base64_decode(
    $raw,
    true
    );

    if ($decoded !== false) {
    $raw = $decoded;
    }
    } elseif ($encoding === 4) {
    $raw = quoted_printable_decode(
    $raw
    );
    }

    $charset = 'UTF-8';

    foreach (
    $structure->parameters ?? [] as $param
    ) {
    if (
    strtolower(
    $param->attribute ?? ''
    ) === 'charset'
    ) {
    $charset = $param->value;
    break;
    }
    }

    if (
    strcasecmp(
    $charset,
    'UTF-8'
    ) !== 0
    ) {
    $converted = @iconv(
    $charset,
    'UTF-8//IGNORE',
    $raw
    );

    if ($converted !== false) {
    $raw = $converted;
    }
    }

    return $raw;
    }

    if (!empty($structure->parts)) {
    foreach (
    $structure->parts as $index => $part
    ) {
    $childNumber = $partNumber !== ''
    ? $partNumber . '.' . ($index + 1)
    : (string) ($index + 1);

    $body = $this->findBodyPart(
    $imap,
    $emailNumber,
    $part,
    $wantedSubtype,
    $childNumber
    );

    if ($body !== null) {
    return $body;
    }
    }
    }

    return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Clean Gmail / Outlook Quoted Replies
    |--------------------------------------------------------------------------
    */

    private function cleanReply(string $body): string
    {
    $body = str_replace(
    ["\r\n", "\r"],
    "\n",
    $body
    );

    $body = str_replace(
    ["\xC2\xA0", "\xE2\x80\xAF"],
    ' ',
    $body
    );

    $body = preg_replace(
    '/^\xEF\xBB\xBF/u',
    '',
    $body
    ) ?? $body;

    $body = html_entity_decode(
    $body,
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
    );

    $lines = explode("\n", $body);

    $cleanLines = [];

    foreach ($lines as $line) {
    $trimmed = trim($line);

    // Gmail quoted conversation.
    if (preg_match(
    '/^On\s+.+\bwrote\s*:\s*$/iu',
    $trimmed
    )) {
    break;
    }

    // Gmail wraps the "wrote:" line sometimes.
    if (preg_match(
    '/^On\s+.+\bAfterSale AI Support\b/iu',
    $trimmed
    )) {
    break;
    }

    // Outlook quoted conversation.
    if (preg_match(
    '/^(From|Sent|To|Subject):\s+.+/iu',
    $trimmed
    )) {
    break;
    }

    if (preg_match(
    '/^-{2,}\s*Original Message\s*-{2,}$/iu',
    $trimmed
    )) {
    break;
    }

    if (str_starts_with($trimmed, '>')) {
    break;
    }

    // Standard signature separator.
    if (
    $trimmed === '--' ||
    $trimmed === '-- '
    ) {
    break;
    }

    if (preg_match(
    '/^sent from my (iphone|ipad|android|samsung|mobile)/iu',
    $trimmed
    )) {
    break;
    }

    if (preg_match(
    '/^get outlook for (ios|android)/iu',
    $trimmed
    )) {
    break;
    }

    $cleanLines[] = rtrim($line);
    }

    $body = trim(
    implode("\n", $cleanLines)
    );

    $body = preg_replace(
    '/<[^>]+>/u',
        ' ',
        $body
        ) ?? $body;

        $body = preg_replace(
        '/\n{3,}/u',
        "\n\n",
        $body
        ) ?? $body;

        return mb_substr(
        trim($body),
        0,
        10000
        );
        }
        }