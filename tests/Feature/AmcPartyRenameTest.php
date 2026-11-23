<?php

namespace Tests\Feature;

use App\Models\Amc\AmcClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\AmcFixtures;
use Tests\TestCase;

/** (23/11/2026) A party renamed in the Party master renames its AMC client; the Party save is never stopped. */
class AmcPartyRenameTest extends TestCase
{
    use AmcFixtures;
    use RefreshDatabase;

    protected $seed = true;

    public function test_renaming_a_party_renames_its_amc_client(): void
    {
        $c = $this->amcClient('Smart Work');
        $other = $this->amcClient('Rich Next');

        $c->party->update(['name' => 'Smart Work Pvt Ltd']);
        $this->assertSame('Smart Work Pvt Ltd', $c->fresh()->name);
        $this->assertSame('Rich Next', $other->fresh()->name);

        // other changes to the party leave the name alone
        $c->party->update(['status' => 'Active']);
        $this->assertSame('Smart Work Pvt Ltd', $c->fresh()->name);
    }

    public function test_a_name_another_amc_client_has_is_left_and_the_party_still_saves(): void
    {
        $c = $this->amcClient('Smart Work');
        AmcClient::create(['name' => 'Old Unlinked Co', 'amc_vertical_id' => $c->amc_vertical_id]);

        $c->party->update(['name' => 'Old Unlinked Co']);
        $this->assertSame('Old Unlinked Co', $c->party->fresh()->name);
        $this->assertSame('Smart Work', $c->fresh()->name);
    }
}
