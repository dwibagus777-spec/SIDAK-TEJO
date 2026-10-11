<?php

/*
 | --------------------------------------------------------------------
 | App Namespace
 | --------------------------------------------------------------------
 |
 | This defines the default Namespace that is used throughout
 | CodeIgniter to refer to the Application directory. Change
 | this constant to change the namespace that all application
 | classes should use.
 |
 | NOTE: changing this will require manually modifying the
 | existing namespaces of App\* namespaced-classes.
 */
defined('APP_NAMESPACE') || define('APP_NAMESPACE', 'App');

/*
 | --------------------------------------------------------------------------
 | Composer Path
 | --------------------------------------------------------------------------
 |
 | The path that Composer's autoload file is expected to live. By default,
 | the vendor folder is in the Root directory, but you can customize that here.
 */
if (!defined('COMPOSER_PATH')) {
    $composerCandidates = [
        ROOTPATH . 'vendor/autoload.php',
        defined('APPPATH') ? realpath(APPPATH . '../vendor/autoload.php') : false,
        defined('FCPATH') ? realpath(FCPATH . '../vendor/autoload.php') : false,
        realpath(__DIR__ . '/../../vendor/autoload.php'),
    ];
    $resolvedComposerPath = ROOTPATH . 'vendor/autoload.php';
    foreach ($composerCandidates as $candidate) {
        if (!empty($candidate) && is_file($candidate)) {
            $resolvedComposerPath = $candidate;
            break;
        }
    }
    define('COMPOSER_PATH', $resolvedComposerPath);
}

/*
 |--------------------------------------------------------------------------
 | Timing Constants
 |--------------------------------------------------------------------------
 |
 | Provide simple ways to work with the myriad of PHP functions that
 | require information to be in seconds.
 */
defined('SECOND') || define('SECOND', 1);
defined('MINUTE') || define('MINUTE', 60);
defined('HOUR')   || define('HOUR', 3600);
defined('DAY')    || define('DAY', 86400);
defined('WEEK')   || define('WEEK', 604800);
defined('MONTH')  || define('MONTH', 2_592_000);
defined('YEAR')   || define('YEAR', 31_536_000);
defined('DECADE') || define('DECADE', 315_360_000);

/*
 | --------------------------------------------------------------------------
 | Exit Status Codes
 | --------------------------------------------------------------------------
 |
 | Used to indicate the conditions under which the script is exit()ing.
 | While there is no universal standard for error codes, there are some
 | broad conventions.  Three such conventions are mentioned below, for
 | those who wish to make use of them.  The CodeIgniter defaults were
 | chosen for the least overlap with these conventions, while still
 | leaving room for others to be defined in future versions and user
 | applications.
 |
 | The three main conventions used for determining exit status codes
 | are as follows:
 |
 |    Standard C/C++ Library (stdlibc):
 |       http://www.gnu.org/software/libc/manual/html_node/Exit-Status.html
 |       (This link also contains other GNU-specific conventions)
 |    BSD sysexits.h:
 |       http://www.gsp.com/cgi-bin/man.cgi?section=3&topic=sysexits
 |    Bash scripting:
 |       http://tldp.org/LDP/abs/html/exitcodes.html
 |
 */
defined('EXIT_SUCCESS')        || define('EXIT_SUCCESS', 0);        // no errors
defined('EXIT_ERROR')          || define('EXIT_ERROR', 1);          // generic error
defined('EXIT_CONFIG')         || define('EXIT_CONFIG', 3);         // configuration error
defined('EXIT_UNKNOWN_FILE')   || define('EXIT_UNKNOWN_FILE', 4);   // file not found
defined('EXIT_UNKNOWN_CLASS')  || define('EXIT_UNKNOWN_CLASS', 5);  // unknown class
defined('EXIT_UNKNOWN_METHOD') || define('EXIT_UNKNOWN_METHOD', 6); // unknown class member
defined('EXIT_USER_INPUT')     || define('EXIT_USER_INPUT', 7);     // invalid user input
defined('EXIT_DATABASE')       || define('EXIT_DATABASE', 8);       // database error
defined('EXIT__AUTO_MIN')      || define('EXIT__AUTO_MIN', 9);      // lowest automatically-assigned error code
defined('EXIT__AUTO_MAX')      || define('EXIT__AUTO_MAX', 125);    // highest automatically-assigned error code

/*
 |--------------------------------------------------------------------------
 | Persistent Storage Architecture Settings
 |--------------------------------------------------------------------------
 | Centralized path for user uploaded runtime photos outside deployment root.
 */
/*
 * Resolve persistent storage outside the deployed web root.
 * Hostinger File Manager shows sidak_storage beside public_html, so try
 * paths derived from the runtime root and known account locations first.
 * Never silently fall back to a deploy-managed public/foto directory.
 * 
 * Priority:
 * 1. Explicit SIDAK_STORAGE_PATH env var (manual override)
 * 2. Environment-aware: staging -> staging path, production -> production path
 * 3. Candidate auto-detection (staging path before production path)
 */
if (!defined('SIDAK_STORAGE_PATH')) {
    // 1. Explicit override via environment variable — validate matches environment
    $explicitPath = getenv('SIDAK_STORAGE_PATH');
    $env = getenv('CI_ENVIRONMENT') ?: ENVIRONMENT;
    $isStaging = ($env === 'staging');
    
    if ($explicitPath !== false && $explicitPath !== '' && is_dir($explicitPath) && is_writable($explicitPath)) {
        // Validate explicit path matches current environment
        $explicitPathNormalized = rtrim($explicitPath, '/\\') . DIRECTORY_SEPARATOR;
        if ($isStaging) {
            // Staging: path MUST contain staging identifier
            if (!str_contains($explicitPathNormalized, 'sidak_storage_staging')) {
                throw new RuntimeException('SIDAK_STORAGE_PATH explicitly set to production path but CI_ENVIRONMENT=staging. Use staging storage path (sidak_storage_staging) or unset SIDAK_STORAGE_PATH for auto-detection.');
            }
        } else {
            // Production: path MUST NOT contain staging identifier
            if (str_contains($explicitPathNormalized, 'sidak_storage_staging')) {
                throw new RuntimeException('SIDAK_STORAGE_PATH explicitly set to staging path but CI_ENVIRONMENT=production. Use production storage path (sidak_storage) or unset SIDAK_STORAGE_PATH for auto-detection.');
            }
        }
        define('SIDAK_STORAGE_PATH', $explicitPathNormalized);
    } else {
        // 2. Environment-aware prioritization

        // Candidates ordered by environment priority
        if ($isStaging) {
            // Staging: ONLY staging paths — NO production fallback
            $sidakStorageCandidates = [
                '/home/u532206332/sidak_storage_staging/foto/',
                rtrim(dirname(dirname(rtrim(FCPATH, '/\\'))), '/\\') . '/sidak_storage_staging/foto/',
                rtrim(dirname(rtrim(ROOTPATH, '/\\')), '/\\') . '/sidak_storage_staging/foto/',
            ];
        } else {
            // Production: ONLY production paths — NO staging fallback
            $sidakStorageCandidates = [
                '/home/u532206332/sidak_storage/foto/',
                rtrim(dirname(dirname(rtrim(FCPATH, '/\\'))), '/\\') . '/sidak_storage/foto/',
                rtrim(dirname(rtrim(ROOTPATH, '/\\')), '/\\') . '/sidak_storage/foto/',
                '/home/u532206332/domains/sidaktejo.site/sidak_storage/foto/',
            ];
        }

        $sidakStoragePath = null;
        foreach ($sidakStorageCandidates as $candidate) {
            $candidate = rtrim($candidate, '/\\') . DIRECTORY_SEPARATOR;
            if (is_dir($candidate) && is_writable($candidate)) {
                $sidakStoragePath = $candidate;
                break;
            }
        }

        // Fail-safe: do not silently write to deploy-managed web root
        if ($sidakStoragePath === null) {
            if ($isStaging) {
                // Staging must have its own storage configured
                throw new RuntimeException('SIDAK_STORAGE_PATH not resolved for staging. Configure SIDAK_STORAGE_PATH env var or ensure /home/u532206332/sidak_storage_staging/foto/ exists and is writable.');
            }
            define('SIDAK_STORAGE_PATH', '/home/u532206332/sidak_storage/foto/');
        } else {
            define('SIDAK_STORAGE_PATH', $sidakStoragePath);
        }
    }
}
