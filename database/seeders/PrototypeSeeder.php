<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CaseStatus;
use App\Enums\EmrStatus;
use App\Enums\InputSource;
use App\Enums\ReportType;
use App\Enums\UsState;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\ClinicAdmin;
use App\Models\EmrConnection;
use App\Models\EmrSystem;
use App\Models\MedicalCase;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Mirrors the JSX prototype seed arrays (USERS, CLINICS, PROVIDERS,
 * CLINIC_ADMINS, APPOINTMENTS, SERVICES, PATIENTS, CASES) row-for-row into
 * the Rocket Coding DB. After running, `php artisan tinker` can `Clinic::all()`
 * and the results match what the React prototype renders from its in-memory
 * fixtures.
 *
 * Idempotent: every insert uses updateOrCreate on a natural unique key
 * (email for users/clinics, npi for providers, num for cases, id for services,
 * etc.). Running twice does not create duplicates; it refreshes attributes.
 *
 * Password for every seeded user: "demo123" (bcrypt-hashed — respects
 * BCRYPT_ROUNDS from .env, default 12).
 *
 * Five extra demo users are seeded on top of the JSX's 10 to cover the
 * provider/staff/deployer roles (flagged as ambiguity #3 in STEP-2-NOTES).
 */
class PrototypeSeeder extends Seeder
{
    /** @var array<string, string> JSX clinic-id → DB UUID */
    private array $clinicIds = [];

    /** @var array<string, string> JSX user-id → DB UUID */
    private array $userIds = [];

    /** @var array<string, string> JSX provider-id → DB UUID */
    private array $providerIds = [];

    /** @var array<string, string> JSX patient-id → DB UUID */
    private array $patientIds = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->seedClinics();
            $this->seedUsers();
            $this->seedProviders();
            $this->seedClinicAdmins();
            $this->seedServices();
            $this->seedPatients();
            $this->seedAppointments();
            $this->seedEmrConnections();
            $this->seedCases();
        });

        $this->command?->info(sprintf(
            '  Prototype: %d clinics (%d selfCoded), %d users, %d providers, %d clinic admins, %d services, %d patients, %d appointments, %d EMR connections, %d cases',
            Clinic::count(),
            Clinic::where('selfCoded', true)->count(),
            User::count(),
            Provider::count(),
            ClinicAdmin::count(),
            Service::count(),
            Patient::count(),
            Appointment::count(),
            EmrConnection::count(),
            MedicalCase::count(),
        ));
    }

    // ========================================================================
    // JSX CLINICS (line 15) — 3 rows, selfCoded flags preserved verbatim
    // ========================================================================

    private function seedClinics(): void
    {
        $clinics = [
            'c1' => [
                'name' => 'Valley Medical Group',
                'state' => 'CA',
                'email' => 'contact@valleymed.com',
                'active' => true,
                'city' => 'Bakersfield',
                'addr' => '1200 Truxtun Ave, Suite 400',
                'zip' => '93301',
                'phone' => '(661) 555-0142',
                'npi' => '1679584729',
                'taxId' => '84-2937164',
                'timezone' => 'America/Los_Angeles',
                'selfCoded' => true,
                'reportFavs' => ['initial', 'pr2', 'mmi'],
                'notif' => [
                    'caseStatus' => true, 'overdue' => true, 'aiFlags' => true,
                    'payments' => false, 'channelEmail' => true, 'channelSMS' => false,
                ],
            ],
            'c2' => [
                'name' => 'Coastal Orthopedics',
                'state' => 'TX',
                'email' => 'contact@coastalortho.com',
                'active' => true,
                'city' => 'Austin',
                'addr' => '3400 South Lamar Blvd',
                'zip' => '78704',
                'phone' => '(512) 555-0198',
                'npi' => '1538729471',
                'taxId' => '74-1829463',
                'timezone' => 'America/Chicago',
                'selfCoded' => false,
                'reportFavs' => ['initial', 'qme', 'followup'],
                'notif' => [
                    'caseStatus' => true, 'overdue' => true, 'aiFlags' => false,
                    'payments' => true, 'channelEmail' => true, 'channelSMS' => true,
                ],
            ],
            'c3' => [
                'name' => 'Summit Pain Mgmt',
                'state' => 'NY',
                'email' => 'info@summitpain.com',
                'active' => false,
                'city' => 'Buffalo',
                'addr' => '500 Delaware Ave',
                'zip' => '14202',
                'phone' => '(716) 555-0173',
                'npi' => '1847362954',
                'taxId' => '13-7462819',
                'timezone' => 'America/New_York',
                'selfCoded' => false,
                'reportFavs' => ['initial'],
                'notif' => [
                    'caseStatus' => true, 'overdue' => false, 'aiFlags' => false,
                    'payments' => false, 'channelEmail' => true, 'channelSMS' => false,
                ],
            ],
        ];

        foreach ($clinics as $jsxId => $attrs) {
            $clinic = Clinic::updateOrCreate(
                ['email' => $attrs['email']],
                $attrs,
            );
            $this->clinicIds[$jsxId] = $clinic->id;
        }
    }

    // ========================================================================
    // Users — JSX USERS (line 3, 10 rows) + 5 demo users for the other roles
    // ========================================================================

    private function seedUsers(): void
    {
        $password = Hash::make('demo123');

        $users = [
            // ----- from JSX USERS (10 rows, role: clinic or admin) -----
            'u1' => ['name' => 'Dr. Sarah Chen',     'email' => 'clinic@valleymed.com',      'role' => 'clinic', 'cid' => $this->clinicIds['c1'], 'initials' => 'SC', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-17'],
            'u2' => ['name' => 'Jennifer Walsh',     'email' => 'jwalsh@valleymed.com',       'role' => 'clinic', 'cid' => $this->clinicIds['c1'], 'initials' => 'JW', 'permission' => 'scheduler', 'active' => true,  'lastLogin' => '2026-04-17'],
            'u3' => ['name' => 'Tom Rodriguez',      'email' => 'trodriguez@valleymed.com',   'role' => 'clinic', 'cid' => $this->clinicIds['c1'], 'initials' => 'TR', 'permission' => 'coder',     'active' => true,  'lastLogin' => '2026-04-16'],
            'u4' => ['name' => 'Dr. Marcus Okonkwo', 'email' => 'mokonkwo@valleymed.com',     'role' => 'clinic', 'cid' => $this->clinicIds['c1'], 'initials' => 'MO', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-15'],
            'u5' => ['name' => 'Dr. James Park',     'email' => 'jpark@coastalortho.com',     'role' => 'clinic', 'cid' => $this->clinicIds['c2'], 'initials' => 'JP', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-17'],
            'u6' => ['name' => 'Linda Chen',         'email' => 'lchen@coastalortho.com',     'role' => 'clinic', 'cid' => $this->clinicIds['c2'], 'initials' => 'LC', 'permission' => 'scheduler', 'active' => true,  'lastLogin' => '2026-04-15'],
            'u7' => ['name' => 'Dr. Robert Hayes',   'email' => 'rhayes@summitpain.com',      'role' => 'clinic', 'cid' => $this->clinicIds['c3'], 'initials' => 'RH', 'permission' => 'viewer',    'active' => false, 'lastLogin' => '2026-03-28'],
            'a1' => ['name' => 'Rocket Admin',       'email' => 'admin@rocketcoding.com',     'role' => 'admin',  'cid' => null,                   'initials' => 'RA', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-18'],
            'a2' => ['name' => 'Gene Howell',        'email' => 'gene@snapsolutions.com',     'role' => 'admin',  'cid' => null,                   'initials' => 'GH', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-18'],
            'a3' => ['name' => 'Priya Shah',         'email' => 'pshah@snapsolutions.com',    'role' => 'admin',  'cid' => null,                   'initials' => 'PS', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-16'],

            // ----- added to resolve STEP-2-NOTES ambiguity #3 (5 roles need example logins) -----
            'u8'  => ['name' => 'Dr. Priya Nair',    'email' => 'pnair@valleymed.com',        'role' => 'provider','cid' => $this->clinicIds['c1'], 'initials' => 'PN', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-17'],
            'u9'  => ['name' => 'Dr. Elena Vasquez', 'email' => 'evasquez@coastalortho.com',  'role' => 'provider','cid' => $this->clinicIds['c2'], 'initials' => 'EV', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-16'],
            'u10' => ['name' => 'Maria Gonzalez',    'email' => 'mgonzalez@valleymed.com',    'role' => 'staff',   'cid' => $this->clinicIds['c1'], 'initials' => 'MG', 'permission' => 'scheduler', 'active' => true,  'lastLogin' => '2026-04-18'],
            'u11' => ['name' => 'David Kowalski',    'email' => 'dkowalski@coastalortho.com', 'role' => 'staff',   'cid' => $this->clinicIds['c2'], 'initials' => 'DK', 'permission' => 'scheduler', 'active' => true,  'lastLogin' => '2026-04-18'],
            'd1'  => ['name' => 'Deployer Root',     'email' => 'deployer@rocketcoding.com',  'role' => 'deployer','cid' => null,                   'initials' => 'DR', 'permission' => 'full',      'active' => true,  'lastLogin' => '2026-04-18'],
        ];

        foreach ($users as $jsxId => $attrs) {
            $attrs['password'] = $password;

            $user = User::updateOrCreate(
                ['email' => $attrs['email']],
                $attrs,
            );
            $this->userIds[$jsxId] = $user->id;
        }
    }

    // ========================================================================
    // Providers — JSX PROVIDERS (line 19, 6 rows)
    // ========================================================================

    private function seedProviders(): void
    {
        $providers = [
            // p1 Sarah Chen is also u1 (has a User login) — link them via userId
            'p1' => ['clinic' => $this->clinicIds['c1'], 'userId' => $this->userIds['u1'], 'name' => 'Dr. Sarah Chen, MD',     'npi' => '1275893647', 'license' => 'A92374 (CA)',   'specialty' => 'Occupational Medicine',     'sigBlock' => "Sarah Chen, MD\nBoard Certified, Occupational Medicine\nMedical Director, Valley Medical Group"],
            'p2' => ['clinic' => $this->clinicIds['c1'], 'userId' => $this->userIds['u4'], 'name' => 'Dr. Marcus Okonkwo, DO', 'npi' => '1384729561', 'license' => 'B45782 (CA)',   'specialty' => 'Physical Medicine & Rehab', 'sigBlock' => "Marcus Okonkwo, DO\nBoard Certified, PM&R\nValley Medical Group"],
            'p3' => ['clinic' => $this->clinicIds['c1'], 'userId' => $this->userIds['u8'], 'name' => 'Dr. Priya Nair, MD',     'npi' => '1596473821', 'license' => 'A88124 (CA)',   'specialty' => 'Orthopedic Surgery',        'sigBlock' => "Priya Nair, MD\nBoard Certified, Orthopedic Surgery\nValley Medical Group"],
            'p4' => ['clinic' => $this->clinicIds['c2'], 'userId' => $this->userIds['u5'], 'name' => 'Dr. James Park, MD',     'npi' => '1847293615', 'license' => 'TX-8472 (TX)',  'specialty' => 'Orthopedic Surgery',        'sigBlock' => "James Park, MD\nBoard Certified, Orthopedic Surgery\nCoastal Orthopedics"],
            'p5' => ['clinic' => $this->clinicIds['c2'], 'userId' => $this->userIds['u9'], 'name' => 'Dr. Elena Vasquez, MD',  'npi' => '1736482917', 'license' => 'TX-9283 (TX)',  'specialty' => 'Sports Medicine',           'sigBlock' => "Elena Vasquez, MD\nBoard Certified, Sports Medicine\nCoastal Orthopedics"],
            'p6' => ['clinic' => $this->clinicIds['c3'], 'userId' => $this->userIds['u7'], 'name' => 'Dr. Robert Hayes, MD',   'npi' => '1628374592', 'license' => 'NY-4571 (NY)',  'specialty' => 'Pain Management',           'sigBlock' => "Robert Hayes, MD\nBoard Certified, Pain Management\nSummit Pain Mgmt"],
        ];

        foreach ($providers as $jsxId => $attrs) {
            $provider = Provider::updateOrCreate(
                ['npi' => $attrs['npi']],
                $attrs + ['active' => true],
            );
            $this->providerIds[$jsxId] = $provider->id;
        }
    }

    // ========================================================================
    // Clinic admins — JSX CLINIC_ADMINS (line 27, 4 rows)
    // ========================================================================

    private function seedClinicAdmins(): void
    {
        $admins = [
            ['clinic' => $this->clinicIds['c1'], 'name' => 'Jennifer Walsh',  'email' => 'jwalsh@valleymed.com',        'role' => 'Clinic Admin',    'lastLogin' => '2026-04-17'],
            ['clinic' => $this->clinicIds['c1'], 'name' => 'Tom Rodriguez',   'email' => 'trodriguez@valleymed.com',    'role' => 'Office Manager',  'lastLogin' => '2026-04-16'],
            ['clinic' => $this->clinicIds['c2'], 'name' => 'Linda Chen',      'email' => 'lchen@coastalortho.com',      'role' => 'Clinic Admin',    'lastLogin' => '2026-04-15'],
            ['clinic' => $this->clinicIds['c3'], 'name' => 'Mark Davidson',   'email' => 'mdavidson@summitpain.com',    'role' => 'Clinic Admin',    'lastLogin' => '2026-03-28'],
        ];

        foreach ($admins as $attrs) {
            ClinicAdmin::updateOrCreate(
                ['clinic' => $attrs['clinic'], 'email' => $attrs['email']],
                $attrs,
            );
        }
    }

    // ========================================================================
    // Services — JSX SERVICES (line 42, 13 rows) — PK preserved (s1, s5, sT)
    // ========================================================================

    private function seedServices(): void
    {
        $services = [
            ['id' => 's1',  'cat' => 'Clinical',         'title' => 'Initial Evaluation',   'price' => 275.00, 'sortOrder' => 100],
            ['id' => 's2',  'cat' => 'Clinical',         'title' => 'Follow-Up',            'price' => 175.00, 'sortOrder' => 110],
            ['id' => 's3',  'cat' => 'Clinical',         'title' => 'Procedure Note',       'price' => 225.00, 'sortOrder' => 120],
            ['id' => 's5',  'cat' => 'Work Comp',        'title' => 'PR-1',                 'price' => 350.00, 'sortOrder' => 200],
            ['id' => 's6',  'cat' => 'Work Comp',        'title' => 'PR-2',                 'price' => 300.00, 'sortOrder' => 210],
            ['id' => 's8',  'cat' => 'Work Comp',        'title' => 'MMI / P&S',            'price' => 450.00, 'sortOrder' => 220],
            ['id' => 's9',  'cat' => 'Work Comp',        'title' => 'Impairment Rating',    'price' => 500.00, 'sortOrder' => 230],
            ['id' => 's10', 'cat' => 'Med-Legal',        'title' => 'QME',                  'price' => 750.00, 'sortOrder' => 300],
            ['id' => 's11', 'cat' => 'Med-Legal',        'title' => 'AME',                  'price' => 850.00, 'sortOrder' => 310],
            ['id' => 's12', 'cat' => 'Med-Legal',        'title' => 'Causation',            'price' => 600.00, 'sortOrder' => 320],
            ['id' => 's16', 'cat' => 'Coding & Billing', 'title' => 'Coding Review',        'price' => 125.00, 'sortOrder' => 400],
            ['id' => 's17', 'cat' => 'Coding & Billing', 'title' => 'Chart Audit',          'price' => 200.00, 'sortOrder' => 410],
            ['id' => 'sT',  'cat' => 'Coding & Billing', 'title' => 'AI Transcription',     'price' => 75.00,  'sortOrder' => 420],
        ];

        foreach ($services as $row) {
            Service::updateOrCreate(
                ['id' => $row['id']],
                $row + ['active' => true],
            );
        }
    }

    // ========================================================================
    // Patients — JSX PATIENTS (line 70, 3 rows; add clinicId + providerId)
    // ========================================================================

    private function seedPatients(): void
    {
        // Patient → assigned clinic comes from the JSX `provider` field
        // (string name). We resolve to the matching Provider row and pull
        // the clinic from there. Flagged in STEP-2-NOTES ambiguity #1.
        $patients = [
            'p1' => ['name' => 'Robert Martinez',   'dob' => '1978-03-12', 'emrId' => 'EMR-10042', 'phone' => '(555)234-5678', 'employer' => 'Pacific Logistics',        'doi' => '2026-01-28', 'providerJsxId' => 'p1', 'reportStatus' => 'completed'],
            'p2' => ['name' => 'Angela Thompson',   'dob' => '1985-07-22', 'emrId' => 'EMR-10078', 'phone' => '(555)345-6789', 'employer' => 'Valley Regional Hospital', 'doi' => '2025-06-15', 'providerJsxId' => 'p1', 'reportStatus' => 'needs_report'],
            'p3' => ['name' => 'David Kim',         'dob' => '1990-11-05', 'emrId' => 'EMR-20015', 'phone' => '(555)456-7890', 'employer' => 'TechCore Inc',             'doi' => '2025-09-03', 'providerJsxId' => 'p4', 'reportStatus' => 'needs_report'],
        ];

        foreach ($patients as $jsxId => $attrs) {
            $providerUuid = $this->providerIds[$attrs['providerJsxId']];
            $clinicUuid = Provider::withoutGlobalScopes()->find($providerUuid)->clinic;

            $patientAttrs = [
                'clinicId' => $clinicUuid,
                'providerId' => $providerUuid,
                'name' => $attrs['name'],
                'dob' => $attrs['dob'],
                'emrId' => $attrs['emrId'],
                'phone' => $attrs['phone'],
                'employer' => $attrs['employer'],
                'doi' => $attrs['doi'],
                'reportStatus' => $attrs['reportStatus'],
            ];

            $patient = Patient::withoutGlobalScopes()->updateOrCreate(
                ['clinicId' => $clinicUuid, 'emrId' => $attrs['emrId']],
                $patientAttrs,
            );
            $this->patientIds[$jsxId] = $patient->id;
        }
    }

    // ========================================================================
    // Appointments — JSX APPOINTMENTS (line 33, 8 rows)
    // ========================================================================

    private function seedAppointments(): void
    {
        $appointments = [
            ['date' => '2026-04-21', 'time' => '08:00:00', 'patient' => 'Robert Martinez', 'provider' => 'Dr. Sarah Chen',     'company' => 'Valley Medical Group', 'visitType' => 'initial',   'claim' => 'WC-2026-44521', 'dictation' => true,  'chartUploaded' => true,  'clinic' => 'c1', 'providerJsx' => 'p1', 'patientJsx' => 'p1'],
            ['date' => '2026-04-21', 'time' => '09:30:00', 'patient' => 'Angela Thompson', 'provider' => 'Dr. Sarah Chen',     'company' => 'Valley Medical Group', 'visitType' => 'mmi',       'claim' => 'WC-2026-55892', 'dictation' => false, 'chartUploaded' => true,  'clinic' => 'c1', 'providerJsx' => 'p1', 'patientJsx' => 'p2'],
            ['date' => '2026-04-21', 'time' => '11:00:00', 'patient' => 'David Kim',       'provider' => 'Dr. James Park',     'company' => 'Coastal Orthopedics',  'visitType' => 'qme',       'claim' => 'WC-2026-67103', 'dictation' => true,  'chartUploaded' => false, 'clinic' => 'c2', 'providerJsx' => 'p4', 'patientJsx' => 'p3'],
            ['date' => '2026-04-21', 'time' => '13:00:00', 'patient' => 'Luis Ramirez',    'provider' => 'Dr. Sarah Chen',     'company' => 'Valley Medical Group', 'visitType' => 'followup',  'claim' => 'WC-2026-77834', 'dictation' => false, 'chartUploaded' => false, 'clinic' => 'c1', 'providerJsx' => 'p1', 'patientJsx' => null],
            ['date' => '2026-04-21', 'time' => '14:30:00', 'patient' => 'Jennifer Walsh',  'provider' => 'Dr. Marcus Okonkwo', 'company' => 'Valley Medical Group', 'visitType' => 'initial',   'claim' => 'WC-2026-77831', 'dictation' => false, 'chartUploaded' => true,  'clinic' => 'c1', 'providerJsx' => 'p2', 'patientJsx' => null],
            ['date' => '2026-04-22', 'time' => '09:00:00', 'patient' => 'Marcus Chen',     'provider' => 'Dr. Elena Vasquez',  'company' => 'Coastal Orthopedics',  'visitType' => 'initial',   'claim' => 'WC-2026-77832', 'dictation' => false, 'chartUploaded' => false, 'clinic' => 'c2', 'providerJsx' => 'p5', 'patientJsx' => null],
            ['date' => '2026-04-22', 'time' => '10:30:00', 'patient' => 'Sarah Blackwell', 'provider' => 'Dr. Elena Vasquez',  'company' => 'Coastal Orthopedics',  'visitType' => 'qme',       'claim' => null,            'dictation' => false, 'chartUploaded' => false, 'clinic' => 'c2', 'providerJsx' => 'p5', 'patientJsx' => null],
            ['date' => '2026-04-23', 'time' => '14:00:00', 'patient' => 'Robert Hayes',    'provider' => 'Dr. Priya Nair',     'company' => 'Valley Medical Group', 'visitType' => 'causation', 'claim' => 'WC-2026-88012', 'dictation' => false, 'chartUploaded' => false, 'clinic' => 'c1', 'providerJsx' => 'p3', 'patientJsx' => null],
        ];

        foreach ($appointments as $a) {
            $clinicUuid = $this->clinicIds[$a['clinic']];
            $providerUuid = $this->providerIds[$a['providerJsx']];
            $patientUuid = $a['patientJsx'] ? $this->patientIds[$a['patientJsx']] : null;

            $attrs = [
                'clinicId' => $clinicUuid,
                'providerId' => $providerUuid,
                'patientId' => $patientUuid,
                'date' => $a['date'],
                'time' => $a['time'],
                'patient' => $a['patient'],
                'provider' => $a['provider'],
                'company' => $a['company'],
                'visitType' => $a['visitType'],
                'claim' => $a['claim'],
                'dictation' => $a['dictation'],
                'chartUploaded' => $a['chartUploaded'],
                'status' => 'scheduled',
            ];

            Appointment::withoutGlobalScopes()->updateOrCreate(
                ['clinicId' => $clinicUuid, 'date' => $a['date'], 'time' => $a['time'], 'patient' => $a['patient']],
                $attrs,
            );
        }
    }

    // ========================================================================
    // EMR connections — for each clinic, mirror the status from JSX EMR_SYSTEMS
    // ========================================================================

    private function seedEmrConnections(): void
    {
        // JSX shows 2 "Connected", 1 "Pending", rest "Not Connected" on the
        // Deployer screen. These are systems-wide statuses, but in practice
        // each clinic has its own connection state. We attach the two
        // "Connected" ones to Valley Medical (c1) as an example integration.
        $map = [
            ['clinicJsx' => 'c1', 'emrName' => 'Epic',          'status' => EmrStatus::Connected->value,    'endpoint' => 'https://fhir.valleymed.epic.com/api/FHIR/R4', 'syncFields' => ['Demographics', 'Encounters', 'Documents']],
            ['clinicJsx' => 'c1', 'emrName' => 'athenahealth',  'status' => EmrStatus::Connected->value,    'endpoint' => 'https://api.athenahealth.com/preview1', 'syncFields' => ['Patient', 'Encounters']],
            ['clinicJsx' => 'c2', 'emrName' => 'eClinicalWorks','status' => EmrStatus::Pending->value,      'endpoint' => 'sftp://emr.coastalortho.com/inbox', 'syncFields' => ['Demographics', 'Documents']],
        ];

        foreach ($map as $m) {
            $emrSystem = EmrSystem::where('name', $m['emrName'])->first();
            if (! $emrSystem) {
                continue;
            }

            $clinicUuid = $this->clinicIds[$m['clinicJsx']];

            EmrConnection::withoutGlobalScopes()->updateOrCreate(
                ['clinicId' => $clinicUuid, 'emrSystemId' => $emrSystem->id],
                [
                    'status' => $m['status'],
                    'endpoint' => $m['endpoint'],
                    'syncFields' => $m['syncFields'],
                ],
            );
        }
    }

    // ========================================================================
    // Cases — JSX CASES (line 75, 3 rows) — FULL data incl. notes + AI outputs
    // ========================================================================

    private function seedCases(): void
    {
        $cases = [
            [
                'num' => 'SNP-0001',
                'patient' => 'Robert Martinez',
                'patientJsx' => 'p1',
                'dob' => '1978-03-12',
                'dos' => '2026-02-10',
                'gender' => 'Male',
                'claimNum' => 'WC-2026-44521',
                'emrId' => 'EMR-10042',
                'status' => CaseStatus::Delivered->value,
                'svcs' => ['s1', 's16'],
                'total' => 400.00,
                'clinicJsx' => 'c1',
                'clinicName' => 'Valley Medical Group',
                'providerJsx' => 'p1',
                'state' => 'CA',
                'reportType' => ReportType::Initial->value,
                'inputSource' => InputSource::Upload->value,
                'notes' => "HISTORY OF PRESENT ILLNESS:\nRobert Martinez, 47yo warehouse supervisor, work injury 2026-01-28 lifting 60lb box. Lower back pain radiating left leg, 6/10 rest, 8/10 activity.\n\nPHYSICAL EXAM:\nTenderness L4-L5. +SLR left 40°. Motor 4+/5 left EHL. Decreased L5 sensation. DTR 1+ left Achilles.\n\nASSESSMENT:\n1. Lumbar radiculopathy left (M54.41)\n2. Disc herniation L4-L5 (M51.16)\n\nPLAN: MRI lumbar, PT 2-3x/wk, Meloxicam 15mg. No lift >15lbs. F/U 4wks.",
                'suggestedCPT' => [
                    ['code' => '99215', 'desc' => 'Office Visit Lvl 5'],
                ],
                'suggestedDX' => [
                    ['code' => 'M54.41', 'desc' => 'Lumbago w/ sciatica left'],
                    ['code' => 'M51.16', 'desc' => 'IVD disorder L4-L5'],
                ],
                'modifiers' => [
                    ['code' => '-25', 'desc' => 'Significant E/M', 'appliedTo' => '99214', 'stateRule' => 'E/M w/ procedure'],
                ],
                'stateCompliance' => [
                    'compliant' => true, 'system' => 'CA OMFS', 'note' => 'RVU-based per LC §5307.1', 'violations' => [],
                ],
                'emails' => [
                    ['to' => 'sarah@valleymed.com', 'status' => 'Delivered'],
                ],
                'audit' => [
                    ['action' => 'Created',        'detail' => 'Initiated', 'ts' => '2026-02-15'],
                    ['action' => 'AI Processing',  'detail' => 'Complete',  'ts' => '2026-02-15'],
                    ['action' => 'Delivered',      'detail' => 'Sent',      'ts' => '2026-02-18'],
                ],
                'timeline' => [
                    ['label' => 'Created',   'date' => '2026-02-15'],
                    ['label' => 'Delivered', 'date' => '2026-02-18'],
                ],
                'deliveredAt' => '2026-02-18 10:00:00',
                'aiCompletedAt' => '2026-02-15 11:30:00',
            ],
            [
                'num' => 'SNP-0002',
                'patient' => 'Angela Thompson',
                'patientJsx' => 'p2',
                'dob' => '1985-07-22',
                'dos' => '2026-02-28',
                'gender' => 'Female',
                'claimNum' => 'WC-2026-55892',
                'emrId' => 'EMR-10078',
                'status' => CaseStatus::Completed->value,
                'svcs' => ['s5', 's8'],
                'total' => 800.00,
                'clinicJsx' => 'c1',
                'clinicName' => 'Valley Medical Group',
                'providerJsx' => 'p1',
                'state' => 'CA',
                'reportType' => ReportType::Mmi->value,
                'inputSource' => InputSource::Dictation->value,
                'notes' => "TREATMENT SUMMARY:\nAngela Thompson, 40yo RN. ACL tear right knee from slip 2025-06-15. Reconstruction 2025-08-12. 48 PT sessions.\n\nMMI DETERMINATION:\nP&S as of 2026-02-28.\n\nRESTRICTIONS: No lift >25lbs, no stand >30min, no running/jumping.\n\nWPI: 7% per AMA Guides 5th Ed.",
                'suggestedCPT' => [
                    ['code' => '99214', 'desc' => 'Office Visit Lvl 4'],
                    ['code' => '99455', 'desc' => 'WC Exam'],
                ],
                'suggestedDX' => [
                    ['code' => 'S83.511D', 'desc' => 'ACL sprain right subsequent'],
                    ['code' => 'M17.11',   'desc' => 'OA right knee'],
                ],
                'modifiers' => [],
                'stateCompliance' => [
                    'compliant' => true, 'system' => 'CA OMFS', 'note' => 'Compliant', 'violations' => [],
                ],
                'emails' => [
                    ['to' => 'sarah@valleymed.com', 'status' => 'Delivered'],
                ],
                'audit' => [
                    ['action' => 'Created',   'detail' => 'Initiated', 'ts' => '2026-02-28'],
                    ['action' => 'Completed', 'detail' => 'Done',      'ts' => '2026-03-01'],
                ],
                'timeline' => [
                    ['label' => 'Created',   'date' => '2026-02-28'],
                    ['label' => 'Completed', 'date' => '2026-03-01'],
                ],
                'deliveredAt' => null,
                'aiCompletedAt' => '2026-02-28 15:00:00',
            ],
            [
                'num' => 'SNP-0003',
                'patient' => 'David Kim',
                'patientJsx' => 'p3',
                'dob' => '1990-11-05',
                'dos' => '2026-03-18',
                'gender' => 'Male',
                'claimNum' => null,
                'emrId' => 'EMR-20015',
                'status' => CaseStatus::PendingDiagnosisApproval->value,
                'svcs' => ['s10', 's12'],
                'total' => 1350.00,
                'clinicJsx' => 'c2',
                'clinicName' => 'Coastal Orthopedics',
                'providerJsx' => 'p4',
                'state' => 'TX',
                'reportType' => ReportType::Qme->value,
                'inputSource' => InputSource::Dictation->value,
                'notes' => "QME EVALUATION:\nDavid Kim, 35yo software engineer. Cervical injury 2025-09-03 lifting 80lb server.\n\nRECORDS: ER, MRI (C5-C6 protrusion), PTP reports, EMG (C6 radiculopathy).\n\nEXAM: +Spurling right. Motor 4+/5 right wrist ext. Decreased C6 sensation.\n\nCAUSATION: Industrial, >50% probability. 100% apportioned.\nMMI not reached. WPI deferred.",
                'suggestedCPT' => [
                    ['code' => '99456', 'desc' => 'WC Exam (other)'],
                ],
                'suggestedDX' => [
                    ['code' => 'S13.4XXA', 'desc' => 'Cervical sprain'],
                    ['code' => 'M50.120',  'desc' => 'Cervical disc disorder'],
                ],
                'modifiers' => [],
                'stateCompliance' => [
                    'compliant' => true, 'system' => 'TX TDI', 'note' => 'Compliant', 'violations' => [],
                ],
                'emails' => [],
                'audit' => [
                    ['action' => 'Created',     'detail' => 'Initiated', 'ts' => '2026-03-18'],
                    ['action' => 'DX Mismatch', 'detail' => 'Flagged',   'ts' => '2026-03-18'],
                ],
                'timeline' => [
                    ['label' => 'Created', 'date' => '2026-03-18'],
                ],
                'deliveredAt' => null,
                'aiCompletedAt' => '2026-03-18 14:20:00',
            ],
        ];

        foreach ($cases as $c) {
            $attrs = [
                'clinic' => $this->clinicIds[$c['clinicJsx']],
                'clinicName' => $c['clinicName'],
                'patientId' => $this->patientIds[$c['patientJsx']],
                'providerId' => $this->providerIds[$c['providerJsx']],
                'patient' => $c['patient'],
                'dob' => $c['dob'],
                'dos' => $c['dos'],
                'gender' => $c['gender'],
                'claimNum' => $c['claimNum'],
                'emrId' => $c['emrId'],
                'state' => $c['state'],
                'status' => $c['status'],
                'reportType' => $c['reportType'],
                'inputSource' => $c['inputSource'],
                'svcs' => $c['svcs'],
                'total' => $c['total'],
                'notes' => $c['notes'],
                'suggestedCPT' => $c['suggestedCPT'],
                'suggestedDX' => $c['suggestedDX'],
                'modifiers' => $c['modifiers'],
                'stateCompliance' => $c['stateCompliance'],
                'emails' => $c['emails'],
                'audit' => $c['audit'],
                'timeline' => $c['timeline'],
                'deliveredAt' => $c['deliveredAt'],
                'aiCompletedAt' => $c['aiCompletedAt'],
            ];

            MedicalCase::withoutGlobalScopes()->updateOrCreate(
                ['num' => $c['num']],
                $attrs,
            );
        }
    }
}
