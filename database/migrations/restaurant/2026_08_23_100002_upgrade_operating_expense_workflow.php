<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operating_expenses')) {
            return;
        }

        Schema::table('operating_expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('operating_expenses', 'status')) {
                // Preserve the legacy API contract for direct imports/seeders.
                // The application workflow explicitly creates new requests as
                // draft and requires owner approval before payment.
                $table->string('status', 20)->default('approved')->change();
            }
            if (! Schema::hasColumn('operating_expenses', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('operating_expenses', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('operating_expenses', 'rejected_at')) {
                $table->dateTime('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('operating_expenses', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('operating_expenses', 'paid_by')) {
                $table->foreignId('paid_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('operating_expenses', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('paid_by');
            }
            if (! Schema::hasColumn('operating_expenses', 'payment_method')) {
                $table->string('payment_method', 40)->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('operating_expenses', 'payment_reference')) {
                $table->string('payment_reference', 150)->nullable()->after('payment_method');
            }
            if (! Schema::hasColumn('operating_expenses', 'vendor_name')) {
                $table->string('vendor_name', 255)->nullable()->after('description');
            }
            if (! Schema::hasColumn('operating_expenses', 'invoice_number')) {
                $table->string('invoice_number', 100)->nullable()->after('vendor_name');
            }
            if (! Schema::hasColumn('operating_expenses', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('invoice_number');
            }
            if (! Schema::hasColumn('operating_expenses', 'due_date')) {
                $table->date('due_date')->nullable()->after('invoice_date');
            }
            if (! Schema::hasColumn('operating_expenses', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('operating_expenses', 'cost_center')) {
                $table->string('cost_center', 100)->nullable()->after('due_date');
            }
        });

        Schema::table('operating_expenses', function (Blueprint $table): void {
            $table->index(['restaurant_id', 'status', 'expense_date'], 'operating_expenses_workflow_index');
            $table->index(['restaurant_id', 'vendor_name', 'invoice_number'], 'operating_expenses_vendor_invoice_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('operating_expenses')) {
            return;
        }

        Schema::table('operating_expenses', function (Blueprint $table): void {
            $table->dropIndex('operating_expenses_workflow_index');
            $table->dropIndex('operating_expenses_vendor_invoice_index');
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['paid_by']);
            $table->dropColumn([
                'approved_at', 'rejected_by', 'rejected_at', 'rejection_reason',
                'paid_by', 'paid_at', 'payment_method', 'payment_reference',
                'vendor_name', 'invoice_number', 'invoice_date', 'due_date',
                'tax_amount', 'cost_center',
            ]);
        });
    }
};
