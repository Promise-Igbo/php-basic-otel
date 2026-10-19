# GitHub Copilot Project Instructions

## Workflow
- Inspect the existing codebase before making changes.
- Identify the runtime, framework, entry points, dependencies, and deployment model.
- Read and follow relevant skills in `.github/skills/` before implementing specialized tasks.
- Preserve existing functionality unless the requested task requires changing it.

## Technical Accuracy
- Verify version-sensitive and platform-specific requirements against current official documentation.
- Do not invent environment variables, Azure app settings, package APIs, or platform capabilities.
- Distinguish supported configurations from assumptions and workarounds.
- If a requested architecture is unsupported, explain why and propose the closest supported alternative.

## Implementation
- Make the smallest complete set of changes needed.
- Never hardcode credentials or secrets.
- Do not introduce Docker or Docker Compose when direct platform deployment is requested.
- Keep source code, dependencies, configuration, and documentation consistent.

## Deliverables and Validation
- Follow the task's explicit deliverables and any applicable skill requirements.
- Run relevant tests, dependency checks, and validation commands where available.
- Do not claim checks passed unless they were actually run.
- Report changed files, validation results, limitations, and remaining manual steps.
