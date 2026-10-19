@echo off
cd /d "%~dp0"
echo Starting Little Poll at http://127.0.0.1:8000
"C:\tools\php82\php.exe" -d extension_dir="C:\tools\php82\ext" -d extension=php_openssl.dll -d extension=php_curl.dll -d extension=php_fileinfo.dll -d extension=php_mbstring.dll -d extension=php_pdo_sqlite.dll -d extension=php_sqlite3.dll -d extension=php_zip.dll -S 127.0.0.1:8000 -t public
