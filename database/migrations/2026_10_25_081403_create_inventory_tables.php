<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['parent_id', 'name']);
            $table->unique(['provider_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('price', 14, 2)->unsigned();
            $table->timestamps();
        });

        Schema::create('storages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_id')->constrained()->restrictOnDelete();
            $table->dateTime('purchased_at');
            $table->timestamps();

            $table->index(['purchased_at', 'id']);
        });

        Schema::create('batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 14, 2)->unsigned();
            $table->timestamps();

            $table->unique(['batch_id', 'product_id']);
            $table->index(['product_id', 'batch_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->dateTime('ordered_at')->index();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2)->unsigned();
            $table->timestamps();

            $table->unique(['order_id', 'batch_item_id']);
        });

        Schema::create('purchase_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->dateTime('refunded_at')->index();
            $table->timestamps();

            $table->index(['batch_item_id', 'refunded_at']);
        });

        Schema::create('sale_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->dateTime('refunded_at')->index();
            $table->timestamps();

            $table->index(['order_item_id', 'refunded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_refunds');
        Schema::dropIfExists('purchase_refunds');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('batch_items');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('storages');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('providers');
    }
};
