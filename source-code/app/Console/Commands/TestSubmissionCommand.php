<?php

namespace App\Console\Commands;

use App\Models\SystemRequest;
use App\Models\Department;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TestSubmissionCommand extends Command
{
    protected $signature = 'app:test-submission';
    protected $description = 'Simulate a heavy 16-file submission with original filenames';

    public function handle()
    {
        $this->info("--- UniOrg Heavy Upload Test Script ---");

        // PRE-TEST: Check R2 Connection
        try {
            $this->comment("Testing R2 Connection...");
            $files = \Illuminate\Support\Facades\Storage::disk(storage_disk())->files();
            $this->info("✅ R2 Connection Successful! Found " . count($files) . " files.");
        } catch (\Exception $e) {
            $this->error("❌ R2 Connection Failed: " . $e->getMessage());
            return 1;
        }

        // 1. Define the list of member files
        $memberFilesPaths = [
            "C:\Users\justine\Documents\Application_testing\BSCS 2B_IRREGULAR.pdf",
            "C:\Users\justine\Documents\Application_testing\BSCS 2B_REGULAR.pdf",
            "C:\Users\justine\Documents\Application_testing\BSCS 3a.pdf",
            "C:\Users\justine\Documents\Application_testing\BSCS 3b.pdf",
            "C:\Users\justine\Documents\Application_testing\BSCS_1A.pdf",
            "C:\Users\justine\Documents\Application_testing\BSIT 1A.pdf",
            "C:\Users\justine\Documents\Application_testing\BSIT 1B.pdf",
            "C:\Users\justine\Documents\Application_testing\BSIT 2B_REGULAR.pdf",
            "C:\Users\justine\Documents\Application_testing\BSIT 2D_REGULAR.pdf",
            "C:\Users\justine\Documents\Application_testing\BSIT 4A.pdf",
            "C:\Users\justine\Documents\Application_testing\BSIT 4D.pdf",
            "C:\Users\justine\Documents\Application_testing\IT 3C.pdf",
            "C:\Users\justine\Documents\Application_testing\IT 3D.pdf",
            "C:\Users\justine\Documents\Application_testing\IT 3E.pdf",
            "C:\Users\justine\Documents\Application_testing\IT_3A_MEMBERSHIP.pdf",
            "C:\Users\justine\Documents\Application_testing\IT_3B MEMBERSHIP.pdf"
        ];

        // 2. Setup Dummy Data
        $dept = Department::where('department_name', 'LIKE', '%Computer Studies%')->first();
        if (!$dept) {
            $dept = Department::first();
        }

        $this->info("Using Department: {$dept->department_name} [ID: {$dept->id}]");

        $data = [
            'organization_name' => 'Department Dummy Academy',
            'organization_acronym' => 'DDA',
            'organization_type' => 'Academic',
            'department_id' => $dept->id,
            'requester_first_name' => 'Mark Justine',
            'requester_last_name' => 'Gahi',
            'requester_email' => 'demo.contact@example.invalid',
            'requester_position' => 'Organization President',
            'status' => 'Pending',
            'request_type' => 'apply',
            'application_type' => 'apply',
            'current_step' => 5
        ];

        // 3. Prepare Specific Real Files
        $specificFiles = [
            'application_form' => 'C:\Users\justine\Documents\Application_testing\01-APPLICATION-FOR-ORGANIZATION-RECOGNITION-OR-RENEWAL-OF-ACCREDITED-STUDENT-ORGANIZATION.pdf',
            'letter_of_application' => 'C:\Users\justine\Documents\Application_testing\02-ORGANIZATION-RENEWAL-FORM.pdf',
            'adviser_commitment' => 'C:\Users\justine\Documents\Application_testing\03-ORGANIZATION-ADVISER-COMMITMENT-FORM.pdf',
            'plan_of_activities' => 'C:\Users\justine\Documents\Application_testing\04-PLAN-OF-ACTIVITIES-1.pdf',
            'certification_dean' => 'C:\Users\justine\Documents\Application_testing\06-CERTIFICATION.pdf',
        ];

        // Prepare Dummy File (for the rest)
        $testFilePath = $memberFilesPaths[0];
        $dummyFile = new UploadedFile($testFilePath, basename($testFilePath), 'application/pdf', null, true);

        $documentFields = [
            'adviser_commitment',
            'application_form',
            'certification_of_officers',
            'plan_of_activities',
            'constitution_bylaws',
            'certification_dean',
            'financial_report',
            'letter_of_application',
        ];

        $this->info("\n🚀 Starting Heavy Upload of realistic files to Cloudflare R2...");

        // 4. Upload Emulation with Specific Files
        foreach ($documentFields as $field) {
            if (isset($specificFiles[$field]) && file_exists($specificFiles[$field])) {
                // Use realistic file
                $path = Storage::disk(storage_disk())->putFile('system-requests', new \Illuminate\Http\File($specificFiles[$field]));
                $this->comment("Uploading real file for {$field}: " . basename($specificFiles[$field]));
            } else {
                // Use dummy file
                $path = Storage::disk(storage_disk())->putFile('system-requests', $dummyFile);
                $this->comment("Uploading dummy file for {$field}");
            }
            $data[$field] = $path;
        }

        // 5. Upload Member Files with ORIGINAL NAMES
        $memberFilesData = [];
        foreach ($memberFilesPaths as $path) {
            $filename = basename($path);
            $this->comment("Uploading: $filename");
            $uploadedPath = Storage::disk(storage_disk())->putFile('system-requests', new \Illuminate\Http\File($path));
            
            $memberFilesData[] = [
                'path' => $uploadedPath,
                'name' => $filename // SAVE THE ORIGINAL NAME
            ];
        }
        $data['list_of_members'] = json_encode($memberFilesData);

        // 6. Save to Database
        $systemRequest = SystemRequest::create($data);

        // 7. Add Officers
        $systemRequest->officers()->create([
            'position' => 'Organization President',
            'student_id' => '2021-1234',
            'first_name' => 'Mark Justine',
            'last_name' => 'Gahi',
            'email' => 'demo.contact@example.invalid',
            'course' => 'BSCS',
            'year_level' => '3',
            'section' => 'A'
        ]);

        $this->info("\n--- RESPONSE ---");
        $this->info("Message: Your application has been submitted successfully!");
        $this->info("✅ HEAVY TEST SUCCESSFUL! Request ID: {$systemRequest->id}");
        $this->info("Check this URL: " . url("/osas/system-requests/{$systemRequest->id}"));
    }
}
