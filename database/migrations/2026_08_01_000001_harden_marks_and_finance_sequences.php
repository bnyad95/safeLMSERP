<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('marks')
            ->whereNotNull('course_section_id')
            ->select('course_section_id', 'student_id', DB::raw('COUNT(*) as duplicate_count'))
            ->groupBy('course_section_id', 'student_id')
            ->having('duplicate_count', '>', 1)
            ->orderBy('course_section_id')
            ->get()
            ->each(function ($duplicate): void {
                $duplicateIds = DB::table('marks')
                    ->where('course_section_id', $duplicate->course_section_id)
                    ->where('student_id', $duplicate->student_id)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->pluck('id')
                    ->slice(1);

                if ($duplicateIds->isNotEmpty()) {
                    DB::table('marks')->whereIn('id', $duplicateIds)->delete();
                }
            });

        Schema::table('marks', function (Blueprint $table) {
            $table->unique(['course_section_id', 'student_id'], 'marks_section_student_unique');
        });
        Schema::table('marks', function (Blueprint $table) {
            $table->dropIndex(['course_section_id', 'student_id']);
        });

        Schema::create('finance_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 3);
            $table->unsignedSmallInteger('document_year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['document_type', 'document_year']);
        });

        $sequences = [];
        DB::table('finance_transactions')
            ->select(['invoice_number', 'receipt_number'])
            ->orderBy('id')
            ->each(function ($transaction) use (&$sequences): void {
                foreach (['invoice_number' => 'INV', 'receipt_number' => 'RCT'] as $column => $type) {
                    $number = (string) ($transaction->{$column} ?? '');
                    if (! preg_match('/^'.$type.'-(\d{4})-(\d+)$/', $number, $matches)) {
                        continue;
                    }

                    $key = $type.'-'.$matches[1];
                    $sequences[$key] = max($sequences[$key] ?? 1, ((int) $matches[2]) + 1);
                }
            });

        foreach ($sequences as $key => $nextNumber) {
            [$type, $year] = explode('-', $key, 2);
            DB::table('finance_document_sequences')->insert([
                'document_type' => $type,
                'document_year' => (int) $year,
                'next_number' => $nextNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_document_sequences');

        Schema::table('marks', function (Blueprint $table) {
            $table->index(['course_section_id', 'student_id']);
        });
        Schema::table('marks', function (Blueprint $table) {
            $table->dropUnique('marks_section_student_unique');
        });
    }
};
