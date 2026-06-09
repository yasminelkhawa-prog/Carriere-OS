<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('companies')) {
            return;
        }

        $companies = DB::table('companies')
            ->select('id', 'name', 'slug')
            ->get();

        foreach ($companies as $company) {
            $name = (string) ($company->name ?? '');
            $slug = (string) ($company->slug ?? '');

            $newName = preg_replace('/carri(?:e|\\x{00E8})re\\s*os/iu', 'numa', $name);
            $newName = is_string($newName) ? $newName : $name;

            $newSlug = $slug !== ''
                ? preg_replace('/carriere\\s*os/i', 'numa', $slug)
                : $slug;
            $newSlug = is_string($newSlug) ? $newSlug : $slug;

            $updates = [];

            if ($newName !== $name) {
                $updates['name'] = $newName;
            }

            if ($newSlug !== $slug && $newSlug !== '') {
                $slugTaken = DB::table('companies')
                    ->where('slug', $newSlug)
                    ->where('id', '!=', $company->id)
                    ->exists();

                if (! $slugTaken) {
                    $updates['slug'] = $newSlug;
                }
            }

            if ($updates !== []) {
                if (Schema::hasColumn('companies', 'updated_at')) {
                    $updates['updated_at'] = now();
                }

                DB::table('companies')
                    ->where('id', $company->id)
                    ->update($updates);
            }
        }
    }

    public function down(): void
    {
        // No rollback for data rebranding migration.
    }
};