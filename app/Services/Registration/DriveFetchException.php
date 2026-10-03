<?php

namespace App\Services\Registration;

use RuntimeException;

/**
 * A Google Drive file that could not be copied, with a reason fit to show an
 * admin (see RegistrationDriveFileService).
 */
class DriveFetchException extends RuntimeException {}
