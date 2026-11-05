<?php

namespace Tests\Unit;

use App\Models\ApplyOrg;
use App\Models\ProcessedDocument;
use App\ViewModels\CurrentDocumentAnalysisPresenter;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/** **Validates: Requirements 2.7, 4.4, 4.5, 4.7–4.10, 5.5, 6.1, 6.3, 6.4, 6.12, 6.13, 8.6, 9.1, 9.4, 9.5** */
final class CurrentDocumentAnalysisPresenterTest extends TestCase
{
    public function test_it_presents_every_current_membership_file_and_ignores_stale_sources(): void
    {
        $application = (new ApplyOrg)->forceFill([
            'id' => 41,
            'list_of_members' => json_encode([
                $this->source('members/a.pdf', 'Members A.pdf', 'member-a', 'source-a'),
                $this->source('members/b.pdf', 'Members B.pdf', 'member-b', 'source-b'),
            ], JSON_THROW_ON_ERROR),
        ]);
        $application->setRelation('processedDocuments', new Collection([
            $this->analysis($application, 'list_of_members', 'member-a', 'members/a.pdf', 'source-a', ProcessedDocument::STATUS_PENDING, [
                'original_filename' => 'Members A.pdf',
            ]),
            $this->analysis($application, 'list_of_members', 'member-b', 'members/b.pdf', 'source-b', ProcessedDocument::STATUS_FAILED, [
                'original_filename' => 'Members B.pdf',
            ]),
            $this->analysis($application, 'list_of_members', 'stale-slot', 'members/old.pdf', 'old-source', ProcessedDocument::STATUS_COMPLETED),
        ]));

        $documents = app(CurrentDocumentAnalysisPresenter::class)->forApplication($application);

        self::assertCount(2, $documents);
        self::assertSame(['member-a', 'member-b'], array_column($documents, 'document_slot'));
        self::assertSame(['Members A.pdf', 'Members B.pdf'], array_column($documents, 'original_filename'));
        self::assertSame('Document processing failed safely.', $documents[1]['failure_text']);
        self::assertTrue($documents[1]['allowed_actions']['retry']);
        self::assertFalse($documents[1]['allowed_actions']['view_summary']);
    }

    public function test_it_derives_completed_output_refresh_metadata_and_actions_only_from_current_persisted_output(): void
    {
        $application = (new ApplyOrg)->forceFill([
            'id' => 42,
            'letter_of_application' => $this->source('letters/current.pdf', 'Current Letter.pdf', 'primary', 'source-current'),
        ]);
        $completed = $this->analysis(
            $application,
            'letter_of_application',
            'primary',
            'letters/current.pdf',
            'source-current',
            ProcessedDocument::STATUS_COMPLETED,
            [
                'output_source_version' => 'source-current',
                'summary' => 'Persisted summary.',
                'key_information' => ['school_year' => '2026-2027'],
                'document_type' => 'application_letter',
                'document_type_name' => 'Application Letter',
                'tags' => ['application letter'],
                'processed_at' => now(),
                'processing_duration_ms' => 250,
                'summary_version' => 2,
                'summary_processed_at' => now(),
                'resummarization_status' => 'processing',
                'last_resummarization_changed' => true,
            ],
        );
        $application->setRelation('processedDocuments', new Collection([$completed]));

        $document = app(CurrentDocumentAnalysisPresenter::class)->forApplication($application)[0];

        self::assertTrue($document['output_available']);
        self::assertSame('Persisted summary.', $document['advisory_output']['summary']);
        self::assertSame(CurrentDocumentAnalysisPresenter::ADVISORY, $document['details']['advisory']);
        self::assertSame('Application Letter', $document['details']['detected_document_type']);
        self::assertTrue($document['summary_refresh']['in_progress']);
        self::assertTrue($document['allowed_actions']['view_summary']);
        self::assertTrue($document['allowed_actions']['view_details']);
        self::assertFalse($document['allowed_actions']['resummarize']);
        self::assertFalse($document['allowed_actions']['retry']);
    }

    public function test_it_derives_exact_persisted_actions_and_refresh_results_for_all_presented_states(): void
    {
        $application = (new ApplyOrg)->forceFill([
            'id' => 43,
            'letter_of_application' => $this->source('letters/pending.pdf', 'Pending Letter.pdf', 'primary', 'pending-source'),
            'constitution_bylaws' => $this->source('bylaws/processing.pdf', 'Processing By-Laws.pdf', 'primary', 'processing-source'),
            'adviser_commitment' => $this->source('advisers/completed.pdf', 'Completed Adviser Form.pdf', 'primary', 'completed-source'),
            'application_form' => $this->source('forms/refreshing.pdf', 'Refreshing Form.pdf', 'primary', 'refreshing-source'),
            'certification_of_officers' => $this->source('officers/refresh-failed.pdf', 'Refresh Failed.pdf', 'primary', 'refresh-failed-source'),
            'plan_of_activities' => $this->source('plans/changed.pdf', 'Changed Plan.pdf', 'primary', 'changed-source'),
            'financial_report' => $this->source('reports/unchanged.pdf', 'Unchanged Report.pdf', 'primary', 'unchanged-source'),
            'list_of_members' => json_encode([
                $this->source('members/failed.pdf', 'Unreadable Members.pdf', 'member-failed', 'failed-source'),
                $this->source('members/second.pdf', 'Second Members.pdf', 'member-second', 'second-source'),
            ], JSON_THROW_ON_ERROR),
        ]);
        $application->setRelation('processedDocuments', new Collection([
            $this->analysis($application, 'letter_of_application', 'primary', 'letters/pending.pdf', 'pending-source', ProcessedDocument::STATUS_PENDING),
            $this->analysis($application, 'constitution_bylaws', 'primary', 'bylaws/processing.pdf', 'processing-source', ProcessedDocument::STATUS_PROCESSING),
            $this->analysis($application, 'adviser_commitment', 'primary', 'advisers/completed.pdf', 'completed-source', ProcessedDocument::STATUS_COMPLETED, $this->completedOutput('completed-source')),
            $this->analysis($application, 'application_form', 'primary', 'forms/refreshing.pdf', 'refreshing-source', ProcessedDocument::STATUS_COMPLETED, $this->completedOutput('refreshing-source', [
                'resummarization_status' => 'processing',
            ])),
            $this->analysis($application, 'certification_of_officers', 'primary', 'officers/refresh-failed.pdf', 'refresh-failed-source', ProcessedDocument::STATUS_COMPLETED, $this->completedOutput('refresh-failed-source', [
                'resummarization_status' => 'failed',
                'resummarization_error_message' => 'The summary service is temporarily unavailable. Please try again.',
            ])),
            $this->analysis($application, 'plan_of_activities', 'primary', 'plans/changed.pdf', 'changed-source', ProcessedDocument::STATUS_COMPLETED, $this->completedOutput('changed-source', [
                'last_resummarization_changed' => true,
            ])),
            $this->analysis($application, 'financial_report', 'primary', 'reports/unchanged.pdf', 'unchanged-source', ProcessedDocument::STATUS_COMPLETED, $this->completedOutput('unchanged-source', [
                'last_resummarization_changed' => false,
            ])),
            $this->analysis($application, 'list_of_members', 'member-failed', 'members/failed.pdf', 'failed-source', ProcessedDocument::STATUS_FAILED, [
                'original_filename' => 'Unreadable Members.pdf',
                'error_message' => 'The document could not be read. Please upload a readable file or retry.',
            ]),
            $this->analysis($application, 'list_of_members', 'member-second', 'members/second.pdf', 'second-source', ProcessedDocument::STATUS_COMPLETED, $this->completedOutput('second-source', [
                'original_filename' => 'Second Members.pdf',
            ])),
        ]));

        $documents = collect(app(CurrentDocumentAnalysisPresenter::class)->forApplication($application))
            ->keyBy(fn (array $document): string => "{$document['document_field']}:{$document['document_slot']}");

        foreach (['letter_of_application:primary', 'constitution_bylaws:primary'] as $key) {
            self::assertTrue($documents[$key]['allowed_actions']['view_document']);
            self::assertFalse($documents[$key]['allowed_actions']['view_summary']);
            self::assertFalse($documents[$key]['allowed_actions']['view_details']);
            self::assertFalse($documents[$key]['allowed_actions']['retry']);
            self::assertFalse($documents[$key]['allowed_actions']['resummarize']);
        }

        $failed = $documents['list_of_members:member-failed'];
        self::assertSame('The document could not be read. Please upload a readable file or retry.', $failed['failure_text']);
        self::assertTrue($failed['allowed_actions']['retry']);
        self::assertFalse($failed['allowed_actions']['view_summary']);
        self::assertFalse($failed['allowed_actions']['view_details']);
        self::assertFalse($failed['allowed_actions']['resummarize']);

        foreach ([
            'adviser_commitment:primary',
            'application_form:primary',
            'certification_of_officers:primary',
            'plan_of_activities:primary',
            'financial_report:primary',
            'list_of_members:member-second',
        ] as $key) {
            self::assertTrue($documents[$key]['output_available']);
            self::assertTrue($documents[$key]['allowed_actions']['view_summary']);
            self::assertTrue($documents[$key]['allowed_actions']['view_details']);
            self::assertFalse($documents[$key]['allowed_actions']['retry']);
        }

        self::assertFalse($documents['application_form:primary']['allowed_actions']['resummarize']);
        self::assertTrue($documents['application_form:primary']['summary_refresh']['in_progress']);
        self::assertTrue($documents['certification_of_officers:primary']['allowed_actions']['resummarize']);
        self::assertSame('The summary service is temporarily unavailable. Please try again.', $documents['certification_of_officers:primary']['summary_refresh']['failure_text']);
        self::assertTrue($documents['plan_of_activities:primary']['summary_refresh']['last_result_changed']);
        self::assertFalse($documents['financial_report:primary']['summary_refresh']['last_result_changed']);
        self::assertSame('Second Members.pdf', $documents['list_of_members:member-second']['details']['original_filename']);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function completedOutput(string $sourceVersion, array $overrides = []): array
    {
        return array_replace([
            'output_source_version' => $sourceVersion,
            'summary' => 'Persisted reviewer-facing summary.',
            'key_information' => ['school_year' => '2026-2027'],
            'document_type' => 'application_letter',
            'document_type_name' => 'Application Letter',
            'tags' => ['application letter', 'review'],
            'processed_at' => now(),
            'processing_duration_ms' => 321,
            'summary_version' => 2,
            'summary_processed_at' => now(),
            'resummarization_status' => 'idle',
        ], $overrides);
    }

    /** @return array<string, string> */
    private function source(string $path, string $name, string $slot, string $version): array
    {
        return [
            'path' => $path,
            'name' => $name,
            'slot' => $slot,
            'storage_disk' => 'presenter-test',
            'source_version' => $version,
            'source_fingerprint' => hash('sha256', $version),
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function analysis(
        ApplyOrg $application,
        string $field,
        string $slot,
        string $path,
        string $version,
        string $status,
        array $overrides = [],
    ): ProcessedDocument {
        return (new ProcessedDocument)->forceFill(array_replace([
            'id' => random_int(100, 99999),
            'apply_org_id' => $application->id,
            'document_field' => $field,
            'document_slot' => $slot,
            'original_filename' => basename($path),
            'file_path' => $path,
            'storage_disk' => 'presenter-test',
            'source_version' => $version,
            'source_fingerprint' => hash('sha256', $version),
            'association_ready_at' => now(),
            'processing_status' => $status,
            'error_message' => $status === ProcessedDocument::STATUS_FAILED
                ? 'Document processing failed safely.'
                : null,
            'resummarization_status' => 'idle',
            'summary_version' => 0,
        ], $overrides));
    }
}
