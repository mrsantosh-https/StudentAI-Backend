<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            if (!Schema::hasColumn('resumes', 'designation')) {
                $table->string('designation')->nullable()->after('full_name');
            }

            if (!Schema::hasColumn('resumes', 'address')) {
                $table->text('address')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('resumes', 'city')) {
                $table->string('city')->nullable()->after('address');
            }

            if (!Schema::hasColumn('resumes', 'state')) {
                $table->string('state')->nullable()->after('city');
            }

            if (!Schema::hasColumn('resumes', 'country')) {
                $table->string('country')->nullable()->after('state');
            }

            if (!Schema::hasColumn('resumes', 'pincode')) {
                $table->string('pincode', 20)->nullable()->after('country');
            }

            if (!Schema::hasColumn('resumes', 'career_objective')) {
                $table->text('career_objective')->nullable()->after('summary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $columns = [
                'designation',
                'address',
                'city',
                'state',
                'country',
                'pincode',
                'career_objective',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('resumes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};