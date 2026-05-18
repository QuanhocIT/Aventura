<?php

use App\Models\Employee;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('employees:migrate-job-title-to-role {--create-missing : Create role when not found}', function () {
    $createMissing = (bool) $this->option('create-missing');

    $updated = 0;
    $skipped = 0;

    Employee::query()
        ->whereNull('role_id')
        ->whereNotNull('job_title')
        ->orderBy('id')
        ->chunkById(200, function ($employees) use (&$updated, &$skipped, $createMissing) {
            foreach ($employees as $employee) {
                $jobTitle = trim((string) $employee->job_title);

                if ($jobTitle === '') {
                    $skipped++;
                    continue;
                }

                $role = Role::query()
                    ->where('guard_name', 'web')
                    ->whereRaw('LOWER(name) = ?', [Str::lower($jobTitle)])
                    ->first();

                if (! $role && $createMissing) {
                    $role = Role::query()->create([
                        'name' => $jobTitle,
                        'guard_name' => 'web',
                    ]);
                }

                if (! $role) {
                    $skipped++;
                    continue;
                }

                $employee->role_id = $role->id;
                $employee->save();
                $updated++;
            }
        });

    $this->info("Mapped role_id for {$updated} employees.");

    if ($skipped > 0) {
        $this->warn("Skipped {$skipped} employees (empty job_title or role not found).");
    }
})->purpose('Map employees.job_title to employees.role_id using roles table');

Artisan::command('employees:sync-model-has-roles', function () {
    $synced = 0;

    Employee::query()
        ->orderBy('id')
        ->chunkById(200, function ($employees) use (&$synced) {
            foreach ($employees as $employee) {
                if ($employee->role_id) {
                    $employee->syncRoles([$employee->role_id]);
                } else {
                    $employee->syncRoles([]);
                }

                $synced++;
            }
        });

    $this->info("Synced model_has_roles for {$synced} employees.");
})->purpose('Sync employees.role_id to Spatie model_has_roles table');
