<?php
/**
 * @OA\Schema(
 *   schema="BillingSubscription",
 *   type="object",
 *       @OA\Property(property="id", type="string", example="Opnel5aKBz", description="______"),
 *       @OA\Property(property="user_id", type="string", example="Opnel5aKBz", description="______"),
 *       @OA\Property(property="product_id", type="string", example="Opnel5aKBz", description="______"),
 *       @OA\Property(property="company_id", type="string", example="Opnel5aKBz", description="______"),
 *       @OA\Property(property="recurring_invoice_id", type="string", example="Opnel5aKBz", description="______"),
 *       @OA\Property(property="is_recurring", type="boolean", example="true", description="______"),
 *       @OA\Property(property="frequency_id", type="string", example="1", description="integer const representation of the frequency"),
 *       @OA\Property(property="auto_bill", type="string", example="always", description="enum setting"),
 *       @OA\Property(property="promo_code", type="string", example="PROMOCODE4U", description="______"),
 *       @OA\Property(property="promo_discount", type="number", example=10, description="______"),
 *       @OA\Property(property="is_amount_discount", type="boolean", example="true", description="______"),
 *       @OA\Property(property="allow_cancellation", type="boolean", example="true", description="______"),
 *       @OA\Property(property="per_seat_enabled", type="boolean", example="true", description="______"),
 *       @OA\Property(property="min_seats_limit", type="integer", example="1", description="______"),
 *       @OA\Property(property="max_seats_limit", type="integer", example="100", description="______"),
 *       @OA\Property(property="trial_enabled", type="boolean", example="true", description="______"),
 *       @OA\Property(property="trial_duration", type="integer", example="2", description="______"),
 *       @OA\Property(property="allow_query_overrides", type="boolean", example="true", description="______"),
 *       @OA\Property(property="allow_plan_changes", type="boolean", example="true", description="______"),
 *       @OA\Property(property="plan_map", type="string", example="1", description="map describing the available upgrade/downgrade plans for this subscription"),
 *       @OA\Property(property="refund_period", type="integer", example="2", description="______"),
 *       @OA\Property(property="webhook_configuration", type="string", example="2", description="______"),
 *       @OA\Property(property="is_deleted", type="boolean", example="2", description="______"),
 * )
 */
            