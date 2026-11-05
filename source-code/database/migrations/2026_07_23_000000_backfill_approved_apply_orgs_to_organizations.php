<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::table('apply_orgs')->where('status', 'Approved')->orderBy('id')->each(function (object $application): void {
                $organization = $application->organization_id
                    ? DB::table('organizations')->where('id', $application->organization_id)->first()
                    : null;

                if (! $organization) {
                    $lookup = DB::table('organizations')->where('name', $application->organization_name);
                    $application->organization_acronym === null
                        ? $lookup->whereNull('org_acronym')
                        : $lookup->where('org_acronym', $application->organization_acronym);
                    $organization = $lookup->first();
                }

                if (! $organization) {
                    $type = in_array($application->organization_type, ['Mother Org', 'Sub Org', 'Non-Academic'], true)
                        ? $application->organization_type
                        : ($application->organization_type === 'Non-Academic' ? 'Non-Academic' : 'Mother Org');
                    $organizationId = DB::table('organizations')->insertGetId([
                        'name' => $application->organization_name,
                        'org_acronym' => $application->organization_acronym,
                        'type' => $type,
                        'department_id' => $application->department_id,
                        'status' => 'Approved',
                        'has_system' => DB::raw('false'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $organizationId = $organization->id;
                    DB::table('organizations')->where('id', $organizationId)->update([
                        'status' => 'Approved', 'archived_at' => null, 'updated_at' => now(),
                    ]);
                }

                DB::table('apply_orgs')->where('id', $application->id)->update(['organization_id' => $organizationId]);
            });
        });
    }

    public function down(): void
    {
        // Preserve approved organization records and their links; deleting them could remove user-managed data.
    }
};
