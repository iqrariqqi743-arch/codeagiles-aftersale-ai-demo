<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Store;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReturnController extends Controller
{
    private const RETURN_TRANSITIONS = [
        'pending' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['received', 'completed', 'cancelled'],
        'received' => ['completed'],
        'rejected' => [],
        'completed' => [],
        'cancelled' => [],
    ];

    private const REFUND_TRANSITIONS = [
        'not_started' => ['pending'],
        'pending' => ['processing', 'failed'],
        'processing' => ['completed', 'failed'],
        'failed' => ['pending'],
        'completed' => [],
    ];

    /**
     * GET /api/returns
     */
    public function index(Request $request): JsonResponse
    {
        $returns = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'store:id,store_name',
                'order:id,order_number',
            ])
            ->latest()
            ->get()
            ->map(
                fn(ReturnRequest $return) =>
                $this->formatReturn($return)
            );

        return response()->json([
            'success' => true,
            'message' => 'Return requests fetched successfully.',
            'returns' => $returns,
            'total' => $returns->count(),
        ]);
    }

    /**
     * POST /api/returns
     */
    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'store_id' => ['required', 'integer'],
            'order_id' => ['required', 'integer'],
            'ticket_id' => ['nullable', 'integer', 'min:1'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => [
                'nullable',
                'email',
                'max:255',
            ],
            'request_type' => [
                'required',
                Rule::in(['return', 'exchange', 'refund']),
            ],
            'reason' => [
                'required',
                'string',
                'max:100',
            ],
            'description' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'refund_amount' => [
                'required',
                'numeric',
                'min:0',
                'decimal:0,2',
            ],
        ]);

        $store = Store::query()
            ->where('id', $validated['store_id'])
            ->where('user_id', $userId)
            ->first();

        if (!$store) {
            throw ValidationException::withMessages([
                'store_id' => [
                    'The selected store is invalid.',
                ],
            ]);
        }

        $order = Order::query()
            ->where('id', $validated['order_id'])
            ->where('store_id', $store->id)
            ->where('user_id', $userId)
            ->first();

        if (!$order) {
            throw ValidationException::withMessages([
                'order_id' => [
                    'The selected order does not belong to this store.',
                ],
            ]);
        }

        $amount = round(
            (float) $validated['refund_amount'],
            2
        );

        $orderTotal = round(
            (float) $order->total_amount,
            2
        );

        if ($amount > $orderTotal) {
            throw ValidationException::withMessages([
                'refund_amount' => [
                    'Refund amount cannot exceed the order total.',
                ],
            ]);
        }

        if (
            $validated['request_type'] === 'exchange' &&
            $amount > 0
        ) {
            throw ValidationException::withMessages([
                'refund_amount' => [
                    'Exchange requests must have a zero refund amount.',
                ],
            ]);
        }

        // Only link a ticket after checking ownership,
        // store, order and customer email.
        $ticketId = null;

        if (!empty($validated['ticket_id'])) {
            $ticket = $this->validateTicket(
                (int) $validated['ticket_id'],
                $userId,
                (int) $store->id,
                (int) $order->id,
                $validated['customer_email'] ?? null,
                $order->customer_email
            );

            $ticketId = $ticket->id;
        }

        $return = DB::transaction(function () use (
            $validated,
            $userId,
            $store,
            $order,
            $amount,
            $ticketId
        ) {
            $returnNumber = 'RET-' . strtoupper(
                Str::random(12)
            );

            $return = ReturnRequest::create([
                'user_id' => $userId,
                'store_id' => $store->id,
                'order_id' => $order->id,
                'ticket_id' => $ticketId,
                'return_number' => $returnNumber,
                'customer_name' => $validated['customer_name'],
                'customer_email' =>
                $validated['customer_email'] ?? null,
                'request_type' => $validated['request_type'],
                'reason' => $validated['reason'],
                'description' =>
                $validated['description'] ?? null,
                'status' => 'pending',
                'refund_amount' => $amount,
                'currency' => $order->currency ?: 'USD',
                'refund_status' => 'not_started',
                'admin_notes' => null,
                'is_demo' => (bool) $order->is_demo,
            ]);

            return $return->load([
                'store:id,store_name',
                'order:id,order_number',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Return request created successfully.',
            'return_request' => $this->formatReturn($return),
        ], 201);
    }

    /**
     * GET /api/returns/{id}
     */
    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $return = $this->findOwnedReturn(
            $request,
            $id
        );

        return response()->json([
            'success' => true,
            'message' => 'Return request fetched successfully.',
            'return_request' => $this->formatReturn($return),
        ]);
    }

    /**
     * PATCH /api/returns/{id}/link-ticket
     *
     * Link an existing return request to a valid ticket.
     */
    public function linkTicket(
        Request $request,
        int $id
    ): JsonResponse {
        $validated = $request->validate([
            'ticket_id' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        $return = DB::transaction(function () use (
            $request,
            $id,
            $validated
        ) {
            $return = ReturnRequest::query()
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Never silently overwrite a different link.
            if (
                $return->ticket_id !== null &&
                (int) $return->ticket_id !==
                (int) $validated['ticket_id']
            ) {
                throw ValidationException::withMessages([
                    'ticket_id' => [
                        'This return request is already linked to another ticket.',
                    ],
                ]);
            }

            $order = Order::query()
                ->where('id', $return->order_id)
                ->where('store_id', $return->store_id)
                ->where('user_id', $return->user_id)
                ->first();

            if (!$order) {
                throw ValidationException::withMessages([
                    'order_id' => [
                        'The return order could not be verified.',
                    ],
                ]);
            }

            $ticket = $this->validateTicket(
                (int) $validated['ticket_id'],
                (int) $return->user_id,
                (int) $return->store_id,
                (int) $return->order_id,
                $return->customer_email,
                $order->customer_email
            );

            $return->ticket_id = $ticket->id;
            $return->save();

            return $return->load([
                'store:id,store_name',
                'order:id,order_number',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Support ticket linked successfully.',
            'return_request' => $this->formatReturn($return),
        ]);
    }

    /**
     * PATCH /api/returns/{id}/status
     */
    public function updateStatus(
        Request $request,
        int $id
    ): JsonResponse {
        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in([
                    'pending',
                    'approved',
                    'rejected',
                    'received',
                    'completed',
                    'cancelled',
                ]),
            ],
            'admin_notes' => [
                'nullable',
                'string',
                'max:10000',
            ],
        ]);

        $return = DB::transaction(function () use (
            $request,
            $id,
            $validated
        ) {
            $return = ReturnRequest::query()
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = $return->status;
            $nextStatus = $validated['status'];

            if ($currentStatus !== $nextStatus) {
                $allowed =
                    self::RETURN_TRANSITIONS[$currentStatus]
                    ?? [];

                if (!in_array($nextStatus, $allowed, true)) {
                    throw ValidationException::withMessages([
                        'status' => [
                            "Cannot change return status from {$currentStatus} to {$nextStatus}.",
                        ],
                    ]);
                }

                $return->status = $nextStatus;
            }

            if (
                array_key_exists(
                    'admin_notes',
                    $validated
                )
            ) {
                $return->admin_notes =
                    $validated['admin_notes'];
            }

            $return->save();

            return $return->load([
                'store:id,store_name',
                'order:id,order_number',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Return status updated successfully.',
            'return_request' => $this->formatReturn($return),
        ]);
    }

    /**
     * PATCH /api/returns/{id}/refund
     *
     * Manual tracking only.
     * No real payment is executed.
     */
    public function updateRefund(
        Request $request,
        int $id
    ): JsonResponse {
        $validated = $request->validate([
            'refund_status' => [
                'required',
                Rule::in([
                    'not_started',
                    'pending',
                    'processing',
                    'completed',
                    'failed',
                ]),
            ],
        ]);

        $return = DB::transaction(function () use (
            $request,
            $id,
            $validated
        ) {
            $return = ReturnRequest::query()
                ->where('id', $id)
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                !in_array(
                    $return->status,
                    ['approved', 'received', 'completed'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'refund_status' => [
                        'The return must be approved before refund tracking can begin.',
                    ],
                ]);
            }

            if ($return->request_type === 'exchange') {
                throw ValidationException::withMessages([
                    'refund_status' => [
                        'Refund tracking is not available for exchanges.',
                    ],
                ]);
            }

            if (
                (float) $return->refund_amount <= 0
            ) {
                throw ValidationException::withMessages([
                    'refund_status' => [
                        'Refund amount must be greater than zero.',
                    ],
                ]);
            }

            $currentStatus = $return->refund_status;
            $nextStatus = $validated['refund_status'];

            $allowed =
                self::REFUND_TRANSITIONS[$currentStatus]
                ?? [];

            if (
                !in_array(
                    $nextStatus,
                    $allowed,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'refund_status' => [
                        "Cannot change refund status from {$currentStatus} to {$nextStatus}.",
                    ],
                ]);
            }

            $return->refund_status = $nextStatus;
            $return->save();

            return $return->load([
                'store:id,store_name',
                'order:id,order_number',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' =>
            'Refund tracking updated successfully. No payment was executed.',
            'return_request' => $this->formatReturn($return),
        ]);
    }

    /**
     * Verify ticket ownership and customer identity.
     */
    private function validateTicket(
        int $ticketId,
        int $userId,
        int $storeId,
        int $orderId,
        ?string $customerEmail,
        ?string $orderEmail
    ): SupportTicket {
        $ticket = SupportTicket::query()
            ->where('id', $ticketId)
            ->where('user_id', $userId)
            ->where('store_id', $storeId)
            ->where('order_id', $orderId)
            ->first();

        if (!$ticket) {
            throw ValidationException::withMessages([
                'ticket_id' => [
                    'The selected ticket does not match this store and order.',
                ],
            ]);
        }

        $normalize = fn($email) => strtolower(
            trim((string) $email)
        );

        $ticketEmail = $normalize(
            $ticket->customer_email
        );

        $orderEmail = $normalize($orderEmail);
        $returnEmail = $normalize($customerEmail);

        if (
            $ticketEmail === '' ||
            $orderEmail === '' ||
            $returnEmail === '' ||
            $ticketEmail !== $orderEmail ||
            $ticketEmail !== $returnEmail
        ) {
            throw ValidationException::withMessages([
                'ticket_id' => [
                    'Customer email must match the ticket, return request and order.',
                ],
            ]);
        }

        return $ticket;
    }

    /**
     * Find a return belonging to the authenticated owner.
     */
    private function findOwnedReturn(
        Request $request,
        int $id
    ): ReturnRequest {
        return ReturnRequest::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->with([
                'store:id,store_name',
                'order:id,order_number',
            ])
            ->firstOrFail();
    }

    /**
     * Format response for Returns.vue.
     */
    private function formatReturn(
        ReturnRequest $return
    ): array {
        $ticketNumber = null;

        if ($return->ticket_id) {
            $ticketNumber = SupportTicket::query()
                ->where('id', $return->ticket_id)
                ->where('user_id', $return->user_id)
                ->where('store_id', $return->store_id)
                ->where('order_id', $return->order_id)
                ->value('ticket_number');
        }

        return [
            'id' => $return->id,
            'user_id' => $return->user_id,
            'store_id' => $return->store_id,
            'order_id' => $return->order_id,
            'ticket_id' => $return->ticket_id,
            'return_number' => $return->return_number,
            'customer_name' => $return->customer_name,
            'customer_email' => $return->customer_email,
            'store_name' =>
            $return->store?->store_name,
            'order_number' =>
            $return->order?->order_number,
            'ticket_number' => $ticketNumber,
            'request_type' => $return->request_type,
            'reason' => $return->reason,
            'description' => $return->description,
            'status' => $return->status,
            'refund_amount' => $return->refund_amount,
            'currency' => $return->currency,
            'refund_status' => $return->refund_status,
            'admin_notes' => $return->admin_notes,
            'is_demo' => $return->is_demo,
            'created_at' => $return->created_at,
            'updated_at' => $return->updated_at,
        ];
    }
}
