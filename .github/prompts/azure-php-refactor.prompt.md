---
name: Azure PHP App Service Refactor
description: Refactor PHP for Azure App Service Linux with a verified Azure Monitor OpenTelemetry architecture.
---

Read and follow `.github/copilot-instructions.md` and `.github/skills/azure-appinsights-instrumentation/SKILL.md`.

Inspect the existing PHP application and refactor it for direct deployment to Azure App Service on Linux using the code runtime, without Docker Compose.

Deliver:
- A clean `index.php`.
- A matching `composer.json`.
- A `README.md` with precise deployment instructions and the required Azure App Service settings.

Architecture requirements:
- Follow Microsoft's PHP-specific [OpenTelemetry – Azure Monitor sidecar walkthrough](https://techcommunity.microsoft.com/blog/appsonazureblog/part-i-otel-sidecar-extension-on-azure-app-service-for-linux---intro--php-walkth/4469514) and inspect its linked [PHP sample](https://github.com/Azure-Samples/sidecar-samples/tree/main/otel-sidecar/php/php-blessed-app). Make this the default architecture for this task, not a separately hosted Collector.
- Adapt the walkthrough to the existing repository/framework. Its sample is Slim-specific: do not add Slim or Slim auto-instrumentation packages to an existing Laravel app. Verify the compatible Laravel/manual PHP instrumentation path and dependencies from the current code and official package documentation; do not invent package names or APIs.
- Implement PHP OTel bootstrap/instrumentation and OTLP/HTTP configuration using the walkthrough's required PHP OpenTelemetry PECL extension and packages that apply to this app. Preserve existing app behavior and PHP Linux App Service code-runtime deployment; do not migrate runtimes or introduce Docker Compose.
- Document the walkthrough's main-app setting `OTEL_PHP_AUTOLOAD_ENABLED=true` and map `OTEL_PHP_AUTOLOAD_ENABLED` into the sidecar as described. Document that adding **Deployment Center → Containers (new) → Add → Sidecar Extension → Observability: OpenTelemetry – Azure Monitor** and entering the Application Insights connection string are portal steps for the user, not changes to perform automatically.
- Document the sidecar defaults from the walkthrough when applicable: `OTEL_EXPORTER=azuremonitor`, `OTEL_EXPORTER_OTLP_ENDPOINT=http://127.0.0.1:4318`, and `OTEL_SERVICE_NAME` (set a meaningful app-specific value). Do not replace or duplicate extension-provided settings without verifying what the portal extension currently configures.
- Document the PHP startup requirements from the walkthrough: install/enable the `opentelemetry` PECL extension if it is absent, then start PHP-FPM. If the existing App Service NGINX setup requires the sample `default` config, document the startup command `cp /home/site/wwwroot/default /etc/nginx/sites-enabled/default && nginx -s reload && bash /home/site/wwwroot/startup.sh`; first reconcile paths and startup behavior with this repository so the instructions do not overwrite a working configuration unnecessarily. `SCM_DO_BUILD_DURING_DEPLOYMENT=true` is optional and only for deployment methods that need Composer build automation.
- Distinguish the named sidecar extension from the legacy `ApplicationInsightsAgent_EXTENSION_VERSION` setting. Do not use the legacy setting as the PHP instrumentation path and do not assume the extension is available in every subscription, region, plan, cloud, or App Service Environment. Tell the user to verify the sidecar extension is offered for their target app in the portal; document any unresolved availability check.
- Preserve a separately hosted Collector using OTLP/HTTP only as a fallback if the named sidecar extension is unavailable or unsuitable. Explain its added infrastructure and secure network endpoint; do not make this comparison or fallback the primary architecture.
- Keep connection strings and credentials out of source. Do not set the Application Insights connection string in PHP code; use the sidecar extension's documented portal configuration. Note that App Service app settings can be shared with sidecars and handle secret mapping accordingly.

Validation:
- Check dependencies, PHP compatibility, and configuration consistency.
- Run available local checks.
- Run local tests and validate PHP instrumentation/export configuration where feasible; local OTLP/console checks are not proof of sidecar availability or Azure Monitor ingestion.
- Clearly distinguish local validation from actual Azure deployment and ingestion tests.
- Do not provision Azure resources, add/configure the sidecar extension, deploy a Collector, or change Azure settings. These are manual portal/deployment steps for the user.
- Report changed files, the walkthrough and official sources consulted, any unsupported requirements, assumptions, remaining manual deployment/portal steps, and unverified behavior.
