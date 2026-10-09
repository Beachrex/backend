<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Account deletion requested by the user (B-M2; App Store guideline 5.1.1(v), GDPR art. 17).
 *
 * Personal data is removed: profile fields, sign-in methods, API tokens, saved beaches, itineraries, AI chat
 * history and credits. Purchases stay, linked to the anonymised user row, because accounting law requires
 * keeping payment records (GDPR art. 17(3)(b)). So the row is anonymised, not deleted: a hard delete would
 * also cascade-delete those purchases.
 */
class AccountDeletionService
{
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $id = $user->id;

            $user->tokens()->delete();
            DB::table('beach_favorites')->where('user_id', $id)->delete();
            DB::table('itinerary_plans')->where('user_id', $id)->delete();
            DB::table('user_entitlements')->where('user_id', $id)->delete();
            DB::table('ai_sessions')->where('user_id', $id)->delete(); // messages cascade
            DB::table('ai_usage')->where('user_id', $id)->update(['user_id' => null]);
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->forceFill([
                'name' => "Deleted user #{$id}",
                'email' => "deleted-{$id}-".Str::lower(Str::random(8)).'@deleted.beachrex.invalid',
                'password' => bcrypt(Str::random(64)),
                'username' => null,
                'firstname' => null,
                'lastname' => null,
                'date_of_birth' => null,
                'gender_id' => null,
                'mobile' => null,
                'country_code' => null,
                'google_id' => null,
                'avatar_url' => null,
                'remember_token' => null,
                'email_verified_at' => null,
                'is_admin' => false,
                'status_id' => 2,
            ])->save();
        });
    }
}
