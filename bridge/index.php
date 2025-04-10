<?php

/**
 * LibSQL Database REST API Handler
 * 
 * Provides a RESTful API for managing LibSQL databases with authentication,
 * including endpoints for listing, archiving, and restoring databases.
 * Archiving is implemented by renaming databases with an "-archived" suffix.
 */
class LibSQLRestAPI
{
    /** @var string The directory path where all databases are stored */
    private string $databaseDir;

    /** @var string The suffix appended to archived database names */
    private string $archiveSuffix = '-archived';

    /** @var string The password used for API authentication */
    private string $authPassword;

    /**
     * Constructor
     * 
     * @param string $databaseDir The directory for databases
     * @param string $authPassword The password for API authentication
     */
    public function __construct(string $databaseDir, string $authPassword)
    {
        $this->databaseDir = $databaseDir;
        $this->authPassword = $authPassword;

        // Ensure required directories exist with proper permissions
        $this->ensureDirectoriesExist();
    }

    /**
     * Ensure all required directories exist with proper permissions
     */
    private function ensureDirectoriesExist(): void
    {
        if (!is_dir($this->databaseDir)) {
            // Suppress warnings and use return value for error checking
            if (!@mkdir($this->databaseDir, 0755, true)) {
                error_log("Failed to create directory: $this->databaseDir");
            }
        }

        // Verify directory is writable
        if (!is_writable($this->databaseDir)) {
            error_log("Directory not writable: $this->databaseDir");
        }
    }

    /**
     * Main API router - handles all incoming requests
     */
    public function handleRequest(): void
    {
        // Only handle requests in PHP's built-in server
        if (php_sapi_name() !== 'cli-server') {
            return;
        }

        $url = parse_url($_SERVER['REQUEST_URI']);
        $path = $url['path'];

        // Health check endpoint (no auth required)
        if ($path === '/health') {
            $this->handleHealthCheck();
            return;
        }

        // Authenticate all other API endpoints
        if (!$this->authenticate()) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }

        // Route to appropriate handler
        switch ($path) {
            case '/api/databases':
                $this->handleListDatabases();
                break;

            case '/api/database/archive':
                $this->handleArchiveDatabase();
                break;

            case '/api/database/restore':
                $this->handleRestoreDatabase();
                break;

            default:
                // Let the server handle non-API routes
                return;
        }
    }

    /**
     * Authenticate the request using HTTP Basic Auth
     * 
     * @return bool True if authenticated, false otherwise
     */
    private function authenticate(): bool
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $password = substr($auth, 6); // Remove "Basic " prefix

        return $password === $this->authPassword;
    }

    /**
     * Handle the health check endpoint
     */
    private function handleHealthCheck(): void
    {
        $this->sendResponse(200, ['status' => 'ok']);
    }

    /**
     * Handle the list databases endpoint
     * Lists both active and archived databases with their status
     */
    private function handleListDatabases(): void
    {
        if (!is_dir($this->databaseDir)) {
            $this->sendResponse(500, ['error' => 'Database directory not found']);
            return;
        }

        $directories = scandir($this->databaseDir);
        if ($directories === false) {
            $this->sendResponse(500, ['error' => 'Failed to read database directory']);
            return;
        }

        $directories = array_values(array_diff($directories, ['..', '.']));
        $databases = [];

        foreach ($directories as $dir) {
            $isArchived = $this->isArchived($dir);
            $databases[] = [
                'name' => $isArchived ? substr($dir, 0, -strlen($this->archiveSuffix)) : $dir,
                'status' => $isArchived ? 'archived' : 'active',
                'path' => $dir
            ];
        }

        $this->sendResponse(200, $databases);
    }

    /**
     * Check if a database name indicates it's archived
     * 
     * @param string $databaseName The database name to check
     * @return bool True if the database is archived
     */
    private function isArchived(string $databaseName): bool
    {
        return str_ends_with($databaseName, $this->archiveSuffix);
    }

    /**
     * Handle the archive database endpoint
     * Archives a database by renaming it with the archive suffix
     */
    private function handleArchiveDatabase(): void
    {
        // Validate request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method Not Allowed']);
            return;
        }

        // Get and validate input
        $databaseName = $this->getAndValidateDatabaseName();
        if ($databaseName === null) {
            return; // Error already sent in getAndValidateDatabaseName
        }

        // Path validation
        $sourcePath = $this->databaseDir . '/' . basename($databaseName);
        $archivedPath = $this->databaseDir . '/' . basename($databaseName) . $this->archiveSuffix;

        // Check if database exists
        if (!is_dir($sourcePath)) {
            $this->sendResponse(404, ['error' => 'Database not found']);
            return;
        }

        // Check if already archived
        if ($this->isArchived($databaseName)) {
            $this->sendResponse(400, ['error' => 'Database is already archived']);
            return;
        }

        // Check if archived version already exists
        if (is_dir($archivedPath)) {
            $this->sendResponse(409, ['error' => 'Archived version already exists']);
            return;
        }

        try {
            // Rename the directory to mark it as archived
            if (!@rename($sourcePath, $archivedPath)) {
                throw new Exception("Failed to rename database for archiving");
            }

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Database archived successfully',
                'path' => $archivedPath
            ]);
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Archive error: " . $e->getMessage());

            $this->sendResponse(500, [
                'error' => 'Archiving failed',
                'details' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle the restore database endpoint
     * Restores an archived database by removing the archive suffix
     */
    private function handleRestoreDatabase(): void
    {
        // Validate request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method Not Allowed']);
            return;
        }

        // Get and validate input
        $databaseName = $this->getAndValidateDatabaseName();
        if ($databaseName === null) {
            return; // Error already sent in getAndValidateDatabaseName
        }

        // For restoration, we need to check if the database name already has the archive suffix
        $isAlreadyArchived = $this->isArchived($databaseName);

        // If the user provides the base name (without suffix), append the suffix for checking
        $archivedName = $isAlreadyArchived ? $databaseName : $databaseName . $this->archiveSuffix;
        $baseName = $isAlreadyArchived ? substr($databaseName, 0, -strlen($this->archiveSuffix)) : $databaseName;

        $archivedPath = $this->databaseDir . '/' . basename($archivedName);
        $restoredPath = $this->databaseDir . '/' . basename($baseName);

        // Verify archived database exists
        if (!is_dir($archivedPath)) {
            $this->sendResponse(404, ['error' => 'Archived database not found']);
            return;
        }

        // Check if active version already exists
        if (is_dir($restoredPath)) {
            $this->sendResponse(409, ['error' => 'Active database already exists']);
            return;
        }

        try {
            // Rename the directory to restore it
            if (!@rename($archivedPath, $restoredPath)) {
                throw new Exception("Failed to rename database for restoration");
            }

            $this->sendResponse(200, [
                'success' => true,
                'message' => 'Database restored successfully',
                'path' => $restoredPath
            ]);
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Restore error: " . $e->getMessage());

            $this->sendResponse(500, [
                'error' => 'Restoration failed',
                'details' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get and validate database name from input
     * 
     * @return string|null The validated database name or null if invalid
     */
    private function getAndValidateDatabaseName(): ?string
    {
        // Attempt to get JSON input
        $inputJson = file_get_contents('php://input');
        $input = json_decode($inputJson, true);

        // Check if JSON parsing failed
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendResponse(400, [
                'error' => 'Invalid JSON input',
                'details' => json_last_error_msg()
            ]);
            return null;
        }

        $databaseName = $input['name'] ?? null;

        // Validate input
        if (!$databaseName) {
            $this->sendResponse(400, ['error' => 'Database name is required']);
            return null;
        }

        // Allow the archive suffix for restoration requests
        $pattern = '/^[a-zA-Z0-9_-]+(' . preg_quote($this->archiveSuffix, '/') . ')?$/';

        // Sanitize database name
        if (!preg_match($pattern, $databaseName)) {
            $this->sendResponse(400, ['error' => 'Invalid database name format']);
            return null;
        }

        return $databaseName;
    }

    /**
     * Send JSON response with appropriate status code
     * 
     * @param int $statusCode HTTP status code
     * @param mixed $data Response data to be JSON encoded
     */
    private function sendResponse(int $statusCode, $data): void
    {
        // Clear any previous output to avoid "headers already sent" warnings
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
        } else {
            // Log warning if headers were already sent
            error_log("Warning: Headers already sent when trying to send response with status $statusCode");
        }

        echo json_encode($data);
        exit;
    }
}

// Application entry point
if (php_sapi_name() === 'cli-server') {
    // Error handling setup
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);

    // Configuration
    $databaseDir = __DIR__ . '/data/libsql/data.sqld/dbs';
    $authPassword = $_ENV['BRIDGE_HTTP_PASSWORD'] ?? 'libsql';

    // Initialize and run the API
    $api = new LibSQLRestAPI($databaseDir, $authPassword);
    $api->handleRequest();
}
