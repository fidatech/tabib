<?php

namespace Tests\Feature;

use App\Models\Central\DrugListEntry;
use App\Models\Medicine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The platform list of medicines: loaded once, kept in the central panel, sent to every clinic, not changed by them. */
class DrugListTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_is_kept_centrally_and_every_change_reaches_the_clinics(): void
    {
        $this->artisan('medicines:import')->assertSuccessful();
        $this->assertGreaterThan(700, DrugListEntry::count());
        $this->artisan('medicines:import')->expectsOutputToContain('0 medicines added')->assertSuccessful();

        $tenant = $this->clinic();
        $this->owner();
        $this->assertSame(DrugListEntry::count(), Medicine::count());

        // the clinic writes its limit; it cannot change the brands of a listed medicine
        $para = Medicine::where('code', 'paracetamol-500-mg-tablet')->sole();
        $this->put('/medicines/'.$para->id, ['brands' => 'Fake brand', 'max_daily_mg' => '4000', 'is_active' => '1']);
        $this->assertSame([4000.0, false], [(float) $para->fresh()->max_daily_mg, str_contains((string) $para->fresh()->brands, 'Fake brand')]);

        // the panel adds a medicine, changes one and takes one off: the clinic gets it at once
        $this->central();
        $this->systemAdmin();
        $this->get('/admin/medicines?q=Panadol')->assertOk()->assertSee('Paracetamol');
        $this->post('/admin/medicines', ['name' => 'Joshanda', 'form' => 'sachet', 'class' => 'herbal', 'brands' => 'PK: Qarshi Joshanda | AF: Joshanda'])->assertSessionHas('success');
        $this->from('/admin/medicines')->post('/admin/medicines', ['name' => 'Bad', 'form' => 'tablet', 'brands' => 'Panadol'])->assertSessionHasErrors('brands');
        $entry = DrugListEntry::where('code', 'paracetamol-500-mg-tablet')->sole();
        $this->put('/admin/medicines/'.$entry->id, ['name' => 'Paracetamol', 'strength' => '500 mg', 'form' => 'tablet', 'class' => 'analgesic', 'mg_per_unit' => '500',
            'brands' => $entry->brands.' | AF: Afghan Para'])->assertSessionHas('success');
        $ibu = DrugListEntry::where('code', 'ibuprofen-400-mg-tablet')->sole();
        $this->post('/admin/medicines/'.$ibu->id.'/status')->assertSessionHas('success');

        $this->enter($tenant);
        $this->owner();
        $this->assertTrue(Medicine::where('name', 'Joshanda')->exists());
        $para->refresh();
        $this->assertStringContainsString('Afghan Para', $para->brands);
        $this->assertSame(4000.0, (float) $para->max_daily_mg);   // the limit of the clinic stayed
        $this->assertTrue((bool) Medicine::where('code', 'ibuprofen-400-mg-tablet')->sole()->withdrawn);
        $this->getJson('/medicines/search?q=ibuprofen 400')->assertOk()->assertJsonMissing(['strength' => '400 mg', 'name' => 'Ibuprofen']);
        $this->getJson('/medicines/search?q=Afghan Para')->assertJsonFragment(['name' => 'Paracetamol']);
    }
}
