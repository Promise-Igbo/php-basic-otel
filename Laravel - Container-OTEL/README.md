# Little Poll — Laravel

A small responsive voting UI built with Laravel 12, Blade, HTML, and CSS. Picks accumulate in the current session and are cleared when the browser refreshes; no long-term or shared tally is stored.

## Requirements

- PHP 8.2 or newer with the extensions required by Laravel
- Composer 2

## Run locally

From this folder, install the framework dependencies, create the local environment file, and generate the application key once:

```powershell
$php = 'C:\tools\php82\php.exe'
$phpOptions = @(
	'-d', 'extension_dir=C:\tools\php82\ext',
	'-d', 'extension=php_openssl.dll',
	'-d', 'extension=php_curl.dll',
	'-d', 'extension=php_fileinfo.dll',
	'-d', 'extension=php_mbstring.dll',
	'-d', 'extension=php_pdo_sqlite.dll',
	'-d', 'extension=php_sqlite3.dll',
	'-d', 'extension=php_zip.dll'
)
& $php @phpOptions 'C:\ProgramData\ComposerSetup\bin\composer.phar' install
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
& $php @phpOptions .\artisan key:generate
```

After setup, open a new terminal in this folder and start the app with:

```powershell
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser. This uses PHP 8.2 from your PATH. Stop the server with `Ctrl+C`. If the PATH change is not visible in an existing terminal, close it and open a new one. `run.bat` remains available as a launcher that selects PHP 8.2 directly.

## Session behavior

- The poll uses Laravel's web middleware, CSRF protection, and file-backed sessions.
- Submitted picks accumulate in the file-backed session and are shown after each Post/Redirect/Get flow.
- A small browser reload check clears the poll's session tally when the results page is refreshed. The server-side Clear picks button is also available.
- Picks are not accumulated, shared between visitors, or saved to a database.

This demo is suitable for a local learning project. Keep real vote aggregation separate from the temporary session display.

## Tests

After installing dependencies, run the feature tests with the same PHP 8.2 executable and extensions used by `run.bat`:

```powershell
& $php @phpOptions .\vendor\bin\phpunit
```
