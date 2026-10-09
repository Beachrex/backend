<?php

namespace Tests\Feature;

use App\Filament\BrAdmin\Resources\UserResource\Pages\ListUsers;
use App\Models\Status;
use App\Models\User;
use App\Models\UserMigrationStatus;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Owner decision 2026-10-09 (option a): imported users from the old site get a random password,
 * never the old unsalted MD5 hash and never the literal "password". The admin can list them.
 */
class UserMigrationAndAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Status::insert([
            ['id' => 1, 'name' => 'active'],
            ['id' => 2, 'name' => 'inactive'],
        ]);
    }

    private function dump(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'usersql');
        file_put_contents($path, "INSERT INTO `user` (`datetime`, `datetime_ut`, `password`, `firstname`, `lastname`, `username`, `email`, `mobile`, `date_of_birth`, `gender_id`, `country_id`, `status_id`, `register_type`) VALUES\n"
            ."('2015-05-01 10:00:00', NULL, '5f4dcc3b5aa765d61d8327deb882cf99', 'Ana', 'Novak', 'ana', 'ana@example.test', NULL, NULL, 9, 'xx', 1, 'web'),\n"
            ."('2016-06-01 10:00:00', NULL, NULL, NULL, NULL, 'bor', 'bor@example.test', NULL, NULL, NULL, NULL, 1, NULL);\n");

        return $path;
    }

    public function test_imported_users_get_an_unusable_random_password(): void
    {
        $path = $this->dump();
        $this->artisan('users:migrate-from-old-db', ['--file' => $path])->assertExitCode(0);
        unlink($path);

        $ana = User::where('email', 'ana@example.test')->firstOrFail();
        $bor = User::where('email', 'bor@example.test')->firstOrFail();

        $this->assertSame('Ana Novak', $ana->name);
        $this->assertNull($ana->country_code); // unknown codes are dropped, not the account
        $this->assertNull($ana->gender_id);
        $this->assertFalse($ana->is_admin);
        foreach ([$ana, $bor] as $user) {
            $this->assertNotSame('5f4dcc3b5aa765d61d8327deb882cf99', $user->password);
            $this->assertTrue(Hash::isHashed($user->password));
            $this->assertFalse(Hash::check('password', $user->password));
            $this->assertFalse(Hash::check('5f4dcc3b5aa765d61d8327deb882cf99', $user->password));
        }
        $this->assertNotSame($ana->password, $bor->password);
        $this->assertSame(2, UserMigrationStatus::where('status', 'success')->count());
    }

    public function test_running_twice_skips_existing_users(): void
    {
        $path = $this->dump();
        $this->artisan('users:migrate-from-old-db', ['--file' => $path])->assertExitCode(0);
        $this->artisan('users:migrate-from-old-db', ['--file' => $path])->assertExitCode(0);
        unlink($path);

        $this->assertSame(1, User::where('email', 'ana@example.test')->count());
    }

    public function test_admin_sees_the_users_list(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $listed = User::factory()->create(['email' => 'listed@example.test']);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListUsers::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$admin, $listed]);

        UserMigrationStatus::create(['old_user_id' => 7, 'new_user_id' => $listed->id, 'status' => 'success']);

        Livewire::test(ListUsers::class)
            ->filterTable('imported', true)
            ->assertCanSeeTableRecords([$listed])
            ->assertCanNotSeeTableRecords([$admin])
            ->filterTable('imported', false)
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$listed]);
    }

    public function test_non_admin_cannot_open_the_users_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get(route('filament.admin.resources.users.index'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('filament.admin.resources.users.index'))->assertRedirect();
    }
}
