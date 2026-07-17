# Docs Local Development

This project uses MkDocs and Material theme.

## Prerequisites

- `uv` installed
- Python `3.12+`
- Node.js `22+` (`npx` required for Mermaid SVG rendering)

## Build and Validate

From repository root:

```bash
uv sync --frozen
uv run openapi-spec-validator docs/api/openapi.yaml
uv run mkdocs build --strict
```

OpenAPI validation checks specification structure and local references. `--strict` fails build on documentation warnings.

## Regenerate ER Diagram

```bash
composer docs:erd
```

This updates `docs/diagrams/erd.md` from current Laravel schema.

## Run Local Preview

```bash
uv run mkdocs serve
```

Open `http://127.0.0.1:8001`.

## Cleanup

```bash
rm -rf .venv
```
