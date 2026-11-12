<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\TicketReplyApproval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Throwable;
use App\Services\AiReplyService;
use App\Services\AiTicketAnalysisService;
use App\Services\KnowledgeBaseReplyService;
use App\Services\SubscriptionLimitService;
use App\Services\AiUsageLimitService;
use App\Services\AiUsageLimitException;

class TicketController extends Controller
{
    private const STATUSES = [
        'open',
        'in_progress',
        'waiting_customer',
        'resolved',
        'closed',
    ];

    private const PRIORITIES = [
        'low',
        'medium',
        'high',
        'urgent',
    ];

    private const CATEGORIES = [
        'general',
        'delivery',
        'damaged_product',
        'wrong_product',
        'return',
        'refund',
        'other',
    ];

    private const APPROVAL_TYPES = [
        'general_support',
        'verification_request',
        'order_specific',
    ];

    private function ownerId(Request $request): int
    {
        return (int) $request->user()->id;
    }

    private function ticketQuery(Request $request)
    {
        return SupportTicket::query()
            ->where('user_id', $this->ownerId($request))
            ->whereHas('store', function ($query) use ($request) {
                $query->where('user_id', $this->ownerId($request));
            });
    }

    private function findTicket(
        Request $request,
        int $id
    ): SupportTicket {
        return $this->ticketQuery($request)
            ->findOrFail($id);
    }

    private function normalize(?string $value): string
    {
        return Str::lower(trim((string) $value));
    }

    private function ticketData(SupportTicket $ticket): array
    {
        $ticket->loadMissing(['store', 'order']);

        return array_merge($ticket->toArray(), [
            'store_name' => $ticket->store?->store_name,
            'order_number' => $ticket->order?->order_number,
        ]);
    }

    private function orderContext(SupportTicket $ticket): array
    {
        $order = null;

        if ($ticket->order_id) {
            $order = Order::query()
                ->where('id', $ticket->order_id)
                ->where('user_id', $ticket->user_id)
                ->where('store_id', $ticket->store_id)
                ->first();
        }

        $verification = [
            'status' => 'no_linked_order',
            'message' => 'No linked order found',
            'customerEmailMatch' => false,
            'customerNameMatch' => false,
        ];

        $items = collect();

        if ($order) {
            $items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->get();

            $emailMatch = $this->normalize($ticket->customer_email) !== ''
                && $this->normalize($ticket->customer_email)
                === $this->normalize($order->customer_email);

            $nameMatch = $this->normalize($ticket->customer_name) !== ''
                && $this->normalize($ticket->customer_name)
                === $this->normalize($order->customer_name);

            $status = !$emailMatch
                ? 'customer_mismatch'
                : ($items->isEmpty() ? 'no_products' : 'verified');

            $messages = [
                'customer_mismatch' =>
                'Customer email does not match the linked order',
                'no_products' =>
                'No products found in this order',
                'verified' =>
                'Customer email and order products verified',
            ];

            $verification = [
                'status' => $status,
                'message' => $messages[$status],
                'customerEmailMatch' => $emailMatch,
                'customerNameMatch' => $nameMatch,
            ];
        }

        return [
            'order' => $order,
            'items' => $items,
            'verification' => $verification,
            'verified' => $verification['status'] === 'verified',
        ];
    }

    private function verificationRequestPatterns(): array
    {
        return [
            '/\bverify\s+(your|the|this)?\s*(email|identity|order|account|ownership)\b/i',
            '/\bconfirm\s+(your|the)?\s*(registered|billing|order)\s*email\b/i',
            '/\bprovide\s+(your|the)?\s*(order\s*(number|id)|registered\s*email)\b/i',
            '/\bshare\s+(your|the)?\s*(order\s*(number|id)|registered\s*email)\b/i',
            '/\b(send|provide|share)\s+(us\s+)?(your\s+)?order\s+number\b/i',
            '/\bverification\s+(is\s+)?required\b/i',
        ];
    }

    private function requestsVerification(string $message): bool
    {
        foreach ($this->verificationRequestPatterns() as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    private function containsPrivateOrderData(
        SupportTicket $ticket,
        array $context,
        string $message
    ): bool {
        if ($context['verified'] || !$context['order']) {
            return false;
        }

        $order = $context['order'];
        $values = [$order->order_number];

        if (!$context['verification']['customerEmailMatch']) {
            $values[] = $order->customer_email;
        }

        if (
            $this->normalize($ticket->customer_name)
            !== $this->normalize($order->customer_name)
        ) {
            $values[] = $order->customer_name;
        }

        foreach ($context['items'] as $item) {
            $values[] = $item->product_name ?? null;
            $values[] = $item->product_sku ?? null;
        }

        $normalizedMessage = $this->normalize($message);

        foreach ($values as $value) {
            $value = $this->normalize($value);

            if (mb_strlen($value) < 3) {
                continue;
            }

            $pattern = '/(?<![a-z0-9])'
                . preg_quote($value, '/')
                . '(?![a-z0-9])/iu';

            if (preg_match($pattern, $normalizedMessage)) {
                return true;
            }
        }

        $patterns = [
            '/\byour\s+order\s+(total|amount)\s+is\b/i',
            '/\byour\s+order\s+(status|tracking\s+number)\s+is\b/i',
            '/\byour\s+order\s+contains\b/i',
            '/\byour\s+order\s+was\s+(shipped|delivered|cancelled|refunded)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    private function validateReplySafety(
        SupportTicket $ticket,
        array $context,
        string $message,
        string $approvalType
    ): void {
        if ($approvalType === 'order_specific' && !$context['verified']) {
            throw ValidationException::withMessages([
                'approval_type' =>
                'Order-specific replies require a verified linked order.',
            ]);
        }

        if ($approvalType === 'verification_request' && $context['verified']) {
            throw ValidationException::withMessages([
                'approval_type' =>
                'Order is already verified. Use another approval type.',
            ]);
        }

        if (
            $approvalType === 'general_support'
            && $this->requestsVerification($message)
        ) {
            throw ValidationException::withMessages([
                'approval_type' =>
                'This reply requests verification. Use verification_request.',
            ]);
        }

        if (
            $this->containsPrivateOrderData($ticket, $context, $message)
        ) {
            throw ValidationException::withMessages([
                'message' =>
                'Private linked-order information cannot be disclosed before verification.',
            ]);
        }

        if (!$context['verified']) {
            $genericGreeting = preg_match(
                '/^hello(?:\s*[,!.]|\s*\n)/i',
                $message
            );

            $namedGreeting = preg_match(
                '/^(hello|dear)\s+[^\n,]+,/i',
                $message
            );

            if (!$genericGreeting || $namedGreeting) {
                throw ValidationException::withMessages([
                    'message' =>
                    'For an unverified ticket, start with "Hello," without a customer name.',
                ]);
            }
        }
    }

    private function verificationCooldown(
        SupportTicket $ticket
    ): ?TicketReplyApproval {
        return TicketReplyApproval::query()
            ->recentVerificationEmail(
                (int) $ticket->id,
                (int) $ticket->user_id
            )
            ->latest('sent_at')
            ->first();
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->ticketQuery($request)
            ->with(['store', 'order'])
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->store_id);
        }

        $tickets = $query->get()
            ->map(fn($ticket) => $this->ticketData($ticket));

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
            'total' => $tickets->count(),
        ]);
    }


    public function store(
        Request $request,
        SubscriptionLimitService $subscriptionLimits
    ): JsonResponse {
        $ownerId = $this->ownerId($request);

        $data = $request->validate([
            'store_id' => [
                'required',
                'integer',
                Rule::exists('stores', 'id')
                    ->where('user_id', $ownerId),
            ],
            'order_id' => [
                'nullable',
                'integer',
                Rule::exists('orders', 'id')
                    ->where('user_id', $ownerId),
            ],
            'customer_name' => 'required|string|max:150',
            'customer_email' => 'nullable|email|max:255',
            'subject' => 'required|string|max:255',
            'description' => 'required|string|max:10000',
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'priority' => ['nullable', Rule::in(self::PRIORITIES)],
        ]);

        $store = Store::query()
            ->where('user_id', $ownerId)
            ->findOrFail($data['store_id']);

        $order = null;

        if (!empty($data['order_id'])) {
            $order = Order::query()
                ->where('user_id', $ownerId)
                ->where('store_id', $store->id)
                ->find($data['order_id']);

            if (!$order) {
                throw ValidationException::withMessages([
                    'order_id' => 'Order not found for this store.',
                ]);
            }
        }

        $result = DB::transaction(function () use (
            $data,
            $ownerId,
            $store,
            $order,
            $subscriptionLimits
        ) {
            // Serialize ticket creation for this owner.
            DB::table('users')
                ->where('id', $ownerId)
                ->lockForUpdate()
                ->first();

            $plan = $subscriptionLimits->getActivePlan($ownerId);

            if (!$plan) {
                return [
                    'error' => 'An active subscription is required.',
                    'code' => 403,
                ];
            }

            $limit = (int) $plan->max_tickets_per_month;

            $used = SupportTicket::query()
                ->where('user_id', $ownerId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<', now()->startOfMonth()->addMonth())
                ->count();

            if ($limit !== -1 && $used >= $limit) {
                return [
                    'error' => "Monthly ticket limit reached ({$limit}). Please upgrade your subscription.",
                    'code' => 403,
                    'limit' => $limit,
                    'used' => $used,
                ];
            }

            $ticket = SupportTicket::create([
                'user_id' => $ownerId,
                'store_id' => $store->id,
                'order_id' => $order?->id,
                'ticket_number' =>
                'TKT-' . strtoupper(bin2hex(random_bytes(8))),
                'customer_name' => trim($data['customer_name']),
                'customer_email' =>
                trim($data['customer_email'] ?? '') ?: null,
                'subject' => trim($data['subject']),
                'description' => trim($data['description']),
                'category' => $data['category'] ?? 'general',
                'priority' => $data['priority'] ?? 'medium',
                'status' => 'open',
                'is_demo' =>
                $store->status !== 'connected'
                    || (bool) ($order?->is_demo ?? false),
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_type' => 'customer',
                'sender_id' => null,
                'message' => $ticket->description,
                'is_ai_generated' => false,
                'email_status' => 'not_applicable',
            ]);

            return [
                'ticket' => $ticket,
            ];
        }, 3);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'limit' => $result['limit'] ?? null,
                'used' => $result['used'] ?? null,
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Ticket created successfully',
            'ticket' => $this->ticketData($result['ticket']),
        ], 201);
    }


    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $this->findTicket($request, $id);
        $context = $this->orderContext($ticket);

        $data = $this->ticketData($ticket);

        $data['order_verification'] = $context['verification'];

        if ($context['order']) {
            $linkedOrder = $context['order']->toArray();
            $linkedOrder['items'] = $context['items']->toArray();

            // Only disclose linked-order details after verification.
            $data['linked_order'] = $context['verified']
                ? $linkedOrder
                : null;
        } else {
            $data['linked_order'] = null;
        }

        $messages = TicketMessage::query()
            ->where('ticket_id', $ticket->id)
            ->conversation()
            ->get();

        return response()->json([
            'success' => true,
            'ticket' => $data,
            'messages' => $messages,
        ]);
    }

    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUSES)],
        ]);

        $ticket = $this->findTicket($request, $id);

        $ticket->status = $data['status'];

        $ticket->resolved_at = $data['status'] === 'resolved'
            ? now()
            : null;

        $ticket->closed_at = $data['status'] === 'closed'
            ? now()
            : null;

        $ticket->save();

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully',
            'ticket' => $this->ticketData($ticket),
        ]);
    }

    public function approveReply(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'message' => 'required|string|max:10000',
            'approval_type' => [
                'nullable',
                Rule::in(self::APPROVAL_TYPES),
            ],
        ]);

        $message = trim($data['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Reply cannot be empty.',
            ]);
        }

        $result = DB::transaction(function () use (
            $request,
            $id,
            $data,
            $message
        ) {
            $ticket = $this->ticketQuery($request)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($ticket->status === 'closed') {
                throw ValidationException::withMessages([
                    'ticket' => 'Cannot reply to a closed ticket.',
                ]);
            }

            $context = $this->orderContext($ticket);

            $approvalType = $data['approval_type']
                ?? ($context['verified']
                    ? 'order_specific'
                    : 'verification_request');

            $this->validateReplySafety(
                $ticket,
                $context,
                $message,
                $approvalType
            );

            if ($approvalType === 'verification_request') {
                $recent = $this->verificationCooldown($ticket);

                if ($recent) {
                    return [
                        'cooldown' => true,
                        'approval' => $recent,
                    ];
                }
            }

            TicketReplyApproval::query()
                ->where('ticket_id', $ticket->id)
                ->where('user_id', $ticket->user_id)
                ->where('status', 'approved')
                ->update(['status' => 'revoked']);

            $approval = TicketReplyApproval::create([
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'message' => $message,
                'message_hash' => hash('sha256', $message),
                'approval_type' => $approvalType,
                'approved_order_id' => $context['order']?->id,
                'verification_status' =>
                $context['verification']['status'],
                'status' => 'approved',
                'approved_at' => now(),
                'expires_at' => now()->addMinutes(15),
                'email_status' => 'pending',
            ]);

            return [
                'cooldown' => false,
                'approval' => $approval,
                'verification' => $context['verification'],
            ];
        }, 3);

        if ($result['cooldown']) {
            $recent = $result['approval'];

            return response()->json([
                'success' => false,
                'cooldown' => true,
                'message' =>
                'A verification email was already sent within the last 24 hours.',
                'last_sent_at' => $recent->sent_at,
                'next_allowed_at' => $recent->sent_at?->copy()
                    ->addHours(24),
            ], 429);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reply approved successfully',
            'approval' => $result['approval'],
            'approval_type' =>
            $result['approval']->approval_type,
            'order_verification' => $result['verification'],
            'requires_approval' => false,
            'auto_sent' => false,
        ]);
    }


    public function sendMessage(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        $message = trim($data['message']);

        if ($message === '') {
            throw ValidationException::withMessages([
                'message' => 'Reply cannot be empty.',
            ]);
        }

        // Step 1: Validate approval and save the reply.
        // Keep the database transaction separate from SMTP sending.
        $result = DB::transaction(function () use (
            $request,
            $id,
            $message
        ) {
            $ticket = $this->ticketQuery($request)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($ticket->status === 'closed') {
                return [
                    'error' => 'Cannot reply to a closed ticket.',
                    'code' => 422,
                ];
            }

            $approval = TicketReplyApproval::query()
                ->where('ticket_id', $ticket->id)
                ->where('user_id', $ticket->user_id)
                ->where('status', 'approved')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$approval || !$approval->isValid()) {
                return [
                    'error' => 'Please approve this reply before sending it.',
                    'code' => 403,
                ];
            }

            if (
                !$approval->matchesMessage($message)
                || !hash_equals($approval->message, $message)
            ) {
                return [
                    'error' => 'Reply changed after approval. Approve it again.',
                    'code' => 403,
                ];
            }

            $context = $this->orderContext($ticket);

            if (
                (string) ($approval->approved_order_id ?? '')
                !== (string) ($context['order']?->id ?? '')
                || $approval->verification_status
                !== $context['verification']['status']
            ) {
                return [
                    'error' => 'Order verification changed. Approve the reply again.',
                    'code' => 409,
                ];
            }

            try {
                $this->validateReplySafety(
                    $ticket,
                    $context,
                    $message,
                    $approval->approval_type
                );
            } catch (ValidationException $exception) {
                return [
                    'error' => $exception->getMessage(),
                    'code' => 403,
                ];
            }

            if ($approval->isVerificationRequest()) {
                $recent = $this->verificationCooldown($ticket);

                if ($recent) {
                    return [
                        'error' => 'Verification email cooldown is active.',
                        'code' => 429,
                        'cooldown' => true,
                    ];
                }
            }

            $customerEmail = trim(
                (string) $ticket->customer_email
            );

            $hasValidEmail = filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            ) !== false;

            $ticketMessage = TicketMessage::create([
                'ticket_id' => $ticket->id,
                'sender_type' => 'owner',
                'sender_id' => $ticket->user_id,
                'message' => $message,
                'is_ai_generated' => false,
                'email_status' => $hasValidEmail
                    ? 'pending'
                    : 'skipped',
            ]);

            // Single-use approval: prevents duplicate replies.
            $approval->markAsUsed($ticketMessage);

            if (!$hasValidEmail) {
                $approval->recordEmailStatus('skipped');
            }

            $ticket->touch();

            return [
                'ticket_message' => $ticketMessage,
                'approval' => $approval,
                'verification' => $context['verification'],
                'customer_email' => $customerEmail,
                'has_valid_email' => $hasValidEmail,
                'ticket_number' => $ticket->ticket_number,
                'ticket_subject' => $ticket->subject,
            ];
        }, 3);

        if (isset($result['error'])) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'cooldown' => $result['cooldown'] ?? false,
            ], $result['code']);
        }

        $ticketMessage = $result['ticket_message'];
        $approval = $result['approval'];

        $emailStatus = 'skipped';
        $emailReason = 'Customer email address is missing or invalid.';

        // Step 2: Send the exact approved reply through Gmail SMTP.
        if ($result['has_valid_email']) {
            try {
                $emailBody = $message
                    . "\n\n"
                    . "----------------------------------\n"
                    . "Ticket: {$result['ticket_number']}\n"
                    . "AfterSale AI Support\n";

                Mail::raw($emailBody, function ($mail) use ($result) {
                    $mail->to($result['customer_email'])
                        ->subject(
                            'Re: ' . $result['ticket_subject']
                                . ' [' . $result['ticket_number'] . ']'
                        );
                });

                $emailStatus = 'sent';
                $emailReason = null;
            } catch (Throwable $exception) {
                $emailStatus = 'failed';

                $emailReason = 'SMTP could not send the email.';

                Log::error('Ticket reply SMTP failed', [
                    'ticket_id' => $ticketMessage->ticket_id,
                    'ticket_message_id' => $ticketMessage->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        // Step 3: Save the actual email delivery attempt status.
        // This records SMTP acceptance, not inbox delivery.
        try {
            $ticketMessage->email_status = $emailStatus;

            $ticketMessage->email_sent_at = $emailStatus === 'sent'
                ? now()
                : null;

            $ticketMessage->save();

            $approval->recordEmailStatus($emailStatus);
        } catch (Throwable $exception) {
            Log::error('Ticket email status update failed', [
                'ticket_message_id' => $ticketMessage->id,
                'approval_id' => $approval->id,
                'email_status' => $emailStatus,
                'exception' => $exception->getMessage(),
            ]);

            // Do not attempt SMTP again here: the email may
            // already have been accepted by the mail server.
        }

        $responseMessage = match ($emailStatus) {
            'sent' => 'Reply saved and email accepted by SMTP.',
            'failed' => 'Reply saved, but email sending failed.',
            default => 'Reply saved. Email notification was skipped.',
        };

        return response()->json([
            'success' => true,
            'message' => $responseMessage,
            'ticket_message' => $ticketMessage->fresh(),
            'approval_id' => $approval->id,
            'approval_type' => $approval->approval_type,
            'order_verification' => $result['verification'],
            'email_notification' => [
                'status' => $emailStatus,
                'sent' => $emailStatus === 'sent',
                'reason' => $emailReason,
            ],
        ], 201);
    }


    public function applyAISuggestions(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'category' => [
                'required',
                Rule::in(self::CATEGORIES),
            ],
            'priority' => [
                'required',
                Rule::in(self::PRIORITIES),
            ],
        ]);

        $ticket = DB::transaction(function () use (
            $request,
            $id,
            $data
        ) {
            $ticket = $this->ticketQuery($request)
                ->lockForUpdate()
                ->findOrFail($id);

            $ticket->update([
                'category' => $data['category'],
                'priority' => $data['priority'],
            ]);

            return $ticket;
        });

        return response()->json([
            'success' => true,
            'message' => 'AI suggestions applied successfully',
            'ticket' => $this->ticketData($ticket),
        ]);
    }



    /**
     * Generate Gemini AI reply draft for a support ticket.
     *
     * This endpoint NEVER sends an email.
     * Owner approval is required before sending.
     */
    public function generateAIReply(
        Request $request,
        int $id,
        AiReplyService $aiReplyService,
        AiUsageLimitService $aiUsageLimitService
    ): JsonResponse {
        $ticket = $this->findTicket($request, $id);

        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate a reply for a closed ticket.',
            ], 422);
        }

        $ownerId = (int) $request->user()->id;

        try {
            $usageLog = $aiUsageLimitService->reserve(
                $ownerId,
                (int) $ticket->id,
                'ai_reply'
            );
        } catch (AiUsageLimitException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'ai_usage' => $exception->usage,
            ], 403);
        } catch (Throwable $exception) {
            Log::error('AI usage reservation failed', [
                'ticket_id' => $ticket->id,
                'user_id' => $ownerId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to check AI usage. Please try again.',
            ], 500);
        }

        try {
            $result = $aiReplyService->generate($ticket);

            $aiUsageLimitService->complete($usageLog);

            return response()->json([
                'success' => true,
                'message' => 'AI reply generated successfully.',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'latest_customer_message_id' =>
                $result['latest_customer_message_id'],
                'customer_message' => $result['customer_message'],
                'suggested_reply' => $result['suggested_reply'],
                'model' => $result['model'],
                'review_model' => $result['review_model'],
                'policy_reviewed' => $result['policy_reviewed'],
                'requires_approval' => true,
                'auto_sent' => false,
                'ai_usage' => $aiUsageLimitService->getUsage($ownerId),
            ]);
        } catch (Throwable $exception) {
            try {
                $aiUsageLimitService->fail($usageLog);
            } catch (Throwable $logException) {
                Log::error('Failed to release AI usage reservation', [
                    'usage_log_id' => $usageLog->id,
                    'error' => $logException->getMessage(),
                ]);
            }

            Log::error('Gemini AI reply generation failed', [
                'ticket_id' => $ticket->id,
                'user_id' => $ownerId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AI reply generation failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Generate a Knowledge Base reply draft for a ticket.
     *
     * This endpoint never sends a customer message.
     * Human approval is required before sending.
     */
    public function generateKBReply(
        Request $request,
        int $id,
        KnowledgeBaseReplyService $knowledgeBaseReplyService,
        AiUsageLimitService $aiUsageLimitService
    ): JsonResponse {
        $ticket = $this->findTicket($request, $id);

        if ($ticket->status === 'closed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot generate a reply for a closed ticket.',
            ], 422);
        }

        $ownerId = (int) $request->user()->id;

        try {
            $usageLog = $aiUsageLimitService->reserve(
                $ownerId,
                (int) $ticket->id,
                'kb_reply'
            );
        } catch (AiUsageLimitException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'ai_usage' => $exception->usage,
            ], 403);
        } catch (Throwable $exception) {
            Log::error('Knowledge Base usage reservation failed', [
                'ticket_id' => $ticket->id,
                'user_id' => $ownerId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to check AI usage. Please try again.',
            ], 500);
        }

        try {
            $result = $knowledgeBaseReplyService->generate($ticket);

            $aiUsageLimitService->complete($usageLog);

            return response()->json([
                'success' => true,
                'message' => 'Knowledge Base reply generated successfully.',
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'reply' => $result['reply'],
                'sources' => $result['sources'],
                'information_status' => $result['information_status'],
                'latest_customer_message_id' =>
                $result['latest_customer_message_id'],
                'model' => $result['model'],
                'requires_approval' => true,
                'auto_sent' => false,
                'ai_usage' => $aiUsageLimitService->getUsage($ownerId),
            ], 200);
        } catch (Throwable $exception) {
            try {
                $aiUsageLimitService->fail($usageLog);
            } catch (Throwable $logException) {
                Log::error('Failed to release KB usage reservation', [
                    'usage_log_id' => $usageLog->id,
                    'error' => $logException->getMessage(),
                ]);
            }

            Log::error('Knowledge Base reply generation failed', [
                'ticket_id' => $ticket->id,
                'user_id' => $ownerId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Knowledge Base reply generation failed.',
            ], 500);
        }
    }

    /**
     * Generate AI ticket analysis.
     *
     * POST /api/ai/tickets/{id}/analyze
     */
    public function analyzeTicket(
        Request $request,
        int $id,
        AiTicketAnalysisService $aiAnalysisService,
        AiUsageLimitService $aiUsageLimitService
    ): JsonResponse {
        try {
            $ticket = $this->findTicket($request, $id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found.',
            ], 404);
        }

        $ownerId = (int) $request->user()->id;

        try {
            $usageLog = $aiUsageLimitService->reserve(
                $ownerId,
                (int) $ticket->id,
                'ticket_analysis'
            );
        } catch (AiUsageLimitException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'ai_usage' => $exception->usage,
            ], 403);
        } catch (Throwable $exception) {
            Log::error('AI analysis usage reservation failed', [
                'ticket_id' => $ticket->id,
                'user_id' => $ownerId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to check AI usage. Please try again.',
            ], 500);
        }

        try {
            $result = $aiAnalysisService->analyze($ticket);

            $aiUsageLimitService->complete($usageLog);

            $result['ai_usage'] = $aiUsageLimitService->getUsage($ownerId);

            return response()->json($result, 200);
        } catch (Throwable $exception) {
            try {
                $aiUsageLimitService->fail($usageLog);
            } catch (Throwable $logException) {
                Log::error('Failed to release analysis usage reservation', [
                    'usage_log_id' => $usageLog->id,
                    'error' => $logException->getMessage(),
                ]);
            }

            Log::error('AI ticket analysis failed', [
                'ticket_id' => $ticket->id,
                'user_id' => $ownerId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'AI ticket analysis failed. Please try again.',
            ], 500);
        }
    }
}
