<?php

namespace App\Services;

use App\Models\ReturnRequest;
use App\Models\SupportTicket;
use Illuminate\Support\Facades\DB;

class AiReplySafetyService
{
    /**
     * Get trusted order and return facts.
     */
    public function getVerifiedFacts(SupportTicket $ticket): array
    {
        $facts = [
            'order_verified' => false,
            'order_number' => null,
            'return_verified' => false,
            'return_number' => null,
            'request_type' => null,
            'return_status' => null,
            'refund_status' => null,
            'is_demo' => false,
            'shipment_verified' => false,
            'shipment_status' => null,
            'follow_up_verified' => false,
        ];

        if (!$ticket->order_id) {
            return $facts;
        }

        $order = DB::table('orders')
            ->where('id', $ticket->order_id)
            ->where('user_id', $ticket->user_id)
            ->where('store_id', $ticket->store_id)
            ->first();

        if (!$order) {
            return $facts;
        }

        $ticketEmail = strtolower(
            trim((string) $ticket->customer_email)
        );

        $orderEmail = strtolower(
            trim((string) $order->customer_email)
        );

        if (
            $ticketEmail === '' ||
            $ticketEmail !== $orderEmail
        ) {
            return $facts;
        }

        $facts['order_verified'] = true;
        $facts['order_number'] = $order->order_number;

        $return = ReturnRequest::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $ticket->user_id)
            ->where('store_id', $ticket->store_id)
            ->where('order_id', $order->id)
            ->whereRaw(
                'LOWER(TRIM(customer_email)) = ?',
                [$ticketEmail]
            )
            ->latest('id')
            ->first();

        if (!$return) {
            return $facts;
        }

        $facts['return_verified'] = true;
        $facts['return_number'] = $return->return_number;
        $facts['request_type'] = $return->request_type;
        $facts['return_status'] = $return->status;
        $facts['refund_status'] = $return->refund_status;
        $facts['is_demo'] = (bool) $return->is_demo;

        // Shipment and follow-up are not verified
        // by these order and return records.
        return $facts;
    }

    /**
     * Build a safe status statement using verified facts.
     */
    public function buildSafeStatusText(array $facts): string
    {
        if (!($facts['return_verified'] ?? false)) {
            return '';
        }

        $type = match ($facts['request_type'] ?? null) {
            'exchange' => 'exchange',
            'refund' => 'refund',
            default => 'return',
        };

        $number = $facts['return_number'] ?? '';

        $statusText = match ($facts['return_status'] ?? null) {
            'pending' =>
            'is currently pending and has not yet been approved',

            'approved' =>
            'has been approved',

            'rejected' =>
            'has been rejected',

            'received' =>
            'has been received',

            'completed' =>
            'is marked as completed in our request records',

            'cancelled' =>
            'has been cancelled',

            default =>
            'has no confirmed status available',
        };

        return "Your {$type} request ({$number}) {$statusText}.";
    }

    /**
     * Screen a generated reply for known risky claims.
     *
     * An empty result means no known risky pattern
     * was detected. It does not guarantee that every
     * factual statement is verified.
     */
    public function findRiskyClaims(
        string $reply,
        array $facts
    ): array {
        $issues = [];

        $text = preg_replace(
            '/\s+/u',
            ' ',
            trim($reply)
        );

        if ($text === null) {
            return ['Invalid reply text.'];
        }

        if ($text === '') {
            return ['Empty reply text.'];
        }

        /*
         * 1. Unsupported future follow-up promises.
         */
        if (!($facts['follow_up_verified'] ?? false)) {
            $followUpPatterns = [
                // Existing patterns
                '/\bwe\s+will\s+follow\s+up\b/i',
                '/\bwe(?:\'ll|\s+will)\s+(?:notify|update|contact|email|inform)\s+you\b/i',
                '/\byou\s+will\s+(?:receive|get)\s+(?:an?\s+)?update\b/i',
                '/\byou(?:\'ll|\s+will)\s+(?:be\s+)?notified\b/i',
                '/\bwe\s+will\s+keep\s+you\s+(?:updated|informed|posted)\b/i',
                '/\bwe(?:\'ll|\s+will)\s+(?:get\s+back|reach\s+out)\s+to\s+you\b/i',

                // NEW: Follow-up without "we"
                '/\bwill\s+follow\s+up\b/i',

                // NEW: Promise to get back
                '/\bwill\s+get\s+back\s+to\s+you\b/i',

                // NEW: Promise to notify customer
                '/\bwill\s+(?:notify|update|inform|contact|email)\s+you\b/i',

                // NEW: Promise to keep customer informed
                '/\bwill\s+keep\s+you\s+(?:updated|informed|posted)\b/i',
            ];
            foreach ($followUpPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $issues[] =
                        'Unsupported future follow-up or notification promise.';
                    break;
                }
            }
        }

        /*
         * 2. Return-specific checks.
         */
        if (!($facts['return_verified'] ?? false)) {
            return array_values(array_unique($issues));
        }

        $status = strtolower(
            trim((string) ($facts['return_status'] ?? ''))
        );

        /*
         * 3. Unsupported approval claims.
         *
         * "Has not yet been approved" is safe.
         * "Has been approved" requires verified status.
         */
        if ($status !== 'approved') {
            $approvalPatterns = [
                '/\byour\s+(?:return|exchange|refund|replacement|request)\s+(?:has\s+been|is)\s+approved\b/i',
                '/\bwe\s+have\s+approved\s+your\s+(?:return|exchange|refund|replacement|request)\b/i',
                '/\b(?:return|exchange|refund|replacement)\s+request\s+(?:has\s+been|is)\s+approved\b/i',
            ];

            foreach ($approvalPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $issues[] = 'Unverified approval claim.';
                    break;
                }
            }
        }

        /*
         * 4. Unsupported shipment claims.
         *
         * A pending request does not establish whether
         * a shipment has or has not happened.
         */
        if (!($facts['shipment_verified'] ?? false)) {
            $shipmentPatterns = [
                '/\bhas\s+(?:already\s+)?been\s+shipped\b/i',
                '/\bhas\s+shipped\b/i',
                '/\bwas\s+shipped\b/i',
                '/\bhas\s+not\s+been\s+shipped\b/i',
                '/\bhasn\'t\s+shipped\b/i',
                '/\bwas\s+not\s+shipped\b/i',
                '/\bis\s+on\s+its\s+way\b/i',
                '/\bwill\s+ship\s+soon\b/i',
                '/\bhas\s+been\s+dispatched\b/i',
                '/\bhas\s+not\s+been\s+dispatched\b/i',
                '/\breplacement\s+(?:has\s+been|was)\s+(?:shipped|dispatched)\b/i',
            ];

            foreach ($shipmentPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $issues[] = 'Unsupported shipment claim.';
                    break;
                }
            }
        }

        /*
         * 5. Unsupported active-review claims.
         *
         * "Currently pending" is allowed.
         * "Pending review" is blocked because
         * active review is not verified.
         */
        $reviewPatterns = [
            '/\bcurrently\s+reviewing\b/i',
            '/\bactively\s+reviewing\b/i',
            '/\bbeing\s+reviewed\b/i',
            '/\bunder\s+review\b/i',
            '/\bpending\s+(?:staff\s+)?review\b/i',
            '/\bawaiting\s+(?:staff\s+)?review\b/i',
            '/\bstill\s+in\s+review\b/i',
            '/\bteam\s+is\s+reviewing\b/i',
            '/\bteam\s+is\s+processing\b/i',
        ];

        foreach ($reviewPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                $issues[] = 'Unsupported active-review claim.';
                break;
            }
        }

        /*
         * 6. Demo records cannot prove real
         * payments or replacement shipments.
         */
        if ($facts['is_demo'] ?? false) {
            $demoPatterns = [
                '/\brefund\s+has\s+been\s+issued\b/i',
                '/\bmoney\s+has\s+been\s+sent\b/i',
                '/\bpayment\s+has\s+been\s+transferred\b/i',
                '/\breplacement\s+has\s+been\s+dispatched\b/i',
            ];

            foreach ($demoPatterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $issues[] =
                        'Demo request described as a real transaction.';
                    break;
                }
            }
        }

        return array_values(array_unique($issues));
    }
}
