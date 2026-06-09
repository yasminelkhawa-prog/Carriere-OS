<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "UPDATE interviews
             SET location_type = CASE
                WHEN location_type IN ('zoom', 'virtual') THEN 'zoom'
                WHEN location_type IN ('in_person', 'onsite') THEN 'in_person'
                ELSE 'other'
             END"
        );

        DB::statement('ALTER TABLE interviews DROP CONSTRAINT IF EXISTS interviews_location_type_check');
        DB::statement("ALTER TABLE interviews ADD CONSTRAINT interviews_location_type_check CHECK (location_type IN ('zoom','in_person','other'))");

        DB::statement(
            "UPDATE interview_participants
             SET participant_role = CASE
                WHEN participant_role = 'interviewer' THEN 'interviewer'
                ELSE 'observer'
             END"
        );

        DB::statement('ALTER TABLE interview_participants DROP CONSTRAINT IF EXISTS interview_participants_role_check');
        DB::statement("ALTER TABLE interview_participants ADD CONSTRAINT interview_participants_role_check CHECK (participant_role IN ('interviewer','observer'))");

        DB::statement(
            "UPDATE interview_feedback
             SET recommendation = CASE
                WHEN recommendation IN ('strong_yes', 'yes', 'hire') THEN 'hire'
                WHEN recommendation IN ('maybe', 'hold') THEN 'hold'
                ELSE 'no'
             END"
        );

        DB::statement('ALTER TABLE interview_feedback DROP CONSTRAINT IF EXISTS interview_feedback_recommendation_check');
        DB::statement("ALTER TABLE interview_feedback ADD CONSTRAINT interview_feedback_recommendation_check CHECK (recommendation IN ('hire','hold','no'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE interview_feedback DROP CONSTRAINT IF EXISTS interview_feedback_recommendation_check');
        DB::statement('ALTER TABLE interview_participants DROP CONSTRAINT IF EXISTS interview_participants_role_check');
        DB::statement('ALTER TABLE interviews DROP CONSTRAINT IF EXISTS interviews_location_type_check');
    }
};
