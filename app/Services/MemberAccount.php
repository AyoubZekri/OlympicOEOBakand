<?php

namespace App\Services;

use App\Models\Individual;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Every member has a user account (users table, linked by individuals.user_id):
 *   - email: the member's email, or a generated one when the member has none
 *   - password: always random (it can be changed later from the users page)
 *   - no role
 * The account's name and email follow the member when the member is edited. Deleting a member keeps the account.
 */
class MemberAccount
{
    /** Domain of the generated emails, for members without an email */
    public const GENERATED_DOMAIN = 'members.olympic-oeo.local';

    /** Is this email already the login of another account (not this member's own)? */
    public static function emailTaken(?string $email, ?Individual $member = null): bool
    {
        if (!$email) {
            return false;
        }
        return User::where('email', $email)
            ->when($member?->user_id, fn ($q) => $q->where('id', '!=', $member->user_id))
            ->exists();
    }

    /**
     * Create the member's account, or bring its name / email up to date.
     * Returns the account, and the password when the account was just created.
     *
     * @return array{user: User, password: ?string}
     */
    public static function sync(Individual $member): array
    {
        $name = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: 'عضو';
        $email = $member->email ?: self::generatedEmail($member);

        $user = $member->user_id ? User::find($member->user_id) : null;
        if ($user) {
            $user->name = $name;
            // The account keeps its current email if the new one belongs to someone else
            if ($user->email !== $email && !self::emailTaken($email, $member)) {
                $user->email = $email;
            }
            $user->save();
            return ['user' => $user, 'password' => null];
        }

        if (self::emailTaken($email)) {
            $email = self::generatedEmail($member);
        }
        $password = Str::password(12, symbols: false);
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => null,
        ]);

        $member->user_id = $user->id;
        $member->saveQuietly();

        return ['user' => $user, 'password' => $password];
    }

    private static function generatedEmail(Individual $member): string
    {
        return "member{$member->id}@" . self::GENERATED_DOMAIN;
    }
}
