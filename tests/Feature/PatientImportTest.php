<?php

namespace Tests\Feature;

use App\Models\History\Allergy;
use App\Models\Patient;
use App\Services\PatientImport;
use App\Support\SheetReader;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

/** Patients brought in from a file: the columns matched, every row checked, those already here kept apart. */
class PatientImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $text): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        file_put_contents($path, $text);

        return new UploadedFile($path, 'register.csv', 'text/csv', null, true);
    }

    public function test_a_register_in_dari_is_matched_checked_and_registered(): void
    {
        $this->clinic();
        $this->owner();
        $this->post('/patients', ['name' => 'Gul Ahmad', 'father_name' => 'Karim', 'gender' => 'male', 'age' => '40', 'phone' => '0700123456']);

        $file = "\xEF\xBB\xBFنام,نام پدر,جنس,سن,تاریخ تولد,شماره تماس,حساسیت,شماره دوسیه\n"
            ."مریم,احمد,زن,۲۵,,۰۷۹۹۱۱۱۲۲۲,پنسلین,A-17\n"
            ."Zahir,Nabi,male,,1369/02/30,799333444,,A-18\n"         // Shamsi date; Excel dropped the 0 of the phone
            ."Ali,Hassan,M,30,,700123456,,A-19\n"                  // the phone of Gul Ahmad: a duplicate
            .",Nobody,F,20,,,,A-20\n"                              // no name
            ."Sara,Wali,F,250,,,,A-21\n"                           // impossible age
            ."مریم,احمد,زن,25,,0799111222,,A-22\n";                // the same as line 2
        $this->post('/patients/import', ['file' => $this->csv($file)])->assertRedirect('/patients/import/columns');
        $this->get('/patients/import/columns')->assertOk()->assertSee('6 rows')->assertSee('Number in the old register');

        // the columns were guessed from the headings
        $state = session('patient_import');
        $this->assertSame(['name', 'father_name', 'gender', 'age', 'date_of_birth', 'phone', 'allergies', 'old_number'], array_values($state['map']));
        $this->post('/patients/import/columns', ['map' => $state['map']])->assertRedirect('/patients/import/check');
        $this->get('/patients/import/check')->assertOk()->assertSee('Same phone as P-000001')->assertSee('No name')->assertSee('The age 250 is not possible')->assertSee('Same as line 2');

        $this->post('/patients/import/run')->assertSessionHas('success');
        $this->assertSame(3, Patient::count());   // Gul Ahmad, Maryam, Zahir
        $maryam = Patient::where('name', 'مریم')->sole();
        $this->assertSame(['female', '0799111222', true], [$maryam->gender, $maryam->phone, $maryam->dob_estimated]);
        $this->assertStringContainsString('A-17', $maryam->notes);
        $this->assertSame('پنسلین', Allergy::where('patient_id', $maryam->id)->sole()->substance);
        $zahir = Patient::where('name', 'Zahir')->sole();
        $this->assertSame(['1990-05-20', '0799333444'], [$zahir->date_of_birth->toDateString(), $zahir->phone]);
        $this->assertNull(session('patient_import'));
    }

    public function test_an_excel_file_is_read_with_its_dates_and_duplicates_can_be_registered_on_purpose(): void
    {
        $this->clinic();
        $this->owner();
        $this->post('/patients', ['name' => 'Gul Ahmad', 'gender' => 'male', 'age' => '40', 'tazkira' => '1400-1-1']);

        // a file as Excel writes it: shared strings, and a date kept as a day number (33000 = 1990-05-07)
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Patients" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Patient name</t></si><si><t>Sex</t></si><si><t>DOB</t></si><si><t>Tazkira</t></si><si><t>Nadia</t></si><si><t>female</t></si><si><r><t>Gul </t></r><r><t>Ahmad</t></r></si><si><t>male</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>4</v></c><c r="B2" t="s"><v>5</v></c><c r="C2"><v>33000</v></c></row>'
            .'<row r="3"><c r="A3" t="s"><v>6</v></c><c r="B3" t="s"><v>7</v></c><c r="D3" t="inlineStr"><is><t>1400-1-1</t></is></c></row>'
            .'</sheetData></worksheet>');
        $zip->close();
        $rows = SheetReader::read($path, 'x.xlsx');
        $this->assertSame([['Patient name', 'Sex', 'DOB', 'Tazkira'], ['Nadia', 'female', '33000'], ['Gul Ahmad', 'male', '', '1400-1-1']], $rows);

        $this->post('/patients/import', ['file' => new UploadedFile($path, 'patients.xlsx', null, null, true)])->assertRedirect();
        $this->post('/patients/import/columns', ['map' => session('patient_import')['map']]);
        $this->get('/patients/import/check')->assertSee('Same Tazkira as P-000001');
        $this->post('/patients/import/run', ['with_duplicates' => '1']);
        $this->assertSame(3, Patient::count());
        $this->assertSame('1990-05-07', Patient::where('name', 'Nadia')->sole()->date_of_birth->toDateString());

        // a file written by Tabib itself is read back
        $own = tempnam(sys_get_temp_dir(), 'own').'.xlsx';
        file_put_contents($own, Xlsx::build([['cells' => ['Name', 'Sex'], 'bold' => true], ['cells' => ['Karim', 'm']]]));
        $this->assertSame([['Name', 'Sex'], ['Karim', 'm']], SheetReader::read($own, 'own.xlsx'));
    }

    public function test_the_name_and_the_sex_must_be_matched_and_others_cannot_import(): void
    {
        $this->clinic();
        $this->owner();
        $this->post('/patients/import', ['file' => $this->csv("Name;Phone\nGul;0700\n")]);
        $this->from('/patients/import/columns')->post('/patients/import/columns', ['map' => ['0' => 'name']])->assertSessionHas('danger');
        $this->from('/patients/import/columns')->post('/patients/import/columns', ['map' => ['0' => 'name', '1' => 'name']])->assertSessionHas('danger');
        $this->assertSame([0 => 'name', 1 => 'phone'], PatientImport::guess(['Name', 'Phone']));

        $this->actingAs($this->userWith(['patients.view']));
        $this->get('/patients/import')->assertForbidden();
    }
}
