<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CptCode;
use App\Models\EmrSystem;
use App\Models\FeeSchedule;
use App\Models\HcpcsCode;
use App\Models\Icd10Code;
use App\Models\Locality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the reference-data tables: CPT / ICD-10 / HCPCS code sets, the 5 WC
 * state fee schedules with their localities, and the 9 EMR systems.
 *
 * All inserts use updateOrCreate on a natural key so running the seeder twice
 * does not create duplicates. Wrapped in a single DB transaction — if any
 * step fails, nothing persists.
 *
 * Reference-data effective dates: CPT and ICD-10 both stamped 2024-01-01 as
 * a stable starting release. Step 5's Deployer upload pipeline will add new
 * releases with fresh effectiveFrom dates (no destructive updates).
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedFeeSchedules();
            $this->seedEmrSystems();
            $this->seedCptCodes();
            $this->seedIcd10Codes();
            $this->seedHcpcsCodes();
        });

        $this->command?->info(sprintf(
            '  Reference: %d fee schedules, %d localities, %d EMRs, %d CPT, %d ICD-10, %d HCPCS',
            FeeSchedule::count(),
            Locality::count(),
            EmrSystem::count(),
            CptCode::count(),
            Icd10Code::count(),
            HcpcsCode::count(),
        ));
    }

    // ----- fee schedules + localities (JSX FEE_STATES line 86) --------------

    private function seedFeeSchedules(): void
    {
        $states = [
            ['code' => 'CA', 'name' => 'California', 'sys' => 'OMFS', 'm' => 1.0000, 'localities' => [
                ['code' => '18', 'name' => 'Los Angeles', 'adj' => 1.014],
                ['code' => '17', 'name' => 'San Francisco/Marin', 'adj' => 1.068],
                ['code' => '26', 'name' => 'Orange County', 'adj' => 1.008],
                ['code' => '09', 'name' => 'San Diego', 'adj' => 0.992],
                ['code' => '03', 'name' => 'Sacramento', 'adj' => 0.970],
                ['code' => '07', 'name' => 'San Bernardino/Riverside', 'adj' => 0.962],
                ['code' => '99', 'name' => 'Rest of California', 'adj' => 0.946],
            ]],
            ['code' => 'TX', 'name' => 'Texas', 'sys' => 'TDI', 'm' => 0.9200, 'localities' => [
                ['code' => '20', 'name' => 'Dallas', 'adj' => 1.009],
                ['code' => '18', 'name' => 'Houston', 'adj' => 1.014],
                ['code' => '31', 'name' => 'Austin', 'adj' => 0.985],
                ['code' => '09', 'name' => 'San Antonio', 'adj' => 0.963],
                ['code' => '99', 'name' => 'Rest of Texas', 'adj' => 0.941],
            ]],
            ['code' => 'NY', 'name' => 'New York', 'sys' => 'WCB', 'm' => 1.0800, 'localities' => [
                ['code' => '01', 'name' => 'Manhattan', 'adj' => 1.094],
                ['code' => '02', 'name' => 'NYC Suburbs/Long Island', 'adj' => 1.068],
                ['code' => '03', 'name' => 'Queens', 'adj' => 1.058],
                ['code' => '04', 'name' => 'Poughkeepsie/N. NYC', 'adj' => 1.011],
                ['code' => '99', 'name' => 'Rest of New York', 'adj' => 0.961],
            ]],
            ['code' => 'FL', 'name' => 'Florida', 'sys' => 'DWC', 'm' => 0.8800, 'localities' => [
                ['code' => '04', 'name' => 'Fort Lauderdale', 'adj' => 1.005],
                ['code' => '03', 'name' => 'Miami', 'adj' => 1.002],
                ['code' => '09', 'name' => 'Orlando', 'adj' => 0.976],
                ['code' => '17', 'name' => 'Tampa/St. Petersburg', 'adj' => 0.973],
                ['code' => '99', 'name' => 'Rest of Florida', 'adj' => 0.952],
            ]],
            ['code' => 'IL', 'name' => 'Illinois', 'sys' => 'WCC', 'm' => 0.9500, 'localities' => [
                ['code' => '16', 'name' => 'Chicago', 'adj' => 1.024],
                ['code' => '12', 'name' => 'East St. Louis', 'adj' => 0.985],
                ['code' => '15', 'name' => 'Suburban Chicago', 'adj' => 1.004],
                ['code' => '99', 'name' => 'Rest of Illinois', 'adj' => 0.951],
            ]],
        ];

        foreach ($states as $state) {
            $localities = $state['localities'];
            unset($state['localities']);

            FeeSchedule::updateOrCreate(
                ['code' => $state['code']],
                $state + ['active' => true],
            );

            foreach ($localities as $loc) {
                Locality::updateOrCreate(
                    ['feeScheduleCode' => $state['code'], 'code' => $loc['code']],
                    [
                        'name' => $loc['name'],
                        'adj' => $loc['adj'],
                        'active' => true,
                    ],
                );
            }
        }
    }

    // ----- EMR systems (JSX EMR_SYSTEMS line 93) ----------------------------

    private function seedEmrSystems(): void
    {
        // JSX `status` and `type` are display strings. Here we also split out
        // the enum-typed backend value from the display label.
        $systems = [
            ['name' => 'Epic',          'type' => 'fhir', 'typeLabel' => 'API (FHIR R4)', 'icon' => '🏥', 'fields' => ['Demographics', 'Encounters', 'Problems', 'Meds', 'Labs', 'Documents']],
            ['name' => 'athenahealth',  'type' => 'rest', 'typeLabel' => 'API (REST)',    'icon' => '☁️', 'fields' => ['Patient', 'Appointments', 'Encounters', 'Documents']],
            ['name' => 'eClinicalWorks','type' => 'sftp', 'typeLabel' => 'SFTP',          'icon' => '📁', 'fields' => ['Demographics', 'Encounters', 'Documents']],
            ['name' => 'NextGen',       'type' => 'api',  'typeLabel' => 'API',           'icon' => '🔗', 'fields' => ['Patient', 'Encounters', 'Documents']],
            ['name' => 'DrChrono',      'type' => 'rest', 'typeLabel' => 'API (REST)',    'icon' => '📋', 'fields' => ['Patient', 'Encounters']],
            ['name' => 'AdvancedMD',    'type' => 'sftp', 'typeLabel' => 'SFTP',          'icon' => '📂', 'fields' => ['Demographics', 'Claims']],
            ['name' => 'Kareo',         'type' => 'api',  'typeLabel' => 'API',           'icon' => '💊', 'fields' => ['Patient', 'Billing']],
            ['name' => 'Custom API',    'type' => 'api',  'typeLabel' => 'Custom',        'icon' => '⚙️', 'fields' => ['Configurable']],
            ['name' => 'SFTP Transfer', 'type' => 'sftp', 'typeLabel' => 'SFTP',          'icon' => '📤', 'fields' => ['File-based']],
        ];

        foreach ($systems as $sys) {
            EmrSystem::updateOrCreate(
                ['name' => $sys['name']],
                $sys + ['active' => true],
            );
        }
    }

    // ----- CPT codes (~100: JSX CPT_LOOKUP + common WC codes) ---------------

    private function seedCptCodes(): void
    {
        $effective = '2024-01-01';

        // From JSX CPT_LOOKUP line 49 — preserved verbatim
        $fromPrototype = [
            '99201' => 'Office Visit, New, Lvl 1 (minimal)',
            '99202' => 'Office Visit, New, Lvl 2 (low)',
            '99203' => 'Office Visit, New, Lvl 3 (mod)',
            '99204' => 'Office Visit, New, Lvl 4 (mod-high)',
            '99205' => 'Office Visit, New, Lvl 5 (high)',
            '99211' => 'Office Visit, Est, Lvl 1 (minimal)',
            '99212' => 'Office Visit, Est, Lvl 2 (low)',
            '99213' => 'Office Visit, Est, Lvl 3 (low-mod)',
            '99214' => 'Office Visit, Est, Lvl 4 (mod)',
            '99215' => 'Office Visit, Est, Lvl 5 (high)',
            '99354' => 'Prolonged Svc, Office, 1st hour',
            '99355' => 'Prolonged Svc, Office, addl 30 min',
            '99358' => 'Prolonged Non-Face-to-Face, 1st hour',
            '99455' => 'WC Disability Exam, Treating',
            '99456' => 'WC Disability Exam, Other',
            '99080' => 'Special Reports / Forms',
            '99499' => 'Unlisted E/M Service',
            '97110' => 'Therapeutic Exercises',
            '97112' => 'Neuromuscular Reeducation',
            '97140' => 'Manual Therapy',
            '97530' => 'Therapeutic Activities',
            '97161' => 'PT Evaluation, Low Complex',
            '97162' => 'PT Evaluation, Mod Complex',
            '97163' => 'PT Evaluation, High Complex',
            '97164' => 'PT Re-evaluation',
            '20550' => 'Injection, Tendon Sheath',
            '20551' => 'Injection, Tendon Origin',
            '20552' => 'Injection, Trigger Pt, 1-2 muscles',
            '20553' => 'Injection, Trigger Pt, 3+ muscles',
            '20600' => 'Arthrocentesis, Small Joint',
            '20605' => 'Arthrocentesis, Mid Joint',
            '20610' => 'Arthrocentesis, Major Joint',
            '64483' => 'Injection, Transforaminal Epidural',
            '64484' => 'Injection, Transforaminal, Addl Lvl',
            '72100' => 'X-Ray, Lumbar Spine, 2-3 views',
            '72110' => 'X-Ray, Lumbar Spine, Min 4 views',
            '72148' => 'MRI, Lumbar Spine, w/o contrast',
            '73030' => 'X-Ray, Shoulder, Min 2 views',
            '73221' => 'MRI, Upper Extremity Joint',
            '73721' => 'MRI, Lower Extremity Joint',
            '95831' => 'Muscle Test, Manual, Extremity',
            '95851' => 'Range of Motion, Extremity',
            '95857' => 'Tensilon Test',
            '99050' => 'After-Hours Office Visit',
            '99051' => 'Weekend/Evening Office',
            '99070' => 'Supplies and Materials',
        ];

        // Common WC codes the prototype doesn't list — required by launcher spec
        // (covers the E/M, imaging, injection, EMG, PT codes backend needs in scope).
        $additional = [
            // Observation
            '99217' => ['desc' => 'Observation Care Discharge',                'cat' => 'E/M'],
            '99218' => ['desc' => 'Initial Observation Care, Low',             'cat' => 'E/M'],
            '99219' => ['desc' => 'Initial Observation Care, Moderate',        'cat' => 'E/M'],
            '99220' => ['desc' => 'Initial Observation Care, High',            'cat' => 'E/M'],

            // Hospital
            '99221' => ['desc' => 'Initial Hospital Care, Low',                'cat' => 'E/M'],
            '99222' => ['desc' => 'Initial Hospital Care, Moderate',           'cat' => 'E/M'],
            '99223' => ['desc' => 'Initial Hospital Care, High',               'cat' => 'E/M'],
            '99231' => ['desc' => 'Subsequent Hospital Care, Low',             'cat' => 'E/M'],
            '99232' => ['desc' => 'Subsequent Hospital Care, Moderate',        'cat' => 'E/M'],
            '99233' => ['desc' => 'Subsequent Hospital Care, High',            'cat' => 'E/M'],

            // ED
            '99281' => ['desc' => 'ED Visit, Problem-Focused',                 'cat' => 'E/M'],
            '99282' => ['desc' => 'ED Visit, Low',                             'cat' => 'E/M'],
            '99283' => ['desc' => 'ED Visit, Moderate',                        'cat' => 'E/M'],
            '99284' => ['desc' => 'ED Visit, High w/ Threat',                  'cat' => 'E/M'],
            '99285' => ['desc' => 'ED Visit, High Severity',                   'cat' => 'E/M'],

            // Imaging — cervical MRI (launcher listed 72141-72148)
            '72141' => ['desc' => 'MRI, Cervical Spine, w/o contrast',         'cat' => 'Imaging'],
            '72142' => ['desc' => 'MRI, Cervical Spine, w/ contrast',          'cat' => 'Imaging'],
            '72146' => ['desc' => 'MRI, Thoracic Spine, w/o contrast',         'cat' => 'Imaging'],
            '72147' => ['desc' => 'MRI, Thoracic Spine, w/ contrast',          'cat' => 'Imaging'],

            // MRI lower extremity series (launcher listed 73721-73723)
            '73722' => ['desc' => 'MRI, Lower Extremity Joint, w/ contrast',   'cat' => 'Imaging'],
            '73723' => ['desc' => 'MRI, Lower Extremity Joint, w/o+w/ contrast','cat' => 'Imaging'],
            '73718' => ['desc' => 'MRI, Lower Extremity (non-joint), w/o',     'cat' => 'Imaging'],
            '73719' => ['desc' => 'MRI, Lower Extremity (non-joint), w/',      'cat' => 'Imaging'],
            '73720' => ['desc' => 'MRI, Lower Extremity (non-joint), w/o+w/',  'cat' => 'Imaging'],

            // Shoulder / upper extremity series
            '73000' => ['desc' => 'X-Ray, Clavicle',                           'cat' => 'Imaging'],
            '73010' => ['desc' => 'X-Ray, Scapula',                            'cat' => 'Imaging'],
            '73020' => ['desc' => 'X-Ray, Shoulder, 1 view',                   'cat' => 'Imaging'],
            '73040' => ['desc' => 'X-Ray, Shoulder Arthrogram',                'cat' => 'Imaging'],
            '73222' => ['desc' => 'MRI, Upper Extremity Joint, w/ contrast',   'cat' => 'Imaging'],
            '73223' => ['desc' => 'MRI, Upper Extremity Joint, w/o+w/',        'cat' => 'Imaging'],

            // Epidural injections by level
            '62321' => ['desc' => 'Injection, Interlaminar Cervical/Thoracic', 'cat' => 'Injection'],
            '62322' => ['desc' => 'Injection, Interlaminar Lumbar/Sacral',     'cat' => 'Injection'],
            '62323' => ['desc' => 'Injection, Interlaminar Lumbar/Sacral, w/ imaging guidance','cat' => 'Injection'],
            '64479' => ['desc' => 'Injection, Transforaminal Cervical, 1st Lvl','cat' => 'Injection'],
            '64480' => ['desc' => 'Injection, Transforaminal Cervical, Addl',  'cat' => 'Injection'],

            // Arthroscopy
            '29826' => ['desc' => 'Arthroscopy, Shoulder, w/ Acromioplasty',   'cat' => 'Surgery'],
            '29827' => ['desc' => 'Arthroscopy, Shoulder, w/ Rotator Cuff Repair','cat' => 'Surgery'],
            '29881' => ['desc' => 'Arthroscopy, Knee, Meniscectomy',           'cat' => 'Surgery'],
            '29888' => ['desc' => 'Arthroscopy, Knee, ACL Reconstruction',     'cat' => 'Surgery'],

            // Nerve conduction (launcher listed 95905-95911)
            '95905' => ['desc' => 'Nerve Conduction Study, 1-2 Studies',       'cat' => 'Diagnostic'],
            '95907' => ['desc' => 'Nerve Conduction Studies, 1-2 Studies',     'cat' => 'Diagnostic'],
            '95908' => ['desc' => 'Nerve Conduction Studies, 3-4 Studies',     'cat' => 'Diagnostic'],
            '95909' => ['desc' => 'Nerve Conduction Studies, 5-6 Studies',     'cat' => 'Diagnostic'],
            '95910' => ['desc' => 'Nerve Conduction Studies, 7-8 Studies',     'cat' => 'Diagnostic'],
            '95911' => ['desc' => 'Nerve Conduction Studies, 9-10 Studies',    'cat' => 'Diagnostic'],
            '95912' => ['desc' => 'Nerve Conduction Studies, 11-12 Studies',   'cat' => 'Diagnostic'],
            '95913' => ['desc' => 'Nerve Conduction Studies, 13+ Studies',     'cat' => 'Diagnostic'],
            '95885' => ['desc' => 'EMG, Needle, Limited Extremity',            'cat' => 'Diagnostic'],
            '95886' => ['desc' => 'EMG, Needle, Complete Extremity',           'cat' => 'Diagnostic'],
            '95887' => ['desc' => 'EMG, Needle, Cranial Nerve',                'cat' => 'Diagnostic'],

            // Additional PT / manipulation
            '97010' => ['desc' => 'Application of Hot/Cold Packs',             'cat' => 'PT'],
            '97012' => ['desc' => 'Mechanical Traction',                       'cat' => 'PT'],
            '97014' => ['desc' => 'Electrical Stimulation (unattended)',       'cat' => 'PT'],
            '97032' => ['desc' => 'Electrical Stimulation (attended)',         'cat' => 'PT'],
            '97035' => ['desc' => 'Ultrasound Therapy',                        'cat' => 'PT'],
            '97124' => ['desc' => 'Massage Therapy',                           'cat' => 'PT'],
            '98940' => ['desc' => 'Chiropractic Manipulation, Spinal (1-2 Rg)','cat' => 'Chiro'],
            '98941' => ['desc' => 'Chiropractic Manipulation, Spinal (3-4 Rg)','cat' => 'Chiro'],
            '98942' => ['desc' => 'Chiropractic Manipulation, Spinal (5 Rg)',  'cat' => 'Chiro'],
        ];

        foreach ($fromPrototype as $code => $description) {
            CptCode::updateOrCreate(
                ['code' => $code, 'effectiveFrom' => $effective],
                [
                    'description' => $description,
                    'category' => 'E/M',
                    'active' => true,
                ],
            );
        }

        foreach ($additional as $code => $row) {
            CptCode::updateOrCreate(
                ['code' => $code, 'effectiveFrom' => $effective],
                [
                    'description' => $row['desc'],
                    'category' => $row['cat'],
                    'active' => true,
                ],
            );
        }
    }

    // ----- ICD-10 codes (~80 WC-focused) ------------------------------------

    private function seedIcd10Codes(): void
    {
        $effective = '2024-01-01';

        $codes = [
            // Dorsalgia (M54 series)
            'M54.10' => ['desc' => 'Radiculopathy, site unspecified',                 'ch' => 'M00-M99 Musculoskeletal'],
            'M54.11' => ['desc' => 'Radiculopathy, occipito-atlanto-axial region',    'ch' => 'M00-M99 Musculoskeletal'],
            'M54.12' => ['desc' => 'Radiculopathy, cervical region',                  'ch' => 'M00-M99 Musculoskeletal'],
            'M54.13' => ['desc' => 'Radiculopathy, cervicothoracic region',           'ch' => 'M00-M99 Musculoskeletal'],
            'M54.14' => ['desc' => 'Radiculopathy, thoracic region',                  'ch' => 'M00-M99 Musculoskeletal'],
            'M54.15' => ['desc' => 'Radiculopathy, thoracolumbar region',             'ch' => 'M00-M99 Musculoskeletal'],
            'M54.16' => ['desc' => 'Radiculopathy, lumbar region',                    'ch' => 'M00-M99 Musculoskeletal'],
            'M54.17' => ['desc' => 'Radiculopathy, lumbosacral region',               'ch' => 'M00-M99 Musculoskeletal'],
            'M54.18' => ['desc' => 'Radiculopathy, sacral and sacrococcygeal region', 'ch' => 'M00-M99 Musculoskeletal'],
            'M54.2'  => ['desc' => 'Cervicalgia',                                     'ch' => 'M00-M99 Musculoskeletal'],
            'M54.30' => ['desc' => 'Sciatica, unspecified side',                      'ch' => 'M00-M99 Musculoskeletal'],
            'M54.31' => ['desc' => 'Sciatica, right side',                            'ch' => 'M00-M99 Musculoskeletal'],
            'M54.32' => ['desc' => 'Sciatica, left side',                             'ch' => 'M00-M99 Musculoskeletal'],
            'M54.40' => ['desc' => 'Lumbago with sciatica, unspecified side',         'ch' => 'M00-M99 Musculoskeletal'],
            'M54.41' => ['desc' => 'Lumbago with sciatica, right side',               'ch' => 'M00-M99 Musculoskeletal'],
            'M54.42' => ['desc' => 'Lumbago with sciatica, left side',                'ch' => 'M00-M99 Musculoskeletal'],
            'M54.50' => ['desc' => 'Low back pain, unspecified',                      'ch' => 'M00-M99 Musculoskeletal'],
            'M54.51' => ['desc' => 'Vertebrogenic low back pain',                     'ch' => 'M00-M99 Musculoskeletal'],
            'M54.59' => ['desc' => 'Other low back pain',                             'ch' => 'M00-M99 Musculoskeletal'],
            'M54.6'  => ['desc' => 'Pain in thoracic spine',                          'ch' => 'M00-M99 Musculoskeletal'],

            // IVD disorders (M50, M51)
            'M50.10' => ['desc' => 'Cervical disc disorder, unspecified region',      'ch' => 'M00-M99 Musculoskeletal'],
            'M50.11' => ['desc' => 'Cervical disc disorder, high cervical region',    'ch' => 'M00-M99 Musculoskeletal'],
            'M50.120'=> ['desc' => 'Cervical disc disorder, mid-cervical unspecified','ch' => 'M00-M99 Musculoskeletal'],
            'M50.121'=> ['desc' => 'Cervical disc disorder, C4-C5',                   'ch' => 'M00-M99 Musculoskeletal'],
            'M50.122'=> ['desc' => 'Cervical disc disorder, C5-C6',                   'ch' => 'M00-M99 Musculoskeletal'],
            'M50.123'=> ['desc' => 'Cervical disc disorder, C6-C7',                   'ch' => 'M00-M99 Musculoskeletal'],
            'M50.13' => ['desc' => 'Cervical disc disorder, cervicothoracic region',  'ch' => 'M00-M99 Musculoskeletal'],
            'M51.16' => ['desc' => 'Intervertebral disc disorder, lumbar L4-L5',      'ch' => 'M00-M99 Musculoskeletal'],
            'M51.17' => ['desc' => 'Intervertebral disc disorder, lumbosacral',       'ch' => 'M00-M99 Musculoskeletal'],
            'M51.26' => ['desc' => 'Other disc displacement, lumbar region',          'ch' => 'M00-M99 Musculoskeletal'],
            'M51.27' => ['desc' => 'Other disc displacement, lumbosacral region',     'ch' => 'M00-M99 Musculoskeletal'],
            'M51.36' => ['desc' => 'Other disc degeneration, lumbar region',          'ch' => 'M00-M99 Musculoskeletal'],
            'M51.37' => ['desc' => 'Other disc degeneration, lumbosacral region',     'ch' => 'M00-M99 Musculoskeletal'],

            // OA — knee / hip / shoulder
            'M16.0'  => ['desc' => 'Primary OA of hip, bilateral',                    'ch' => 'M00-M99 Musculoskeletal'],
            'M16.11' => ['desc' => 'Unilateral primary OA, right hip',                'ch' => 'M00-M99 Musculoskeletal'],
            'M16.12' => ['desc' => 'Unilateral primary OA, left hip',                 'ch' => 'M00-M99 Musculoskeletal'],
            'M17.0'  => ['desc' => 'Primary OA of knee, bilateral',                   'ch' => 'M00-M99 Musculoskeletal'],
            'M17.11' => ['desc' => 'Unilateral primary OA, right knee',               'ch' => 'M00-M99 Musculoskeletal'],
            'M17.12' => ['desc' => 'Unilateral primary OA, left knee',                'ch' => 'M00-M99 Musculoskeletal'],
            'M19.011'=> ['desc' => 'Primary OA, right shoulder',                      'ch' => 'M00-M99 Musculoskeletal'],
            'M19.012'=> ['desc' => 'Primary OA, left shoulder',                       'ch' => 'M00-M99 Musculoskeletal'],

            // Shoulder / rotator cuff
            'M75.100' => ['desc' => 'Unspecified rotator cuff tear, unspecified side','ch' => 'M00-M99 Musculoskeletal'],
            'M75.101' => ['desc' => 'Unspecified rotator cuff tear, right',           'ch' => 'M00-M99 Musculoskeletal'],
            'M75.102' => ['desc' => 'Unspecified rotator cuff tear, left',            'ch' => 'M00-M99 Musculoskeletal'],
            'M75.41'  => ['desc' => 'Impingement syndrome of right shoulder',         'ch' => 'M00-M99 Musculoskeletal'],
            'M75.42'  => ['desc' => 'Impingement syndrome of left shoulder',          'ch' => 'M00-M99 Musculoskeletal'],

            // Sprains — cervical, lumbar, knee (ACL), ankle
            'S13.4XXA' => ['desc' => 'Sprain of ligaments of cervical spine, initial',         'ch' => 'S00-T88 Injury/Poisoning'],
            'S13.4XXD' => ['desc' => 'Sprain of ligaments of cervical spine, subsequent',      'ch' => 'S00-T88 Injury/Poisoning'],
            'S33.5XXA' => ['desc' => 'Sprain of ligaments of lumbar spine, initial',           'ch' => 'S00-T88 Injury/Poisoning'],
            'S33.5XXD' => ['desc' => 'Sprain of ligaments of lumbar spine, subsequent',        'ch' => 'S00-T88 Injury/Poisoning'],
            'S83.511A' => ['desc' => 'Sprain of ACL, right knee, initial',                     'ch' => 'S00-T88 Injury/Poisoning'],
            'S83.511D' => ['desc' => 'Sprain of ACL, right knee, subsequent',                  'ch' => 'S00-T88 Injury/Poisoning'],
            'S83.512A' => ['desc' => 'Sprain of ACL, left knee, initial',                      'ch' => 'S00-T88 Injury/Poisoning'],
            'S83.512D' => ['desc' => 'Sprain of ACL, left knee, subsequent',                   'ch' => 'S00-T88 Injury/Poisoning'],
            'S83.521A' => ['desc' => 'Sprain of PCL, right knee, initial',                     'ch' => 'S00-T88 Injury/Poisoning'],
            'S83.522A' => ['desc' => 'Sprain of PCL, left knee, initial',                      'ch' => 'S00-T88 Injury/Poisoning'],
            'S93.401A' => ['desc' => 'Sprain of unspecified ligament, right ankle, initial',   'ch' => 'S00-T88 Injury/Poisoning'],
            'S93.402A' => ['desc' => 'Sprain of unspecified ligament, left ankle, initial',    'ch' => 'S00-T88 Injury/Poisoning'],

            // Fractures (common in WC)
            'S42.001A' => ['desc' => 'Fracture of clavicle, right, initial, closed',           'ch' => 'S00-T88 Injury/Poisoning'],
            'S52.501A' => ['desc' => 'Fracture of lower end of right radius, initial, closed', 'ch' => 'S00-T88 Injury/Poisoning'],
            'S72.001A' => ['desc' => 'Fracture of neck of right femur, initial, closed',       'ch' => 'S00-T88 Injury/Poisoning'],
            'S82.101A' => ['desc' => 'Fracture of upper end of right tibia, initial, closed',  'ch' => 'S00-T88 Injury/Poisoning'],

            // Strains
            'S39.012A' => ['desc' => 'Strain of muscle/fascia/tendon of lower back, initial',  'ch' => 'S00-T88 Injury/Poisoning'],
            'S29.011A' => ['desc' => 'Strain of muscle/tendon of thorax, initial',             'ch' => 'S00-T88 Injury/Poisoning'],

            // Neuropathies
            'G56.00' => ['desc' => 'Carpal tunnel syndrome, unspecified upper limb',           'ch' => 'G00-G99 Nervous System'],
            'G56.01' => ['desc' => 'Carpal tunnel syndrome, right upper limb',                 'ch' => 'G00-G99 Nervous System'],
            'G56.02' => ['desc' => 'Carpal tunnel syndrome, left upper limb',                  'ch' => 'G00-G99 Nervous System'],
            'G56.20' => ['desc' => 'Lesion of ulnar nerve, unspecified upper limb',            'ch' => 'G00-G99 Nervous System'],
            'G57.10' => ['desc' => 'Meralgia paresthetica, unspecified lower limb',            'ch' => 'G00-G99 Nervous System'],

            // Tendonitis / enthesopathy
            'M65.811' => ['desc' => 'Other synovitis/tenosynovitis, right shoulder',           'ch' => 'M00-M99 Musculoskeletal'],
            'M77.11'  => ['desc' => 'Lateral epicondylitis, right elbow',                      'ch' => 'M00-M99 Musculoskeletal'],
            'M77.12'  => ['desc' => 'Lateral epicondylitis, left elbow',                       'ch' => 'M00-M99 Musculoskeletal'],
            'M77.51'  => ['desc' => 'Other enthesopathy, right foot',                          'ch' => 'M00-M99 Musculoskeletal'],

            // Concussion / TBI
            'S06.0X0A' => ['desc' => 'Concussion without loss of consciousness, initial',      'ch' => 'S00-T88 Injury/Poisoning'],
            'S06.0X1A' => ['desc' => 'Concussion with LOC ≤30 min, initial',                   'ch' => 'S00-T88 Injury/Poisoning'],

            // Mental health — work-related stress
            'F43.23' => ['desc' => 'Adjustment disorder with mixed anxiety and depressed mood','ch' => 'F01-F99 Mental/Behavioral'],

            // Symptom codes often used on WC
            'R52'    => ['desc' => 'Pain, unspecified',                                        'ch' => 'R00-R99 Symptoms/Signs'],
            'R25.2'  => ['desc' => 'Cramp and spasm',                                          'ch' => 'R00-R99 Symptoms/Signs'],
        ];

        foreach ($codes as $code => $row) {
            Icd10Code::updateOrCreate(
                ['code' => $code, 'effectiveFrom' => $effective],
                [
                    'description' => $row['desc'],
                    'chapter' => $row['ch'],
                    'billable' => true,
                    'active' => true,
                ],
            );
        }
    }

    // ----- HCPCS codes (small starter set) ----------------------------------

    private function seedHcpcsCodes(): void
    {
        $effective = '2024-01-01';

        $codes = [
            'A4550' => ['desc' => 'Surgical trays',                    'cat' => 'Supplies'],
            'A9270' => ['desc' => 'Non-covered item or service',       'cat' => 'Misc'],
            'E0112' => ['desc' => 'Crutches, underarm, wood',          'cat' => 'DME'],
            'E0114' => ['desc' => 'Crutches, underarm, aluminum',      'cat' => 'DME'],
            'E0470' => ['desc' => 'BiPAP without backup',              'cat' => 'DME'],
            'L0625' => ['desc' => 'Lumbar-sacral orthosis, flexible',  'cat' => 'Orthotic'],
            'L0627' => ['desc' => 'Lumbar-sacral orthosis, sagittal control','cat' => 'Orthotic'],
            'L1833' => ['desc' => 'Knee orthosis, adjustable knee joints','cat' => 'Orthotic'],
            'J1030' => ['desc' => 'Methylprednisolone acetate 40mg',   'cat' => 'Injection'],
            'J1100' => ['desc' => 'Dexamethasone sodium phosphate 1mg','cat' => 'Injection'],
            'J7325' => ['desc' => 'Hyaluronan (Synvisc), per dose',    'cat' => 'Injection'],
        ];

        foreach ($codes as $code => $row) {
            HcpcsCode::updateOrCreate(
                ['code' => $code, 'effectiveFrom' => $effective],
                [
                    'description' => $row['desc'],
                    'category' => $row['cat'],
                    'active' => true,
                ],
            );
        }
    }
}
