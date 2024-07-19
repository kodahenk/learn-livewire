<?php

namespace App\Console\Commands;

use File;
use ZipArchive;
use Illuminate\Console\Command;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Symfony\Component\Console\Helper\ProgressBar;
use Illuminate\Support\Facades\Log;

class DevFtpBackup extends Command
{
    protected $signature = 'devftp:backup 
                            {--exclude=* : Files or directories to exclude (comma-separated)}
                            {--compression=7 : Compression level (0-9, default: 7)}';

    protected $description = 'Backup project files excluding specified files or directories to a zip file in storage/app/devftp/backup';

    private $logPath;

    public function __construct()
    {
        parent::__construct();
        $this->logPath = storage_path('app/devftp/backup.log');
    }

    public function handle()
    {
        // Directory to backup
        $sourceDir = base_path();

        // Directory where backup will be stored
        $backupDir = storage_path('app/devftp/backup');

        // Ensure the backup directory exists
        if (!File::isDirectory($backupDir) && !File::makeDirectory($backupDir, 0755, true)) {
            $this->error('Failed to create backup directory.');
            $this->logError('Failed to create backup directory.');
            return 1;
        }

        // Name of the backup file
        $backupFile = $backupDir . DIRECTORY_SEPARATOR . now()->format('Y-m-d_H-i-s') . '.zip';

        // Get excluded paths from options
        $excludePaths = $this->option('exclude') ?? [];

        $excludePaths = array_map(function ($path) {
            return base_path($path);
        }, $excludePaths);

        $excludePaths[] = base_path('.git');
        $excludePaths[] = base_path('storage' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'devftp' . DIRECTORY_SEPARATOR . 'backup');

        // Get compression level
        $compressionLevel = (int) $this->option('compression');
        if ($compressionLevel < 0 || $compressionLevel > 9) {
            $this->error('Invalid compression level. It must be between 0 and 9.');
            $this->logError('Invalid compression level.');
            return 1;
        }

        // Create a ZipArchive instance
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error('Failed to create backup file: ' . $backupFile);
            $this->logError('Failed to create backup file: ' . $backupFile);
            return 1;
        }

        // Set compression level
        $zip->setCompressionIndex(0, $compressionLevel);

        // Create recursive directory iterator
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Count total items for progress bar
        $totalItems = iterator_count($files);
        $progressBar = new ProgressBar($this->output, $totalItems);
        $progressBar->start();

        // Start logging
        $this->logInfo('Backup started.');

        // Add files to zip archive
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            // Skip excluded files and directories
            $skipFile = false;
            foreach ($excludePaths as $excludePath) {
                if (strpos($filePath, $excludePath) === 0) {
                    $skipFile = true;
                    break;
                }
            }
            if ($skipFile) {
                continue;
            }

            // Add file or directory to zip
            if ($file->isDir()) {
                if ($zip->addEmptyDir($relativePath)) {
                    $this->logInfo('Added directory: ' . $relativePath);
                } else {
                    $this->logError('Failed to add directory: ' . $relativePath);
                }
            } elseif ($file->isFile()) {
                if ($zip->addFile($filePath, $relativePath)) {
                    $this->logInfo('Added file: ' . $relativePath);
                } else {
                    $this->logError('Failed to add file: ' . $relativePath);
                }
            }

            $progressBar->advance();
        }

        // Close and save archive
        if ($zip->close()) {
            $this->info(PHP_EOL . 'Backup created successfully: ' . $backupFile);
            $this->logInfo('Backup created successfully: ' . $backupFile);
        } else {
            $this->error('Failed to finalize the backup.');
            $this->logError('Failed to finalize the backup.');
        }

        $progressBar->finish();
        $this->logInfo('Backup process completed.');
        return 0;
    }

    private function logInfo($message)
    {
        File::append($this->logPath, '[' . now() . '] INFO: ' . $message . PHP_EOL);
    }

    private function logError($message)
    {
        File::append($this->logPath, '[' . now() . '] ERROR: ' . $message . PHP_EOL);
    }
}
