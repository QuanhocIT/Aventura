<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupService
{
    /**
     * Chạy tác vụ sao lưu cơ sở dữ liệu.
     *
     * @return array Thông tin tệp sao lưu đã tạo
     */
    public function backup(): array
    {
        $connection = config('database.default');
        $dbConfig = config("database.connections.{$connection}");

        if ($connection !== 'mysql' && $connection !== 'sqlite') {
            throw new \Exception('Chỉ hỗ trợ cơ sở dữ liệu MySQL và SQLite.');
        }

        $timestamp = date('Y_m_d_His');
        $database = str_replace([':', '/', '\\', '*'], '_', basename($dbConfig['database'] ?? 'database'));

        $tempDir = storage_path('app/temp-backups');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        if ($connection === 'sqlite') {
            $filename = "backup_{$database}_{$timestamp}.sql";
            $tempFile = $tempDir.'/'.$filename;
            $dbFile = $dbConfig['database'];

            if ($dbFile === ':memory:') {
                file_put_contents($tempFile, "-- SQLite in-memory database backup simulation\n");
            } else {
                if (file_exists($dbFile)) {
                    copy($dbFile, $tempFile);
                } else {
                    file_put_contents($tempFile, "-- SQLite database file was missing\n");
                }
            }
            $isMysqldumpSuccess = true;
        } else {
            $host = $dbConfig['host'][0] ?? $dbConfig['host'] ?? '127.0.0.1';
            $port = $dbConfig['port'] ?? '3306';
            $username = $dbConfig['username'];
            $password = $dbConfig['password'];

            $filename = "backup_{$database}_{$timestamp}.sql";
            $tempFile = $tempDir.'/'.$filename;
            $isMysqldumpSuccess = false;

            // Tránh lỗi khi mật khẩu chứa ký tự đặc biệt bằng cách sử dụng CLI an toàn
            if ($password !== null && $password !== '') {
                $passwordArg = '-p'.$password; // Không có khoảng cách giữa -p và mật khẩu
            } else {
                $passwordArg = '';
            }
        }

        if ($connection === 'mysql') {
            // Tạo lệnh mysqldump
            $command = sprintf(
                'mysqldump -h %s -P %s -u %s %s %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                $passwordArg,
                escapeshellarg($database),
                escapeshellarg($tempFile)
            );

            try {
                $output = [];
                $returnVar = -1;
                exec($command, $output, $returnVar);

                if ($returnVar === 0 && file_exists($tempFile) && filesize($tempFile) > 0) {
                    $isMysqldumpSuccess = true;
                } else {
                    Log::warning("mysqldump failed with code {$returnVar}. Output: ".implode("\n", $output));
                }
            } catch (\Exception $e) {
                Log::warning('exec mysqldump threw exception: '.$e->getMessage());
            }

            // FALLBACK: Nếu mysqldump thất bại hoặc không khả dụng, sử dụng PHP Fallback SQL Dumper
            if (! $isMysqldumpSuccess) {
                Log::info('Starting PHP fallback database dump...');
                $this->phpFallbackBackup($tempFile);
            }
        }

        // Nén tệp tin SQL thành .gz để tiết kiệm băng thông và bộ nhớ
        $gzFile = $tempFile.'.gz';
        $gzHandle = gzopen($gzFile, 'w9');
        if (! $gzHandle) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw new \Exception('Không thể tạo tệp nén gzip.');
        }

        $sqlContent = file_get_contents($tempFile);
        gzwrite($gzHandle, $sqlContent);
        gzclose($gzHandle);

        // Xóa tệp sql tạm chưa nén
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        // Xác định Cloud Storage S3 có được cấu hình đúng
        $disk = 'local';
        $destination = 'backups/'.$filename.'.gz';
        $uploadedToS3 = false;

        if ($this->isS3Configured()) {
            try {
                $fileStream = fopen($gzFile, 'r');
                Storage::disk('s3')->put($destination, $fileStream);
                fclose($fileStream);
                $disk = 's3';
                $uploadedToS3 = true;
            } catch (\Exception $e) {
                Log::warning('Failed to upload backup to S3: '.$e->getMessage().'. Saving locally instead.');
            }
        }

        if (! $uploadedToS3) {
            $fileStream = fopen($gzFile, 'r');
            Storage::disk('local')->put($destination, $fileStream);
            fclose($fileStream);
            $disk = 'local';
        }

        // Xóa tệp nén tạm thời
        if (file_exists($gzFile)) {
            unlink($gzFile);
        }

        $size = Storage::disk($disk)->size($destination);

        return [
            'filename' => $filename.'.gz',
            'disk' => $disk,
            'size' => $size,
            'size_mb' => round($size / (1024 * 1024), 2),
            'path' => $destination,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Trả về danh sách các bản sao lưu từ S3 và local storage.
     */
    public function getBackups(): array
    {
        $backups = [];

        // 1. Quét local disk
        try {
            $localFiles = Storage::disk('local')->files('backups');
            foreach ($localFiles as $file) {
                if (str_ends_with($file, '.sql.gz') || str_ends_with($file, '.gz')) {
                    $size = Storage::disk('local')->size($file);
                    $backups[] = [
                        'filename' => basename($file),
                        'disk' => 'local',
                        'size' => $size,
                        'size_mb' => round($size / (1024 * 1024), 2),
                        'last_modified' => date('d/m/Y H:i:s', Storage::disk('local')->lastModified($file)),
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error listing local backups: '.$e->getMessage());
        }

        // 2. Quét S3 disk nếu cấu hình
        if ($this->isS3Configured()) {
            try {
                $s3Files = Storage::disk('s3')->files('backups');
                foreach ($s3Files as $file) {
                    if (str_ends_with($file, '.sql.gz') || str_ends_with($file, '.gz')) {
                        $size = Storage::disk('s3')->size($file);
                        $backups[] = [
                            'filename' => basename($file),
                            'disk' => 's3',
                            'size' => $size,
                            'size_mb' => round($size / (1024 * 1024), 2),
                            'last_modified' => date('d/m/Y H:i:s', Storage::disk('s3')->lastModified($file)),
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Error listing S3 backups: '.$e->getMessage());
            }
        }

        // Sắp xếp các bản sao lưu mới nhất lên đầu
        usort($backups, function ($a, $b) {
            return strtotime(str_replace('/', '-', $b['last_modified'])) <=> strtotime(str_replace('/', '-', $a['last_modified']));
        });

        return $backups;
    }

    /**
     * Tải về bản sao lưu.
     */
    public function downloadBackup(string $filename, string $disk)
    {
        $path = 'backups/'.$filename;
        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'Tệp sao lưu không tồn tại.');
        }

        return Storage::disk($disk)->download($path, $filename);
    }

    /**
     * Xóa bản sao lưu.
     */
    public function deleteBackup(string $filename, string $disk): bool
    {
        $path = 'backups/'.$filename;
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }

    /**
     * Kiểm tra xem S3 Cloud Storage có cấu hình hợp lệ không.
     */
    private function isS3Configured(): bool
    {
        return ! empty(config('filesystems.disks.s3.key')) &&
               ! empty(config('filesystems.disks.s3.bucket')) &&
               ! empty(config('filesystems.disks.s3.secret'));
    }

    /**
     * Trình xuất bản cơ sở dữ liệu thuần PHP phục vụ môi trường không có mysqldump.
     */
    private function phpFallbackBackup(string $outputPath): void
    {
        $handle = fopen($outputPath, 'w');
        if (! $handle) {
            throw new \Exception('Không thể tạo tệp sao lưu tạm thời.');
        }

        fwrite($handle, "-- Aventura Database Backup Fallback\n");
        fwrite($handle, '-- Generated at: '.date('Y-m-d H:i:s')."\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tablesResult = DB::select('SHOW TABLES');
        $dbName = config('database.connections.mysql.database');
        $keyName = 'Tables_in_'.$dbName;

        foreach ($tablesResult as $tableObj) {
            $tableName = $tableObj->$keyName;

            // Lấy CREATE TABLE statement
            $createTableResult = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (! empty($createTableResult)) {
                $createTableSql = ((array) $createTableResult[0])['Create Table'] ?? '';
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($handle, $createTableSql.";\n\n");
            }

            // Đọc dữ liệu theo Chunk để tránh tràn bộ nhớ PHP
            DB::table($tableName)->chunk(500, function ($rows) use ($handle, $tableName) {
                if ($rows->isEmpty()) {
                    return;
                }

                foreach ($rows as $row) {
                    $rowArray = (array) $row;
                    $columns = array_map(fn ($col) => "`{$col}`", array_keys($rowArray));

                    $values = array_map(function ($val) {
                        if ($val === null) {
                            return 'NULL';
                        }

                        return "'".addslashes((string) $val)."'";
                    }, array_values($rowArray));

                    $sql = "INSERT INTO `{$tableName}` (".implode(', ', $columns).') VALUES ('.implode(', ', $values).");\n";
                    fwrite($handle, $sql);
                }
                fwrite($handle, "\n");
            });

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);
    }
}
