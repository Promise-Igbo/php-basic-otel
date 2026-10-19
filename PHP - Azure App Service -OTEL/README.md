# PHP Voting Poll on Azure App Service with OpenTelemetry

A responsive PHP voting poll with session-only vote counts and a manually instrumented OpenTelemetry HTTP server span. It follows Microsoft's [PHP App Service sidecar walkthrough](https://techcommunity.microsoft.com/blog/appsonazureblog/part-i-otel-sidecar-extension-on-azure-app-service-for-linux---intro--php-walkth/4469514): PHP exports OTLP/HTTP to the **Observability: OpenTelemetry – Azure Monitor** sidecar extension, which sends telemetry to Application Insights.

## Architecture and support boundary

This uses the named App Service sidecar extension from the PHP walkthrough, not `ApplicationInsightsAgent_EXTENSION_VERSION` and not a separately hosted Collector. App Service manages the extension's sidecar lifecycle; the application still needs PHP OpenTelemetry instrumentation and OTLP export configured. Microsoft's general Azure Monitor distro language list does not include PHP, so use the PHP-specific walkthrough and its sample as the setup reference.

The app creates one server span per HTTP request. `startup.sh` installs/enables the PHP OpenTelemetry PECL extension as in the walkthrough. The poll uses manual spans; it does not include the sample's Slim-specific auto-instrumentation packages. This app does not currently emit application metrics or logs.

## Files

- `index.php` contains the poll, request validation, PHP session handling, and a manual OpenTelemetry server span.
- `style.css` contains the responsive UI styles.
- `composer.json` declares the OpenTelemetry SDK, OTLP exporter, and Guzzle PSR-18 adapter used by the app.
- `startup.sh` enables the PHP OpenTelemetry PECL extension if needed, then starts PHP-FPM.
- `default` configures NGINX to serve this app's root `index.php` through PHP-FPM.

## Local setup

Requirements: PHP 8.2+ with sessions, PECL, and Composer 2. From this folder:

```bash
composer install
OTEL_PHP_AUTOLOAD_ENABLED=true \
OTEL_TRACES_EXPORTER=console \
OTEL_METRICS_EXPORTER=none \
OTEL_LOGS_EXPORTER=none \
php -S 127.0.0.1:8000
```

Open <http://127.0.0.1:8000>. The console exporter is useful for local verification and does not send data to Application Insights. Stop the server with `Ctrl+C`.

The local console exporter does not send data to Application Insights. The Azure sidecar extension is configured in App Service and isn't created by the local PHP built-in server command.

## Azure App Service configuration

Create an **App Service Web App** on **Linux** using the built-in PHP runtime and code deployment, not a custom container. Select a PHP version supported by App Service that satisfies the `^8.2` Composer requirement. Check currently supported PHP versions before creating the app.

```bash
az webapp list-runtimes --os linux
az webapp config set \
	--resource-group <resource-group> \
	--name <app-name> \
	--linux-fx-version "PHP|<supported-php-version>"
```

Deploy the contents of this folder, including `index.php`, `style.css`, `composer.json`, `composer.lock`, `default`, and `startup.sh`, to the app root. For ZIP deployment with Composer build automation, optionally set:

```text
SCM_DO_BUILD_DURING_DEPLOYMENT=true
```

Create a ZIP from the *contents* of this folder, then deploy it, for example:

```bash
az webapp deploy \
	--resource-group <resource-group> \
	--name <app-name> \
	--src-path <app-package.zip>
```

## App settings and sidecar extension

In **Settings → Environment variables**, set these application settings on the PHP app:

| Setting | Value | Purpose |
| --- | --- | --- |
| `OTEL_PHP_AUTOLOAD_ENABLED` | `true` | Enables the PHP SDK autoload configuration used by the walkthrough. |
| `SCM_DO_BUILD_DURING_DEPLOYMENT` | `true` (optional) | Runs Composer during supported ZIP deployment build automation. |

Then configure the sidecar manually in the Azure portal:

1. Open **Deployment Center → Containers (new) → Add → Sidecar Extension**.
2. Select **Observability: OpenTelemetry – Azure Monitor**.
3. Enter the Application Insights connection string in the extension's configuration. Keep it out of source code.
4. Open the created sidecar's settings and map `OTEL_PHP_AUTOLOAD_ENABLED` from the main app setting using the portal's app-setting reference selector.
5. Check the extension-provided defaults. The walkthrough lists `OTEL_EXPORTER=azuremonitor`, `OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318`, and `OTEL_SERVICE_NAME=php-blessed-otel`. Set a meaningful service name if supported, and don't duplicate/override extension settings without checking the current portal UI.

These values and controls come from the linked walkthrough; verify the extension's current defaults in the target app before applying changes.

## PHP startup

The walkthrough's `startup.sh` installs and enables the `opentelemetry` PECL extension if it isn't already loaded, then starts PHP-FPM. The `default` file configures NGINX to route requests to the PHP app. Set this Startup Command in App Service **Configuration → Stack settings**:

```bash
cp /home/site/wwwroot/default /etc/nginx/sites-enabled/default && nginx -s reload && bash /home/site/wwwroot/startup.sh
```

Confirm these paths and commands against the PHP runtime image selected for the app before applying them. The included files follow the walkthrough's PHP App Service image assumptions.

## Availability and fallback

Before deployment, confirm that **Observability: OpenTelemetry – Azure Monitor** appears under **Deployment Center → Containers (new) → Add → Sidecar Extension** for this specific app. Do not assume availability for every subscription, region, plan, cloud, or App Service Environment. If it isn't offered or doesn't fit the target configuration, use the existing PHP OTLP/HTTP exporter with a separately hosted, secure Collector as a fallback; that requires additional hosting, network access, Collector configuration, and secret management. The fallback is not the default in this walkthrough.

## Vote behavior

- Vote counts are private to each PHP session; they are not shared or stored in a database.
- A successful vote redirects to the results page, so refreshing does not submit the same vote again.
- “Cast another vote” returns to the poll while retaining the current session tally.
- Vote input is validated, CSRF tokens are checked, and rendered poll text is HTML-escaped.

## Validation and manual deployment work

Before deployment, run `composer install` and `php -l index.php`, then verify the poll and local console traces. In Azure, the user must create/select App Service and Application Insights resources, confirm the sidecar extension is available for the target app, apply the portal configuration above, deploy the ZIP, and verify telemetry in Application Insights. This repository does not provision Azure resources or configure the extension. Local checks do not prove Azure sidecar availability or ingestion.

## References

- [Microsoft: Enable OpenTelemetry in Application Insights](https://learn.microsoft.com/en-us/azure/azure-monitor/app/opentelemetry-enable)
- [Microsoft: Configure OpenTelemetry in Application Insights](https://learn.microsoft.com/en-us/azure/azure-monitor/app/opentelemetry-configuration)
- [Microsoft: PHP App Service OpenTelemetry – Azure Monitor sidecar walkthrough](https://techcommunity.microsoft.com/blog/appsonazureblog/part-i-otel-sidecar-extension-on-azure-app-service-for-linux---intro--php-walkth/4469514)
- [Microsoft sample: PHP sidecar app](https://github.com/Azure-Samples/sidecar-samples/tree/main/otel-sidecar/php/php-blessed-app)
- [Microsoft: Sidecars overview](https://learn.microsoft.com/en-us/azure/app-service/overview-sidecar)
- [Microsoft: Configure sidecars](https://learn.microsoft.com/en-us/azure/app-service/configure-sidecar)
- [Microsoft: Configure a PHP app in Azure App Service](https://learn.microsoft.com/en-us/azure/app-service/configure-language-php?pivots=platform-linux)
- [Microsoft: Deploy files to Azure App Service](https://learn.microsoft.com/en-us/azure/app-service/deploy-zip)
- [OpenTelemetry PHP: Getting started](https://opentelemetry.io/docs/languages/php/getting-started/)
- [OpenTelemetry PHP: OTLP exporters](https://opentelemetry.io/docs/languages/php/exporters/)
- [OpenTelemetry Collector Contrib: Azure Monitor exporter](https://github.com/open-telemetry/opentelemetry-collector-contrib/tree/main/exporter/azuremonitorexporter)
