<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::query()->updateOrCreate([
            'email' => 'test@example.com',
        ], [
            'name' => 'Test User',
            'password' => bcrypt('password'),
        ]);

        $this->call(RolesAndPermissionsSeeder::class);

        // El usuario administrador debe quedar listo aunque falle después
        // algún seeder de datos operativos o del menú lateral.
        $user->syncRoles(['super-admin']);

        $this->call([
            SidebarMenuSeeder::class,
            MesasSeeder::class,
        ]);
    }
}
