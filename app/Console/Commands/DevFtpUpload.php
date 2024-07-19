<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DevFtpUpload extends Command
{
    protected $signature = 'devftp:upload 
                            {--file= : The path of the file to upload}';

    protected $description = 'Upload a specified file to the FTP server';

    private $logPath;

    public function __construct()
    {
        parent::__construct();
        $this->logPath = storage_path('app/devftp/upload.log');
    }

    public function handle()
    {
        $filePath = $this->option('file');

        // Check if the file option is provided
        if (empty($filePath)) {
            $this->error('File path is required.');
            $this->logError('File path is required.');
            return 1;
        }

        // Check if the file exists
        if (!File::exists($filePath)) {
            $this->error('File does not exist: ' . $filePath);
            $this->logError('File does not exist: ' . $filePath);
            return 1;
        }

        // Attempt to upload the file to the FTP server
        if ($this->uploadToFtp($filePath)) {
            $this->info('File uploaded to FTP successfully.');
            $this->logInfo('File uploaded to FTP successfully.');
        } else {
            $this->error('Failed to upload file to FTP.');
            $this->logError('Failed to upload file to FTP.');
            return 1;
        }

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

    private function uploadToFtp($filePath)
    {
        if (!function_exists('ftp_connect')) {
            $this->logError('FTP functions are not available. Ensure the FTP extension is enabled.');
            return false;
        }

        $ftpConfig = config('devftp.ftp');
        
        // Check if FTP configuration is valid
        if (empty($ftpConfig['host']) || empty($ftpConfig['username']) || empty($ftpConfig['password']) || empty($ftpConfig['root'])) {
            $this->logError('Incomplete FTP configuration.');
            return false;
        }

        // Establish FTP connection with error handling
        try {
            $ftpConnection = @ftp_connect($ftpConfig['host'], $ftpConfig['port']);
            if (!$ftpConnection) {
                $this->logError('Failed to connect to FTP server: ' . $ftpConfig['host']);
                return false;
            }
        } catch (\Exception $e) {
            $this->logError('Failed to connect to FTP server: ' . $ftpConfig['host'] . '. Error: ' . $e->getMessage());
            return false;
        }

        // Attempt to login to the FTP server
        $login = ftp_login($ftpConnection, $ftpConfig['username'], $ftpConfig['password']);
        if (!$login) {
            $this->logError('Failed to login to FTP server: ' . $ftpConfig['host']);
            ftp_close($ftpConnection);
            return false;
        }

        // Set FTP connection to passive mode
        ftp_pasv($ftpConnection, true);

        // Prepare upload path
        $uploadPath = rtrim($ftpConfig['root'], '/') . '/' . basename($filePath);

        // Attempt to upload the file
        $upload = ftp_put($ftpConnection, $uploadPath, $filePath, FTP_BINARY);
        ftp_close($ftpConnection);

        if (!$upload) {
            $this->logError('Failed to upload file to FTP: ' . $uploadPath);
            return false;
        }

        return true;
    }
}
