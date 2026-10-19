---
name: azure-appinsights-instrumentation
description: Detects codebase runtime language and applies correct, currently supported architectures to safely pipe application telemetry to Azure Application Insights across 5 core enterprise ecosystems.
version: "2.3"
compatibility: Microsoft Azure Monitor distros for supported languages; PHP App Service sidecar walkthrough; OpenTelemetry OTLP
---

# Multi-Language Azure Application Insights Instrumentation Protocol

You are activated to inject telemetry tracking hooks into a target application. Verify language criteria strictly against official provider support before generating instrumentation code.

## Step 1: Language Detection Matrix
Scan files to isolate the runtime platform. Use the dedicated blueprints below:

### Blueprint A: .NET (Officially Supported)
- **Detection:** `*.csproj` or `Program.cs`
- **Dependency:** Add `Microsoft.OpenTelemetry` or the `Azure.Monitor.OpenTelemetry.AspNetCore` NuGet package.
- **Implementation:** Inject into `Program.cs`:
  ```csharp
  using Azure.Monitor.OpenTelemetry.AspNetCore;

  var builder = WebApplication.CreateBuilder(args);
  builder.Services.AddOpenTelemetry().UseAzureMonitor(); // Automatically hooks into APPLICATIONINSIGHTS_CONNECTION_STRING
  ```

### Blueprint B: Node.js (Officially Supported)
- **Detection:** `package.json`
- **Dependency:** `npm install @azure/monitor-opentelemetry`
- **Implementation:** Inject at the absolute entry point (`index.js`):
  ```javascript
  import { useAzureMonitor } from "@azure/monitor-opentelemetry";
  useAzureMonitor(); // Automatically reads APPLICATIONINSIGHTS_CONNECTION_STRING from environment
  ```

### Blueprint C: Python (Officially Supported)
- **Detection:** `requirements.txt` or `pyproject.toml`
- **Dependency:** `pip install azure-monitor-opentelemetry`
- **Implementation:** Inject into entry script:
  ```python
  from azure.monitor.opentelemetry import configure_azure_monitor
  configure_azure_monitor() # Automatically bootstraps via environment variable string
  ```

### Blueprint D: Java (Officially Supported - No Code Baseline)
- **Detection:** `pom.xml` or `build.gradle`
- **Strategy:** Java relies on the **Azure Monitor OpenTelemetry Distro standalone Java agent**. Avoid injecting tracing boilerplate inside user source code unless explicitly requested.
- **Implementation:** Direct the user/CI-CD setup to download the latest `applicationinsights-agent.jar` and configure JVM options at boot:
  ```bash
  # Execute via container entrypoint or self-hosted runtime setup
  java -javaagent:path/to/applicationinsights-agent.jar -jar your-app.jar
  ```

### Blueprint E: PHP on Azure App Service Linux (Follow the Microsoft Sidecar Walkthrough)
- **Detection:** `composer.json` or `index.php`
- **Required walkthrough:** Follow Microsoft's [PHP code-based App Service walkthrough](https://techcommunity.microsoft.com/blog/appsonazureblog/part-i-otel-sidecar-extension-on-azure-app-service-for-linux---intro--php-walkth/4469514) and inspect its linked [PHP sample](https://github.com/Azure-Samples/sidecar-samples/tree/main/otel-sidecar/php/php-blessed-app). It documents the portal extension named **Observability: OpenTelemetry – Azure Monitor**. Do not substitute the generic sidecar tutorial, a separately hosted Collector, or `ApplicationInsightsAgent_EXTENSION_VERSION` as the default.
- **App settings and portal configuration from the walkthrough:** Set `OTEL_PHP_AUTOLOAD_ENABLED=true` on the PHP app. In Deployment Center → Containers (new) → Add → Sidecar Extension, choose **Observability: OpenTelemetry – Azure Monitor** and provide the Application Insights connection string. In the sidecar's settings, map `OTEL_PHP_AUTOLOAD_ENABLED` from the app setting. The extension sets defaults including `OTEL_EXPORTER=azuremonitor`, `OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318`, and `OTEL_SERVICE_NAME`; set a meaningful service name if needed. `SCM_DO_BUILD_DURING_DEPLOYMENT=true` is optional when the selected deployment method requires App Service Composer build automation.
- **Composer and framework fit:** Inspect the sample's `composer.json` for its SDK, OTLP exporter, PHP auto-instrumentation extension, and instrumentation packages, then adapt to the existing app and its actual framework. The sample is Slim-specific; do not add Slim instrumentation to Laravel or another framework. Verify package versions, Composer plugin allow-list, PHP version, and PECL extension requirements against the sample and current package docs.
- **Startup:** Follow the walkthrough's `startup.sh` behavior: install/enable the `opentelemetry` PECL extension when absent, then start PHP-FPM. For the sample's NGINX routing, it deploys a `default` config and uses `cp /home/site/wwwroot/default /etc/nginx/sites-enabled/default && nginx -s reload && bash /home/site/wwwroot/startup.sh` as the App Service startup command. Include these only when the existing app/runtime needs the same setup; verify paths against the chosen App Service PHP image and repository.
- **Availability and secrets:** Do not claim the extension is visible or enabled for every subscription, region, plan, App Service Environment, or cloud. Have the user verify it appears for the target Linux code-runtime app in the portal before implementation/deployment assumptions. Treat the connection string as a secret; enter it only in the sidecar extension/App Service configuration, never source code. `ApplicationInsightsAgent_EXTENSION_VERSION=~3` is the legacy App Service-managed agent setting for documented stacks, not this PHP sidecar extension.
- **Fallback only if needed:** If the named extension is unavailable or unsuitable for the target app, preserve an externally hosted Collector using OTLP/HTTP as an explicitly separate fallback and document its additional hosting, network, authentication, and operations requirements. Do not make it the default.
- **Verification:** Use the walkthrough's validation steps and confirm telemetry in Application Insights (for example, Application Map, Live Metrics, or Search). Local syntax/Composer checks do not prove cloud ingestion. Do not provision Azure resources or enable the sidecar unless explicitly asked.

**Required verification before implementation:**

1. Use the linked Microsoft PHP walkthrough and sample as the primary recipe; cross-check its current app settings/startup flow against [App Service sidecar docs](https://learn.microsoft.com/en-us/azure/app-service/overview-sidecar) and [PHP OTel docs](https://opentelemetry.io/docs/languages/php/getting-started/).
2. Verify that **Observability: OpenTelemetry – Azure Monitor** is available for the target app in the portal. Do not assume availability for all subscriptions, regions, plans, clouds, or App Service Environments.
3. Preserve application behavior and code-runtime deployment. Do not migrate the runtime, introduce Docker Compose, or change Azure resources/settings unless requested.
4. Distinguish the walkthrough's local/configuration checks from actual telemetry arriving in Application Insights; report any portal validation the user must perform.

**Architecture Selection and Verification**

- Verify the target host/runtime's supported instrumentation and export path against current official documentation before choosing an architecture. A managed platform agent, language SDK, OTLP receiver, and Azure Monitor exporter are distinct components.
- For PHP App Service code-runtime work, use the named sidecar extension and PHP walkthrough above as the default; retain a separately hosted Collector only as fallback.
- Do not conflate the sidecar extension, the legacy App Service agent setting, and an external Collector.
- Keep connection strings, API keys, and OTLP authentication headers out of source. Follow the walkthrough's sidecar/app-setting mapping and account for shared App Service environment variables.
- Never claim telemetry ingestion is verified unless it has actually been tested against a reachable Collector and the target Azure Monitor resource.


## Step 2: Secret Isolation
For all language blocks generated, never hardcode or print credentials. For this PHP sidecar walkthrough, configure the Application Insights connection string through the sidecar extension portal flow and use the OTEL environment settings documented by the walkthrough. Do not move the connection string into application source. For the external-Collector fallback only, configure its endpoint/authentication separately and keep those values in App Service settings.
