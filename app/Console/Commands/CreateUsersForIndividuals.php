<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Individual;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUsersForIndividuals extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'individuals:create-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create default user accounts for individuals (players and coaches) who do not have one.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $individuals = Individual::whereNull('user_id')->get();

        if ($individuals->isEmpty()) {
            $this->info('No individuals found without a user account.');
            return;
        }

        $count = 0;
        foreach ($individuals as $individual) {
            // Create a unique email based on their name and ID
            // Example: ahmed.player.1@olympic.com
            $firstName = Str::slug($individual->first_name ?: 'user');
            $lastName = Str::slug($individual->last_name ?: $individual->id);
            
            $email = "{$firstName}.{$lastName}.{$individual->id}@olympic.com";
            
            // Default password: password123 (or anything else)
            $defaultPassword = 'password123';

            $user = User::create([
                'name' => trim("{$individual->first_name} {$individual->last_name}"),
                'email' => $email,
                'password' => Hash::make($defaultPassword),
                'user_notify_status' => true,
            ]);

            // Link the individual to this new user
            $individual->user_id = $user->id;
            $individual->save();

            $count++;
            $this->line("Created account for {$individual->type}: {$user->email}");
        }

        $this->info("Successfully created {$count} user accounts!");
        $this->warn("Note: All generated users have the default password: 'password123'");
    }
}
