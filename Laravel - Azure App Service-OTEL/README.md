# Little Poll — Laravel

A responsive session-only voting poll built with Laravel 12, Blade, HTML, and CSS. It emits one manually instrumented OpenTelemetry server span per HTTP request and exports traces over OTLP/HTTP to an OpenTelemetry Collector.

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

Open <http://127.0.0.1:8000>. This uses PHP 8.2 from your PATH. Stop the server with `Ctrl+C`. If the PATH change is not visible in an existing terminal, close it and open a new one. `run.bat` remains available as a launcher that selects PHP 8.2 directly.

## Managed App Service instrumentation support

The requested platform-managed Azure Monitor OpenTelemetry integration is **not documented as supporting PHP** on Linux App Service code deployments. Microsoft's current [Application Insights autoinstrumentation support matrix](https://learn.microsoft.com/en-us/azure/azure-monitor/app/codeless-overview#supported-environments-languages-and-resource-providers) lists ASP.NET Core/.NET, Java, Node.js, and Python for Linux App Service; PHP is not listed. Microsoft's [App Service monitoring guidance](https://learn.microsoft.com/en-us/azure/app-service/monitor-app-service) likewise recommends Application Insights instrumentation for ASP.NET Core, ASP.NET, Java, Node.js, and Python, not PHP.

`ApplicationInsightsAgent_EXTENSION_VERSION=~3` is **not** a general-purpose OTLP sidecar switch for any runtime. Microsoft documents it for the supported App Service agents. Setting it on this PHP app would not make the PHP traces supported or remove the need for an OTLP receiver.

| Alternative | Keeps PHP on Linux App Service code runtime? | What it means |
| --- | --- | --- |
| Current manual PHP OpenTelemetry → OTLP Collector → Azure Monitor | Yes | Keeps this code runtime and avoids Docker Compose for the app, but requires a separately hosted Collector configured with the Azure Monitor exporter. This is the only option here that carries the app's custom PHP spans to Application Insights; Microsoft does not provide a PHP distro/exporter or promise a managed PHP agent. |
| App Service-managed Application Insights autoinstrumentation | No, not for PHP | Simplest managed option if the app is migrated to a documented Linux code runtime such as .NET, Java, Node.js, or Python. App Service then manages the agent and uses `APPLICATIONINSIGHTS_CONNECTION_STRING` plus its documented agent settings. |
| App Service platform monitoring only | Yes | Provides App Service platform metrics and diagnostics/log streaming, but does not export this application's custom OpenTelemetry spans or PHP request traces into Application Insights. |

The request for a **managed PHP OTel sidecar** therefore cannot be met with the documented App Service code-runtime integration. The implementation below intentionally retains the external Collector architecture rather than inventing a PHP sidecar setting.

## OpenTelemetry

The app uses the OpenTelemetry PHP SDK and OTLP exporter. Microsoft does not provide an Application Insights exporter or distro for PHP, so the app sends traces to a separately hosted OpenTelemetry Collector; the Collector exports them to Application Insights. No PHP OpenTelemetry extension or automatic Laravel instrumentation is used.

`TraceHttpRequests` instruments requests with HTTP method, matched route, response status, and exception details. This sample exports traces only; it does not emit OpenTelemetry metrics or logs. If `OTEL_EXPORTER_OTLP_ENDPOINT` is unset, request spans use a no-op tracer and are not exported.
The current simple span processor exports synchronously, so request latency depends on the Collector's response time. Consider a batching or asynchronous export strategy before using this sample for high-throughput workloads.

To export local traces, make an OTLP/HTTP Collector receiver reachable and set its base URL before starting Laravel:

```powershell
$env:OTEL_EXPORTER_OTLP_ENDPOINT = 'http://127.0.0.1:4318'
$env:OTEL_SERVICE_NAME = 'little-poll'
php artisan serve
```

The app sends protobuf to `<endpoint>/v1/traces`; do not include `/v1/traces` in the endpoint setting. For production, use HTTPS and protect the Collector receiver with its supported authentication mechanism.
Optional OTLP headers can be supplied in the standard comma-separated `key=value` form through `OTEL_EXPORTER_OTLP_HEADERS`; URL-encode reserved characters in values and store authentication values only in environment settings.

## Deploy to Azure App Service

Use an **App Service Web App on Linux** with the built-in PHP runtime and code deployment. This project requires PHP 8.2 or newer. Check the currently available App Service runtimes and select a version that satisfies the Composer requirement:

```bash
az webapp list-runtimes --os linux
az webapp config set \
	--resource-group <resource-group> \
	--name <app-name> \
	--linux-fx-version "PHP|<supported-php-version>"
```

Deploy the project root, including `composer.json`, `composer.lock`, and the `default` NGINX configuration file. App Service's PHP NGINX image normally serves the project root, but Laravel's public document root is `public/`. The included `default` file points NGINX to that directory and forwards application routes to `public/index.php`.

Configure this startup command in the App Service **Configuration → General settings**:

```bash
cp /home/site/wwwroot/default /etc/nginx/sites-available/default && service nginx reload
```

The startup command restores the custom NGINX configuration after each app restart. Enable Composer build automation for ZIP deployment by setting `SCM_DO_BUILD_DURING_DEPLOYMENT=true`. Deploy a ZIP containing the project files (not an enclosing project directory), for example:

```bash
az webapp deploy \
	--resource-group <resource-group> \
	--name <app-name> \
	--src-path <app-package.zip>
```

Set the following App Service application settings under **Settings → Environment variables**:

| Setting | Value | Purpose |
| --- | --- | --- |
| `SCM_DO_BUILD_DURING_DEPLOYMENT` | `true` | Installs Composer dependencies during deployment build automation. |
| `APP_ENV` | `production` | Enables Laravel production mode. |
| `APP_DEBUG` | `false` | Prevents detailed exception pages from being shown to visitors. |
| `APP_KEY` | `<generated Laravel key>` | Laravel encryption key; generate it securely and store it only as an App Service setting. |
| `APP_URL` | `https://<app-name>.azurewebsites.net` | Public application URL. |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | `https://<collector-host>` | Base URL of the external Collector's OTLP/HTTP receiver. The app appends `/v1/traces`. |
| `OTEL_EXPORTER_OTLP_HEADERS` | `<url-encoded-header-pairs>` | Optional comma-separated OTLP HTTP headers, including Collector authentication when required. Keep secret values in App Service settings. |
| `OTEL_SERVICE_NAME` | `little-poll` | Service identity attached to exported spans. |

Do not commit the production `APP_KEY` or any Collector authentication values. The PHP app sends OTLP to the Collector; it does not need the Application Insights connection string.

## Collector to Application Insights

Run the Collector separately from the PHP App Service. A trace pipeline can use the OTLP/HTTP receiver and Azure Monitor exporter:

```yaml
receivers:
  otlp:
    protocols:
      http:
        endpoint: 0.0.0.0:4318

processors:
  batch:

exporters:
  azure_monitor: {}

service:
  pipelines:
    traces:
      receivers: [otlp]
      processors: [batch]
      exporters: [azure_monitor]
```

Set `APPLICATIONINSIGHTS_CONNECTION_STRING` in the **Collector's** environment, using the connection string from the target Application Insights resource. Keep it secret. Configure the receiver's network binding and authentication for your hosting environment; do not expose an unauthenticated public receiver.

## Session behavior

- The poll uses Laravel's web middleware, CSRF protection, and file-backed sessions.
- Submitted picks accumulate in the file-backed session and are shown after each Post/Redirect/Get flow.
- A small browser reload check clears the poll's session tally when the results page is refreshed. The server-side Clear picks button is also available.
- Picks are not accumulated, shared between visitors, or saved to a database.

This demo is suitable for a learning project. Keep real vote aggregation separate from the temporary session display.

## Tests

After installing dependencies, run the feature tests with the same PHP 8.2 executable and extensions used by `run.bat`:

```powershell
& $php @phpOptions .\vendor\bin\phpunit
```

## References

- [Microsoft: App Service Application Insights autoinstrumentation support matrix](https://learn.microsoft.com/en-us/azure/azure-monitor/app/codeless-overview#supported-environments-languages-and-resource-providers)
- [Microsoft: Monitor Azure App Service](https://learn.microsoft.com/en-us/azure/app-service/monitor-app-service)
- [Microsoft: Enable OpenTelemetry in Application Insights](https://learn.microsoft.com/en-us/azure/azure-monitor/app/opentelemetry-enable)
- [Microsoft: Configure OpenTelemetry in Application Insights](https://learn.microsoft.com/en-us/azure/azure-monitor/app/opentelemetry-configuration)
- [Microsoft: Configure a PHP app in Azure App Service](https://learn.microsoft.com/en-us/azure/app-service/configure-language-php?pivots=platform-linux)
- [Microsoft: Deploy files to Azure App Service](https://learn.microsoft.com/en-us/azure/app-service/deploy-zip)
- [OpenTelemetry PHP: Getting started](https://opentelemetry.io/docs/languages/php/getting-started/)
- [OpenTelemetry PHP: OTLP exporters](https://opentelemetry.io/docs/languages/php/exporters/)
- [OpenTelemetry Collector Contrib: Azure Monitor exporter](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/exporter/azuremonitorexporter)
