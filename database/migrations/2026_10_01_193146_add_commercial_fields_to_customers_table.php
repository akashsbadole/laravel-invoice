<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Commercial detail that has to be queryable rather than free text.
     *
     * These were previously reachable only through the free-form `attributes`
     * map, which is fine for recording a note but useless for anything that
     * has to be enforced, filtered or reported on:
     *
     *  - credit_limit / credit_days are compared numerically before an invoice
     *    is allowed, so they cannot live in a JSON string;
     *  - gstin_type decides B2B vs B2C on an e-invoice;
     *  - place_of_supply is a 2-digit state code that must agree with
     *    state_code and is emitted as a structured BuyerDtls.Pos field;
     *  - price_tier, preferred_contact_channel, referral_source and tags are
     *    all used to group or route work.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Composition / Unregistered / Consumer change what an e-invoice
            // is allowed to say about the buyer.
            $table->string('gstin_type', 20)->nullable()->after('tax_number');

            // Overrides state_code for a one-off delivery address. Nullable so
            // "same as the customer's home state" stays the common case.
            $table->string('place_of_supply', 2)->nullable()->after('state_code');

            // NULL credit_limit means the business does not extend credit.
            $table->decimal('credit_limit', 14, 2)->nullable()->after('place_of_supply');
            $table->unsignedSmallInteger('credit_days')->nullable()->after('credit_limit');

            $table->string('price_tier', 20)->nullable()->after('credit_days');
            $table->string('preferred_contact_channel', 20)->nullable()->after('price_tier');
            $table->string('referral_source', 100)->nullable()->after('preferred_contact_channel');

            // Free-form tags; exact filtering uses LIKE the way catalog search
            // already does, which is enough at the volumes this app serves.
            $table->json('tags')->nullable()->after('referral_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'gstin_type',
                'place_of_supply',
                'credit_limit',
                'credit_days',
                'price_tier',
                'preferred_contact_channel',
                'referral_source',
                'tags',
            ]);
        });
    }
};
