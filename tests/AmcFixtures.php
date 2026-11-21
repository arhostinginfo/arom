<?php

namespace Tests;

use App\Models\Amc\AmcClient;
use App\Models\Amc\AmcContract;
use App\Models\Amc\AmcEmployee;
use App\Models\Amc\AmcPlant;
use App\Models\Amc\AmcPlanter;
use App\Models\Amc\AmcType;
use App\Models\Amc\AmcVertical;
use App\Models\Unit;
use App\Models\User;
use App\Support\Access;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;

/** AMC records for tests (21/11/2026): masters, a client with lines, employees, a contract, users with or without AMC rights. */
trait AmcFixtures
{
    protected function admin(): User
    {
        return User::where('email', 'admin@nursery.local')->first();
    }

    protected function amcUser(array $perms, string $email = 'amc.user@nursery.local'): User
    {
        $role = Role::findOrCreate('AMC test '.$email, 'web');
        $role->syncPermissions(Access::expand($perms));
        $u = User::create(['name' => 'AMC Tester', 'email' => $email, 'mobile' => '97'.substr(sprintf('%08d', crc32($email)), 0, 8), 'password' => 'Secret2026', 'status' => 'Active']);
        $u->assignRole($role);
        $u->units()->sync([Unit::first()->id]);

        return $u;
    }

    /** Business segments, planters, plants and AMC types of the client's sample data. */
    protected function amcMasters(): array
    {
        return [
            'amc' => AmcVertical::firstOrCreate(['name' => 'AMC']),
            'rental' => AmcVertical::firstOrCreate(['name' => 'AMC + Rental']),
            'ceramic' => AmcPlanter::firstOrCreate(['name' => 'Ceramic round 12 inch']),
            'money' => AmcPlant::firstOrCreate(['name' => 'Money Plant'], ['size' => '2 ft']),
            'rose' => AmcPlant::firstOrCreate(['name' => 'Rose'], ['size' => '5 ft']),
            'type' => AmcType::firstOrCreate(['name' => 'Indoor maintenance']),
            'type2' => AmcType::firstOrCreate(['name' => 'Outdoor maintenance']),
        ];
    }

    protected function amcEmployee(string $name, string $role, array $over = []): AmcEmployee
    {
        $e = new AmcEmployee(array_merge(['code' => 'T'.strtoupper(substr(md5($name), 0, 5)), 'name' => $name, 'role' => $role, 'status' => 'Active'], $over));
        $e->save();

        return $e;
    }

    protected function amcClient(string $name = 'Smart Work', ?array $lines = null): AmcClient
    {
        $m = $this->amcMasters();
        $c = AmcClient::create(['name' => $name, 'amc_vertical_id' => $m['rental']->id]);
        $lines ??= [
            ['location_1' => 'Tower A', 'location_2' => 'Floor 5 - Lobby', 'amc_planter_id' => $m['ceramic']->id, 'planter_qty' => 10, 'amc_plant_id' => $m['money']->id, 'plant_qty' => 10],
            ['location_1' => 'Tower A', 'location_2' => 'Floor 6', 'amc_plant_id' => $m['rose']->id, 'plant_qty' => 20],
        ];
        foreach ($lines as $i => $l) {
            $c->lines()->create($l + ['line_no' => $i + 1]);
        }

        return $c;
    }

    protected function amcContract(AmcClient $client, array $over = []): AmcContract
    {
        $m = $this->amcMasters();
        $c = new AmcContract(array_merge([
            'amc_client_id' => $client->id, 'amc_type_id' => $m['type']->id, 'start_date' => today()->subMonths(6)->toDateString(),
            'end_date' => today()->addMonths(6)->toDateString(), 'billing_cycle' => 'Monthly', 'amount' => 12000, 'status' => 'Active',
        ], $over));
        $c->save();

        return $c;
    }

    /** A small real PNG or JPEG made with GD, for upload tests. */
    protected function amcImage(string $name, int $w = 1200, int $h = 900, string $kind = 'png'): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, random_int(0, 255), 120, 60));
        $path = tempnam(sys_get_temp_dir(), 'amc');
        $kind === 'png' ? imagepng($img, $path) : imagejpeg($img, $path, 80);
        imagedestroy($img);

        return new UploadedFile($path, $name, $kind === 'png' ? 'image/png' : 'image/jpeg', null, true);
    }

    protected function amcPdf(string $name = 'po.pdf'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'amc');
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }
}
