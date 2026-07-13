<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 30)->nullable()->unique()->after('id');
        });

        // The "processing" status was split into finer-grained stages; map it to "preparing".
        DB::table('orders')->where('status', 'processing')->update(['status' => 'preparing']);

        DB::table('orders')->whereNull('order_number')->orderBy('id')->each(function (object $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'order_number' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(4)),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });

        DB::table('orders')->where('status', 'preparing')->update(['status' => 'processing']);
    }
};
