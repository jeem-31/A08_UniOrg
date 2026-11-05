<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Faq;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Organizations First
        $this->call(OrganizationSeeder::class);

        // Get the first organization (SSC) for seeding data
        $defaultOrgId = \DB::table('organizations')->where('name', 'Supreme Student Council (SSC)')->value('id');

        // Create Super Admin
        $superAdmin = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@soccsync.com',
            'password' => Hash::make(\Illuminate\Support\Str::random(40)),
            'role' => 'super_admin',
            'student_id' => 'SA-2024-001',
            'is_active' => true,
        ]);

        // Create SOCCS Admin
        $admin = User::create([
            'first_name' => 'SOCCS',
            'last_name' => 'Admin',
            'email' => 'admin@soccsync.com',
            'password' => Hash::make(\Illuminate\Support\Str::random(40)),
            'role' => 'admin',
            'student_id' => 'AD-2024-001',
            'is_active' => true,
        ]);

        // Create Sample Student
        $student = User::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'student@soccsync.com',
            'password' => Hash::make(\Illuminate\Support\Str::random(40)),
            'role' => 'student',
            'student_id' => '2024-00001',
            'course' => 'BSCS',
            'year_level' => '3rd Year',
            'section' => 'A',
            'is_active' => true,
        ]);

        // Create more sample students
        for ($i = 2; $i <= 10; $i++) {
            User::create([
                'first_name' => 'Student',
                'last_name' => $i,
                'email' => 'student' . $i . '@soccsync.com',
                'password' => Hash::make(\Illuminate\Support\Str::random(40)),
                'role' => 'student',
                'student_id' => '2024-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'course' => ['BSCS', 'BSIT', 'ACT'][array_rand(['BSCS', 'BSIT', 'ACT'])],
                'year_level' => ['1st Year', '2nd Year', '3rd Year', '4th Year'][array_rand(['1st Year', '2nd Year', '3rd Year', '4th Year'])],
                'section' => ['A', 'B', 'C'][array_rand(['A', 'B', 'C'])],
                'is_active' => true,
            ]);
        }

        // Create Sample Announcements
        Announcement::create([
            'organization_id' => $defaultOrgId,
            'user_id' => $admin->id,
            'title' => 'Welcome to SOCCSync!',
            'content' => 'We are excited to announce the launch of SOCCSync, the official student organization management system for the College of Computer Studies. This platform will help streamline our operations and improve communication with all CCS students.',
            'type' => 'general',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Announcement::create([
            'organization_id' => $defaultOrgId,
            'user_id' => $admin->id,
            'title' => 'SOCCS Membership Drive 2024',
            'content' => 'Join the Student Organization of the College of Computer Studies! Membership is now open for all CCS students. Benefits include access to exclusive events, workshops, and networking opportunities. Visit the Payments section to pay your membership fee.',
            'type' => 'general',
            'is_published' => true,
            'published_at' => now(),
        ]);

        Announcement::create([
            'organization_id' => $defaultOrgId,
            'user_id' => $admin->id,
            'title' => 'Urgent: Deadline for Document Submission',
            'content' => 'All students are reminded to submit their required documents (medical certificate, parent consent form) by the end of this week. Late submissions will not be accepted. Please upload your documents through the My Documents section.',
            'type' => 'urgent',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Create Sample Events
        Event::create([
            'organization_id' => $defaultOrgId,
            'user_id' => $admin->id,
            'title' => 'CCS Tech Summit 2024',
            'description' => 'Join us for the annual CCS Tech Summit featuring industry speakers, workshops, and networking sessions. This is a great opportunity to learn about the latest trends in technology and connect with professionals in the field.',
            'start_date' => now()->addDays(15)->setTime(9, 0),
            'end_date' => now()->addDays(15)->setTime(17, 0),
            'venue' => 'LSPU Auditorium',
            'category' => 'general_on',
            'is_published' => true,
        ]);

        Event::create([
            'organization_id' => $defaultOrgId,
            'user_id' => $admin->id,
            'title' => 'Programming Competition',
            'description' => 'Test your coding skills in our annual programming competition! Prizes await the winners. Open to all CCS students. Register now through the SOCCS office.',
            'start_date' => now()->addDays(30)->setTime(8, 0),
            'end_date' => now()->addDays(30)->setTime(18, 0),
            'venue' => 'CCS Computer Laboratory',
            'category' => 'general_on',
            'is_published' => true,
        ]);

        Event::create([
            'organization_id' => $defaultOrgId,
            'user_id' => $admin->id,
            'title' => 'Educational Tour to Tech Companies',
            'description' => 'Experience the industry firsthand! Join our educational tour to leading tech companies in Metro Manila. Limited slots available. Tour fee applies.',
            'start_date' => now()->addDays(45)->setTime(6, 0),
            'end_date' => now()->addDays(45)->setTime(20, 0),
            'venue' => 'Metro Manila',
            'category' => 'general_off',
            'is_published' => true,
        ]);

        // Create Sample FAQs
        Faq::create([
            'question' => 'How do I become a SOCCS member?',
            'answer' => 'To become a SOCCS member, you need to: 1) Complete your student profile, 2) Pay the membership fee through the Payments section, 3) Wait for verification from SOCCS officers. Once verified, you will have full access to all member benefits.',
            'category' => 'Membership',
            'keywords' => ['membership', 'join', 'member', 'how to join'],
            'is_published' => true,
        ]);

        Faq::create([
            'question' => 'How do I pay for events and fees?',
            'answer' => 'You can pay through the Payments section. We accept GCash payments (online) or manual payments. For GCash, enter your reference number after payment. For manual payments, visit the SOCCS office during office hours. All payments require verification from SOCCS officers.',
            'category' => 'Payments',
            'keywords' => ['payment', 'pay', 'gcash', 'fee', 'how to pay'],
            'is_published' => true,
        ]);

        Faq::create([
            'question' => 'Where can I find study materials?',
            'answer' => 'Study materials, PDFs, presentations, and other learning resources are available in the e-Library section. You can browse by category or course, and download materials for free. New resources are added regularly.',
            'category' => 'e-Library',
            'keywords' => ['library', 'study', 'materials', 'download', 'resources'],
            'is_published' => true,
        ]);

        Faq::create([
            'question' => 'How do I upload my projects to EduShare?',
            'answer' => 'Go to the EduShare section and click "Upload". Fill in the details about your project (title, description, category) and upload your file. You can choose to make it public or private. Public projects can be viewed and downloaded by other students.',
            'category' => 'EduShare',
            'keywords' => ['edushare', 'upload', 'project', 'share', 'code'],
            'is_published' => true,
        ]);

        Faq::create([
            'question' => 'How do I vote in SOCCS elections?',
            'answer' => 'When elections are active, go to the Elections section. You will see the list of candidates and their platforms. Click on your preferred candidate and submit your vote. You can only vote once per election. Results will be published after the election period ends.',
            'category' => 'Elections',
            'keywords' => ['vote', 'election', 'voting', 'candidate'],
            'is_published' => true,
        ]);

        Faq::create([
            'question' => 'What documents do I need to submit?',
            'answer' => 'Required documents include: Medical Certificate, Parent Consent Form, and Tour Requirements (if applicable). Upload these documents in the My Documents section. Make sure they are valid and not expired. Documents will be verified by SOCCS officers.',
            'category' => 'Documents',
            'keywords' => ['document', 'requirements', 'medical', 'consent', 'upload'],
            'is_published' => true,
        ]);

        Faq::create([
            'question' => 'How can I contact SOCCS officers?',
            'answer' => 'You can contact SOCCS officers through: 1) Email: soccs@lspu.edu.ph, 2) Phone: (049) 123-4567, 3) Visit the SOCCS office during office hours (Mon-Fri, 8AM-5PM), 4) Submit a message through the Complaints & Suggestions section.',
            'category' => 'Contact',
            'keywords' => ['contact', 'email', 'phone', 'office', 'reach'],
            'is_published' => true,
        ]);

        Faq::create([
            'question' => 'Can I submit complaints or suggestions anonymously?',
            'answer' => 'Yes! When submitting through the Complaints & Suggestions section, you have the option to submit anonymously. Simply check the "Submit Anonymously" box. Anonymous submissions are still reviewed by SOCCS officers, but your identity will not be revealed.',
            'category' => 'Complaints',
            'keywords' => ['complaint', 'suggestion', 'anonymous', 'feedback'],
            'is_published' => true,
        ]);
    }
}
